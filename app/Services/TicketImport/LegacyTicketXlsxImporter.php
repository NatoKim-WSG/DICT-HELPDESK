<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class LegacyTicketXlsxImporter
{
    /**
     * @var array<int, User>|null
     */
    private ?array $usersById = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $usersByEmail = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $usersByUsername = null;

    /**
     * @var array<string, User>|null
     */
    private ?array $supportUsersByName = null;

    /**
     * @var array<int, Category>|null
     */
    private ?array $categoriesById = null;

    /**
     * @var array<string, Category>|null
     */
    private ?array $categoriesByName = null;

    public function __construct(
        private readonly HelpdeskTrackerXlsxParser $parser,
        private readonly HelpdeskTrackerDescriptionFormatter $formatter,
    ) {}

    /**
     * @param  list<string>  $paths
     * @param  array{
     *     default_user?: string|int|null,
     *     default_category?: string|int|null,
     *     default_priority?: string|null,
     *     default_status?: string|null,
     *     source_timezone?: string|null,
     *     dry_run?: bool,
     *     update_existing?: bool
     * }  $options
     * @return array{
     *     paths: list<string>,
     *     files: int,
     *     rows: int,
     *     validated: int,
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     dry_run: bool
     * }
     */
    public function import(array $paths, array $options = []): array
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one spreadsheet path is required.');
        }

        $settings = $this->normalizeOptions($options);
        $resolvedPaths = array_map(fn (string $path) => $this->resolvePath($path), $paths);
        $preparedRows = [];

        foreach ($resolvedPaths as $resolvedPath) {
            foreach ($this->parser->parse($resolvedPath) as $row) {
                $preparedRows[] = $this->prepareRow($resolvedPath, $row, $settings);
            }
        }

        $summary = [
            'paths' => $resolvedPaths,
            'files' => count($resolvedPaths),
            'rows' => count($preparedRows),
            'validated' => count($preparedRows),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $settings['dry_run'],
        ];

        if ($settings['dry_run']) {
            return $summary;
        }

        $existingTicketsByKey = $settings['update_existing']
            ? $this->loadExistingTicketsByMatchKey($preparedRows)
            : [];

        DB::transaction(function () use ($preparedRows, $settings, &$summary, &$existingTicketsByKey): void {
            foreach ($preparedRows as $preparedRow) {
                $ticketNumber = $preparedRow['ticket_number'];

                if ($ticketNumber !== null) {
                    $existingTicket = Ticket::query()->where('ticket_number', $ticketNumber)->first();

                    if ($existingTicket instanceof Ticket) {
                        if (! $settings['update_existing']) {
                            $summary['skipped']++;

                            continue;
                        }

                        $existingTicket->timestamps = false;
                        $existingTicket->forceFill($preparedRow['attributes']);
                        $existingTicket->save();
                        $summary['updated']++;

                        continue;
                    }
                }

                if (! $settings['update_existing']) {
                    $ticket = new Ticket;
                    $ticket->timestamps = false;
                    $ticket->forceFill($preparedRow['attributes']);
                    $ticket->save();
                    $summary['imported']++;

                    continue;
                }

                $matchKey = $this->buildMatchKey($preparedRow['match_subject'], $preparedRow['match_created_at']);
                /** @var Collection<int, Ticket> $matches */
                $matches = $existingTicketsByKey[$matchKey] ?? collect();
                /** @var Ticket|null $existingTicket */
                $existingTicket = $matches->shift();
                $existingTicketsByKey[$matchKey] = $matches;

                if ($existingTicket instanceof Ticket) {
                    $existingTicket->timestamps = false;
                    $existingTicket->forceFill($preparedRow['attributes']);
                    $existingTicket->save();
                    $summary['updated']++;

                    continue;
                }

                $ticket = new Ticket;
                $ticket->timestamps = false;
                $ticket->forceFill($preparedRow['attributes']);
                $ticket->save();
                $summary['imported']++;
            }
        });

        return $summary;
    }

    /**
     * @param  array{
     *     default_user: string|null,
     *     default_category: string|null,
     *     default_priority: string,
     *     default_status: string,
     *     source_timezone: string,
     *     dry_run: bool,
     *     update_existing: bool
     * }  $settings
     * @param  array{
     *     source_sheet: string,
     *     source_row: int,
     *     values: array<string, string|null>
     * }  $row
     * @return array{
     *     ticket_number: string|null,
     *     match_subject: string,
     *     match_created_at: Carbon,
     *     attributes: array<string, mixed>
     * }
     */
    private function prepareRow(string $resolvedPath, array $row, array $settings): array
    {
        $values = $row['values'];
        $projectName = $this->nullableString($values['project_name'] ?? null);
        $issueDescription = $this->nullableString($values['issue_description'] ?? null);

        if ($projectName === null && $issueDescription === null) {
            throw new RuntimeException(sprintf(
                'Spreadsheet row %d in %s is missing both project name and issue description.',
                $row['source_row'],
                basename($resolvedPath)
            ));
        }

        $createdAt = $this->formatter->combineDateAndTime(
            $values['date_received'] ?? null,
            $values['time_received'] ?? null,
            $settings['source_timezone'],
        );

        if (! $createdAt instanceof Carbon) {
            throw new RuntimeException(sprintf(
                'Spreadsheet row %d in %s is missing a usable Date Received value.',
                $row['source_row'],
                basename($resolvedPath)
            ));
        }

        $completedAt = $this->formatter->combineDateAndTime(
            $values['date_resolved'] ?? null,
            $values['time_resolved'] ?? null,
            $settings['source_timezone'],
        );

        $requester = $this->resolveUserReference($settings['default_user'], $row['source_row'], 'requester');
        $category = $this->resolveCategoryReference($settings['default_category'], $row['source_row']);
        $subject = $this->formatter->buildSubject($projectName, $issueDescription, $values['issue_via'] ?? null);
        $displayTimezone = (string) config('app.timezone', $settings['source_timezone']);
        $ticketNumber = $this->nullableString($values['ticket_number'] ?? null);
        $requesterSnapshot = $this->extractRequesterSnapshot($values['requestor_details'] ?? null, $requester);
        $assignedUser = $this->resolveOptionalSupportUserByDisplayName($values['attended_by'] ?? null);

        return [
            'ticket_number' => $ticketNumber,
            'match_subject' => $subject,
            'match_created_at' => $createdAt,
            'attributes' => [
                'ticket_number' => $ticketNumber,
                'name' => $requesterSnapshot['name'],
                'contact_number' => $requesterSnapshot['contact_number'],
                'email' => $requesterSnapshot['email'],
                'province' => $requesterSnapshot['province'],
                'municipality' => $requesterSnapshot['municipality'],
                'subject' => $subject,
                'description' => $this->formatter->buildDescription(
                    fields: [
                        'date_received' => $values['date_received'] ?? null,
                        'time_received' => $values['time_received'] ?? null,
                        'date_resolved' => $values['date_resolved'] ?? null,
                        'time_resolved' => $values['time_resolved'] ?? null,
                        'issue_via' => $values['issue_via'] ?? null,
                        'requestor_details' => $values['requestor_details'] ?? null,
                        'project_name' => $projectName,
                        'issue_description' => $issueDescription,
                        'resolution' => $values['resolution'] ?? null,
                        'attended_by' => $values['attended_by'] ?? null,
                    ],
                    createdAt: $createdAt,
                    completedAt: $completedAt,
                    displayTimezone: $displayTimezone,
                ),
                'priority' => $settings['default_priority'],
                'status' => $completedAt ? 'closed' : $settings['default_status'],
                'user_id' => $requester->id,
                'assigned_to' => $assignedUser?->id,
                'assigned_at' => $assignedUser ? $createdAt->copy() : null,
                'category_id' => $category->id,
                'created_at' => $createdAt,
                'updated_at' => $completedAt ?? $createdAt->copy(),
                'resolved_at' => $completedAt,
                'closed_at' => $completedAt,
            ],
        ];
    }

    /**
     * @param  list<array{
     *     ticket_number: string|null,
     *     match_subject: string,
     *     match_created_at: Carbon,
     *     attributes: array<string, mixed>
     * }>  $preparedRows
     * @return array<string, Collection<int, Ticket>>
     */
    private function loadExistingTicketsByMatchKey(array $preparedRows): array
    {
        $existingTicketsByKey = [];

        foreach ($preparedRows as $preparedRow) {
            if ($preparedRow['ticket_number'] !== null) {
                continue;
            }

            $matchKey = $this->buildMatchKey($preparedRow['match_subject'], $preparedRow['match_created_at']);

            if (array_key_exists($matchKey, $existingTicketsByKey)) {
                continue;
            }

            $existingTicketsByKey[$matchKey] = Ticket::query()
                ->where('subject', $preparedRow['match_subject'])
                ->where('created_at', $preparedRow['match_created_at'])
                ->orderBy('id')
                ->get();
        }

        return $existingTicketsByKey;
    }

    private function buildMatchKey(string $subject, Carbon $createdAt): string
    {
        return $subject.'|'.$createdAt->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    /**
     * @return array{
     *     default_user: string|null,
     *     default_category: string|null,
     *     default_priority: string,
     *     default_status: string,
     *     source_timezone: string,
     *     dry_run: bool,
     *     update_existing: bool
     * }
     */
    private function normalizeOptions(array $options): array
    {
        $defaultUser = $this->nullableString($options['default_user'] ?? null);
        if ($defaultUser === null) {
            throw new InvalidArgumentException('The XLSX importer requires --default-user because tracker files do not include requester accounts.');
        }

        $defaultCategory = $this->nullableString($options['default_category'] ?? null) ?? 'Other';
        $defaultPriority = $this->nullableString($options['default_priority'] ?? null) ?? 'medium';
        $defaultStatus = $this->nullableString($options['default_status'] ?? null) ?? 'open';

        if (! in_array($defaultPriority, Ticket::PRIORITIES, true)) {
            throw new InvalidArgumentException('Unsupported default priority: '.$defaultPriority);
        }

        if (! in_array($defaultStatus, Ticket::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported default status: '.$defaultStatus);
        }

        return [
            'default_user' => $defaultUser,
            'default_category' => $defaultCategory,
            'default_priority' => $defaultPriority,
            'default_status' => $defaultStatus,
            'source_timezone' => $this->nullableString($options['source_timezone'] ?? null)
                ?? (string) config('helpdesk.ticket_import_timezone', config('app.timezone', 'UTC')),
            'dry_run' => (bool) ($options['dry_run'] ?? false),
            'update_existing' => (bool) ($options['update_existing'] ?? false),
        ];
    }

    private function resolvePath(string $path): string
    {
        $trimmedPath = trim($path);
        if ($trimmedPath === '') {
            throw new InvalidArgumentException('Import path cannot be empty.');
        }

        $storageCandidate = Storage::disk((string) config('helpdesk.ticket_import_disk', 'local'))
            ->path(trim((string) config('helpdesk.ticket_import_path', 'imports').'/'.$trimmedPath, '/'));

        $candidates = [
            $trimmedPath,
            base_path($trimmedPath),
            $storageCandidate,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Import file not found: '.$trimmedPath);
    }

    private function resolveUserReference(?string $reference, int $row, string $context): User
    {
        if ($reference === null) {
            throw new RuntimeException(sprintf('Spreadsheet row %d could not resolve a %s user.', $row, $context));
        }

        if (ctype_digit($reference)) {
            $user = $this->usersById()[(int) $reference] ?? null;
            if ($user instanceof User) {
                return $user;
            }
        }

        $normalizedReference = strtolower($reference);
        $user = $this->usersByEmail()[$normalizedReference] ?? $this->usersByUsername()[$normalizedReference] ?? null;
        if ($user instanceof User) {
            return $user;
        }

        throw new RuntimeException(sprintf('Spreadsheet row %d could not resolve %s "%s".', $row, $context, $reference));
    }

    private function resolveCategoryReference(?string $reference, int $row): Category
    {
        if ($reference === null) {
            throw new RuntimeException(sprintf('Spreadsheet row %d could not resolve a category.', $row));
        }

        if (ctype_digit($reference)) {
            $category = $this->categoriesById()[(int) $reference] ?? null;
            if ($category instanceof Category) {
                return $category;
            }
        }

        $category = $this->categoriesByName()[strtolower($reference)] ?? null;
        if ($category instanceof Category) {
            return $category;
        }

        throw new RuntimeException(sprintf('Spreadsheet row %d could not resolve category "%s".', $row, $reference));
    }

    private function resolveOptionalSupportUserByDisplayName(?string $displayName): ?User
    {
        $displayName = $this->normalizeLookupKey($displayName);
        if ($displayName === null) {
            return null;
        }

        return $this->supportUsersByName()[$displayName] ?? null;
    }

    /**
     * @return array<int, User>
     */
    private function usersById(): array
    {
        if ($this->usersById !== null) {
            return $this->usersById;
        }

        $this->usersById = User::query()->get()->mapWithKeys(
            static fn (User $user): array => [$user->id => $user]
        )->all();

        return $this->usersById;
    }

    /**
     * @return array<string, User>
     */
    private function usersByEmail(): array
    {
        if ($this->usersByEmail !== null) {
            return $this->usersByEmail;
        }

        $this->usersByEmail = User::query()->get()->reduce(function (array $carry, User $user): array {
            if ($user->email) {
                $carry[strtolower($user->email)] = $user;
            }

            return $carry;
        }, []);

        return $this->usersByEmail;
    }

    /**
     * @return array<string, User>
     */
    private function usersByUsername(): array
    {
        if ($this->usersByUsername !== null) {
            return $this->usersByUsername;
        }

        $this->usersByUsername = User::query()->get()->reduce(function (array $carry, User $user): array {
            if ($user->username) {
                $carry[strtolower($user->username)] = $user;
            }

            return $carry;
        }, []);

        return $this->usersByUsername;
    }

    /**
     * @return array<string, User>
     */
    private function supportUsersByName(): array
    {
        if ($this->supportUsersByName !== null) {
            return $this->supportUsersByName;
        }

        $groupedUsers = User::query()
            ->whereIn('role', User::TICKET_CONSOLE_ROLES)
            ->get()
            ->groupBy(fn (User $user): ?string => $this->normalizeLookupKey($user->name));

        $this->supportUsersByName = $groupedUsers
            ->filter(static fn (Collection $users, ?string $key): bool => $key !== null && $users->count() === 1)
            ->map(fn (Collection $users): User => $users->first())
            ->all();

        return $this->supportUsersByName;
    }

    /**
     * @return array<int, Category>
     */
    private function categoriesById(): array
    {
        if ($this->categoriesById !== null) {
            return $this->categoriesById;
        }

        $this->categoriesById = Category::query()->get()->mapWithKeys(
            static fn (Category $category): array => [$category->id => $category]
        )->all();

        return $this->categoriesById;
    }

    /**
     * @return array<string, Category>
     */
    private function categoriesByName(): array
    {
        if ($this->categoriesByName !== null) {
            return $this->categoriesByName;
        }

        $this->categoriesByName = Category::query()->get()->reduce(function (array $carry, Category $category): array {
            $carry[strtolower($category->name)] = $category;

            return $carry;
        }, []);

        return $this->categoriesByName;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeLookupKey(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        return strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /**
     * @return array{
     *     name: string,
     *     contact_number: string,
     *     email: string,
     *     province: string,
     *     municipality: string
     * }
     */
    private function extractRequesterSnapshot(?string $requestorDetails, User $fallbackRequester): array
    {
        $details = $this->nullableString($requestorDetails);
        $snapshot = [
            'name' => null,
            'contact_number' => null,
            'email' => null,
            'province' => null,
            'municipality' => null,
        ];

        if ($details !== null) {
            $segments = preg_split('/[\r\n;]+/', $details) ?: [];

            foreach ($segments as $segment) {
                $segment = $this->nullableString($segment);
                if ($segment === null) {
                    continue;
                }

                if (preg_match('/^(name|requester|requestor)\s*[:\-]\s*(.+)$/i', $segment, $matches)) {
                    $snapshot['name'] = $this->nullableString($matches[2]);

                    continue;
                }

                if (preg_match('/^(contact(?:\s+number)?|contact\s+no\.?|phone|mobile)\s*[:\-]\s*(.+)$/i', $segment, $matches)) {
                    $snapshot['contact_number'] = $this->nullableString($matches[2]);

                    continue;
                }

                if (preg_match('/^email\s*[:\-]\s*(.+)$/i', $segment, $matches)) {
                    $snapshot['email'] = $this->nullableString($matches[1]);

                    continue;
                }

                if (preg_match('/^province\s*[:\-]\s*(.+)$/i', $segment, $matches)) {
                    $snapshot['province'] = $this->nullableString($matches[1]);

                    continue;
                }

                if (preg_match('/^(municipality|city)\s*[:\-]\s*(.+)$/i', $segment, $matches)) {
                    $snapshot['municipality'] = $this->nullableString($matches[2]);
                }
            }

            if ($snapshot['email'] === null && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $details, $matches)) {
                $snapshot['email'] = $this->nullableString($matches[0]);
            }

            if ($snapshot['contact_number'] === null && preg_match('/(?:\+?\d[\d\s\-]{8,}\d)/', $details, $matches)) {
                $snapshot['contact_number'] = $this->nullableString($matches[0]);
            }
        }

        return [
            'name' => $snapshot['name'] ?? (string) ($fallbackRequester->name ?: 'Unknown Requester'),
            'contact_number' => $snapshot['contact_number'] ?? (string) ($fallbackRequester->phone ?: 'N/A'),
            'email' => $snapshot['email'] ?? (string) ($fallbackRequester->email ?: 'unknown@local.invalid'),
            'province' => $snapshot['province'] ?? 'Unspecified',
            'municipality' => $snapshot['municipality'] ?? 'Unspecified',
        ];
    }
}
