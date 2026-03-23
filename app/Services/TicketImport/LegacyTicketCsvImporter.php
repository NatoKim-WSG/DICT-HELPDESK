<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

class LegacyTicketCsvImporter
{
    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'ticket_number' => ['ticket_number', 'ticket number', 'ticket_no', 'ticket no', 'ticket no.', 'ticketnum'],
        'subject' => ['subject', 'title', 'summary'],
        'description' => ['description', 'details', 'body', 'message', 'issue', 'concern'],
        'priority' => ['priority', 'severity'],
        'status' => ['status', 'state'],
        'category_id' => ['category_id'],
        'category' => ['category', 'category_name', 'category name', 'type'],
        'user_id' => ['user_id', 'requester_user_id', 'client_user_id'],
        'user_email' => ['user_email', 'requester_user_email', 'client_user_email', 'account_email'],
        'user_username' => ['user_username', 'requester_username', 'client_username', 'account_username'],
        'assigned_to_id' => ['assigned_to_id', 'assignee_id', 'technician_id'],
        'assigned_to_email' => ['assigned_to_email', 'assignee_email', 'technician_email'],
        'assigned_to_username' => ['assigned_to_username', 'assignee_username', 'technician_username'],
        'name' => ['name', 'requester_name', 'client_name'],
        'contact_number' => ['contact_number', 'contact number', 'contact no', 'contact no.', 'contact', 'phone', 'phone_number', 'mobile'],
        'email' => ['email', 'requester_email', 'client_email'],
        'province' => ['province', 'state_province'],
        'municipality' => ['municipality', 'city', 'municipal_city'],
        'created_at' => ['created_at', 'created', 'original_created_at', 'date_created', 'opened_at', 'reported_at', 'requested_at'],
        'updated_at' => ['updated_at', 'updated', 'modified_at', 'last_updated_at'],
        'assigned_at' => ['assigned_at'],
        'resolved_at' => ['resolved_at', 'date_resolved'],
        'closed_at' => ['closed_at', 'date_closed'],
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_ALIASES = [
        'low' => 'low',
        'medium' => 'medium',
        'normal' => 'medium',
        'high' => 'high',
        'urgent' => 'urgent',
        'critical' => 'urgent',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_ALIASES = [
        'new' => 'open',
        'open' => 'open',
        'in progress' => 'in_progress',
        'in_progress' => 'in_progress',
        'ongoing' => 'in_progress',
        'pending' => 'pending',
        'resolved' => 'resolved',
        'done' => 'resolved',
        'closed' => 'closed',
    ];

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
     * @var array<int, Category>|null
     */
    private ?array $categoriesById = null;

    /**
     * @var array<string, Category>|null
     */
    private ?array $categoriesByName = null;

    /**
     * @param  array{
     *     default_user?: string|int|null,
     *     default_category?: string|int|null,
     *     default_priority?: string|null,
     *     default_status?: string|null,
     *     source_timezone?: string|null,
     *     delimiter?: string|null,
     *     dry_run?: bool,
     *     update_existing?: bool
     * }  $options
     * @return array{
     *     path: string,
     *     rows: int,
     *     validated: int,
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     dry_run: bool
     * }
     */
    public function import(string $path, array $options = []): array
    {
        $settings = $this->normalizeOptions($options);
        $resolvedPath = $this->resolvePath($path);
        $rows = $this->readRows($resolvedPath, $settings['delimiter']);
        $preparedRows = [];

        foreach ($rows as $row) {
            $preparedRows[] = $this->prepareRow($row['line'], $row['data'], $settings);
        }

        $summary = [
            'path' => $resolvedPath,
            'rows' => count($rows),
            'validated' => count($preparedRows),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $settings['dry_run'],
        ];

        if ($settings['dry_run']) {
            return $summary;
        }

        DB::transaction(function () use ($preparedRows, $settings, &$summary): void {
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
     *     delimiter: string,
     *     dry_run: bool,
     *     update_existing: bool
     * }  $settings
     * @param  array<string, string|null>  $row
     * @return array{
     *     line: int,
     *     ticket_number: string|null,
     *     attributes: array<string, mixed>
     * }
     */
    private function prepareRow(int $line, array $row, array $settings): array
    {
        $subject = $this->requiredString($line, $row, 'subject');
        $createdAt = $this->parseDateTime(
            $line,
            $this->requiredString($line, $row, 'created_at'),
            'created_at',
            $settings['source_timezone']
        );
        $updatedAt = $this->optionalDateTime($line, $row['updated_at'] ?? null, 'updated_at', $settings['source_timezone'])
            ?? $createdAt->copy();

        if ($updatedAt->lt($createdAt)) {
            $updatedAt = $createdAt->copy();
        }

        $requester = $this->resolveRequester($line, $row, $settings['default_user']);
        $category = $this->resolveCategory($line, $row, $settings['default_category']);
        $assignedUser = $this->resolveOptionalUser(
            $line,
            $row['assigned_to_id'] ?? null,
            $row['assigned_to_email'] ?? null,
            $row['assigned_to_username'] ?? null,
            'assignee'
        );

        $priority = $this->normalizePriority($line, $row['priority'] ?? null, $settings['default_priority']);
        $status = $this->normalizeStatus($line, $row['status'] ?? null, $settings['default_status']);
        $ticketNumber = $this->nullableString($row['ticket_number'] ?? null);
        $description = $this->nullableString($row['description'] ?? null) ?? $subject;

        return [
            'line' => $line,
            'ticket_number' => $ticketNumber,
            'attributes' => [
                'ticket_number' => $ticketNumber,
                'name' => $this->nullableString($row['name'] ?? null) ?? $requester->name ?? 'Unknown Requester',
                'contact_number' => $this->nullableString($row['contact_number'] ?? null) ?? $requester->phone ?? 'N/A',
                'email' => $this->nullableString($row['email'] ?? null) ?? $requester->email ?? 'unknown@local.invalid',
                'province' => $this->nullableString($row['province'] ?? null) ?? 'Unspecified',
                'municipality' => $this->nullableString($row['municipality'] ?? null) ?? 'Unspecified',
                'subject' => $subject,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'user_id' => $requester->id,
                'assigned_to' => $assignedUser?->id,
                'assigned_at' => $this->optionalDateTime($line, $row['assigned_at'] ?? null, 'assigned_at', $settings['source_timezone']),
                'category_id' => $category->id,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'resolved_at' => $this->optionalDateTime($line, $row['resolved_at'] ?? null, 'resolved_at', $settings['source_timezone']),
                'closed_at' => $this->optionalDateTime($line, $row['closed_at'] ?? null, 'closed_at', $settings['source_timezone']),
            ],
        ];
    }

    /**
     * @return array{
     *     default_user: string|null,
     *     default_category: string|null,
     *     default_priority: string,
     *     default_status: string,
     *     source_timezone: string,
     *     delimiter: string,
     *     dry_run: bool,
     *     update_existing: bool
     * }
     */
    private function normalizeOptions(array $options): array
    {
        $defaultPriority = $this->normalizePriority(1, $options['default_priority'] ?? 'medium', 'medium');
        $defaultStatus = $this->normalizeStatus(1, $options['default_status'] ?? 'open', 'open');
        $delimiter = (string) ($options['delimiter'] ?? ',');

        if ($delimiter === '') {
            throw new InvalidArgumentException('CSV delimiter cannot be empty.');
        }

        return [
            'default_user' => $this->nullableString($options['default_user'] ?? null),
            'default_category' => $this->nullableString($options['default_category'] ?? null) ?? 'Other',
            'default_priority' => $defaultPriority,
            'default_status' => $defaultStatus,
            'source_timezone' => $this->nullableString($options['source_timezone'] ?? null)
                ?? (string) config('helpdesk.ticket_import_timezone', config('app.timezone', 'UTC')),
            'delimiter' => mb_substr($delimiter, 0, 1),
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

    /**
     * @return list<array{line: int, data: array<string, string|null>}>
     */
    private function readRows(string $path, string $delimiter): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        $rawHeaders = $file->fgetcsv();
        if (! is_array($rawHeaders) || $rawHeaders === [null] || $rawHeaders === []) {
            throw new RuntimeException('Import file is missing a header row.');
        }

        $headers = [];
        foreach ($rawHeaders as $index => $rawHeader) {
            $normalizedHeader = $this->normalizeHeader((string) $rawHeader);
            if ($normalizedHeader === '') {
                continue;
            }

            $canonicalHeader = $this->canonicalHeader($normalizedHeader);
            if ($canonicalHeader === null) {
                continue;
            }

            if (in_array($canonicalHeader, $headers, true)) {
                throw new RuntimeException(sprintf('Duplicate import column detected: %s', $canonicalHeader));
            }

            $headers[$index] = $canonicalHeader;
        }

        if (! in_array('subject', $headers, true)) {
            throw new RuntimeException('Import file must include a subject column.');
        }

        if (! in_array('created_at', $headers, true)) {
            throw new RuntimeException('Import file must include a created_at column so historical ticket dates are preserved.');
        }

        $rows = [];
        $lineNumber = 1;

        while (! $file->eof()) {
            $lineNumber++;
            $rawRow = $file->fgetcsv();

            if (! is_array($rawRow) || $this->rowIsBlank($rawRow)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($rawRow[$index]) ? trim((string) $rawRow[$index]) : null;
            }

            $rows[] = [
                'line' => $lineNumber,
                'data' => $row,
            ];
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim($value))), '_');
    }

    private function canonicalHeader(string $normalizedHeader): ?string
    {
        foreach (self::HEADER_ALIASES as $canonicalHeader => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedHeader === $this->normalizeHeader($alias)) {
                    return $canonicalHeader;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function requiredString(int $line, array $row, string $field): string
    {
        $value = $this->nullableString($row[$field] ?? null);
        if ($value === null) {
            throw new RuntimeException(sprintf('Row %d is missing a %s value.', $line, $field));
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePriority(int $line, mixed $value, string $fallback): string
    {
        $rawValue = $this->nullableString($value) ?? $fallback;
        $normalizedValue = strtolower(str_replace('_', ' ', $rawValue));
        $resolvedValue = self::PRIORITY_ALIASES[$normalizedValue] ?? null;

        if ($resolvedValue === null) {
            throw new RuntimeException(sprintf('Row %d has an unsupported priority: %s', $line, $rawValue));
        }

        return $resolvedValue;
    }

    private function normalizeStatus(int $line, mixed $value, string $fallback): string
    {
        $rawValue = $this->nullableString($value) ?? $fallback;
        $normalizedValue = strtolower(str_replace('_', ' ', $rawValue));
        $resolvedValue = self::STATUS_ALIASES[$normalizedValue] ?? null;

        if ($resolvedValue === null) {
            throw new RuntimeException(sprintf('Row %d has an unsupported status: %s', $line, $rawValue));
        }

        return $resolvedValue;
    }

    private function parseDateTime(int $line, string $value, string $field, string $sourceTimezone): Carbon
    {
        try {
            return Carbon::parse($value, $sourceTimezone)
                ->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (\Throwable $exception) {
            throw new RuntimeException(sprintf('Row %d has an invalid %s value: %s', $line, $field, $value), 0, $exception);
        }
    }

    private function optionalDateTime(int $line, mixed $value, string $field, string $sourceTimezone): ?Carbon
    {
        $normalizedValue = $this->nullableString($value);
        if ($normalizedValue === null) {
            return null;
        }

        return $this->parseDateTime($line, $normalizedValue, $field, $sourceTimezone);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function resolveRequester(int $line, array $row, ?string $defaultReference): User
    {
        $requester = $this->resolveOptionalUser(
            $line,
            $row['user_id'] ?? null,
            $row['user_email'] ?? null,
            $row['user_username'] ?? null,
            'requester'
        );

        if ($requester instanceof User) {
            return $requester;
        }

        if ($defaultReference !== null) {
            return $this->resolveUserReference($defaultReference, $line, 'requester');
        }

        throw new RuntimeException(sprintf(
            'Row %d could not resolve a requester. Provide user_id, user_email, or user_username columns, or pass --default-user.',
            $line
        ));
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function resolveCategory(int $line, array $row, ?string $defaultReference): Category
    {
        $categoryId = $this->nullableString($row['category_id'] ?? null);
        if ($categoryId !== null) {
            $category = $this->categoriesById()[(int) $categoryId] ?? null;
            if ($category instanceof Category) {
                return $category;
            }
        }

        $categoryName = $this->nullableString($row['category'] ?? null) ?? $defaultReference;
        if ($categoryName === null) {
            throw new RuntimeException(sprintf(
                'Row %d could not resolve a category. Provide category/category_id columns, or pass --default-category.',
                $line
            ));
        }

        if (ctype_digit($categoryName)) {
            $category = $this->categoriesById()[(int) $categoryName] ?? null;
            if ($category instanceof Category) {
                return $category;
            }
        }

        $category = $this->categoriesByName()[strtolower($categoryName)] ?? null;
        if ($category instanceof Category) {
            return $category;
        }

        throw new RuntimeException(sprintf('Row %d references an unknown category: %s', $line, $categoryName));
    }

    private function resolveOptionalUser(
        int $line,
        mixed $id,
        mixed $email,
        mixed $username,
        string $label
    ): ?User {
        $idValue = $this->nullableString($id);
        if ($idValue !== null) {
            $user = $this->usersById()[(int) $idValue] ?? null;
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('Row %d references an unknown %s user_id: %s', $line, $label, $idValue));
        }

        $emailValue = $this->nullableString($email);
        if ($emailValue !== null) {
            $user = $this->usersByEmail()[strtolower($emailValue)] ?? null;
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('Row %d references an unknown %s user_email: %s', $line, $label, $emailValue));
        }

        $usernameValue = $this->nullableString($username);
        if ($usernameValue !== null) {
            $user = $this->usersByUsername()[strtolower($usernameValue)] ?? null;
            if ($user instanceof User) {
                return $user;
            }

            throw new RuntimeException(sprintf('Row %d references an unknown %s user_username: %s', $line, $label, $usernameValue));
        }

        return null;
    }

    private function resolveUserReference(string $reference, int $line, string $label): User
    {
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

        throw new RuntimeException(sprintf('Row %d references an unknown %s: %s', $line, $label, $reference));
    }

    /**
     * @return array<int, User>
     */
    private function usersById(): array
    {
        if ($this->usersById === null) {
            $this->usersById = User::query()->get()->keyBy('id')->all();
        }

        return $this->usersById;
    }

    /**
     * @return array<string, User>
     */
    private function usersByEmail(): array
    {
        if ($this->usersByEmail === null) {
            $users = User::query()->whereNotNull('email')->get();
            $this->usersByEmail = [];

            foreach ($users as $user) {
                $email = strtolower((string) $user->email);
                if ($email !== '') {
                    $this->usersByEmail[$email] = $user;
                }
            }
        }

        return $this->usersByEmail;
    }

    /**
     * @return array<string, User>
     */
    private function usersByUsername(): array
    {
        if ($this->usersByUsername === null) {
            $users = User::query()->whereNotNull('username')->get();
            $this->usersByUsername = [];

            foreach ($users as $user) {
                $username = strtolower((string) $user->username);
                if ($username !== '') {
                    $this->usersByUsername[$username] = $user;
                }
            }
        }

        return $this->usersByUsername;
    }

    /**
     * @return array<int, Category>
     */
    private function categoriesById(): array
    {
        if ($this->categoriesById === null) {
            $this->categoriesById = Category::query()->get()->keyBy('id')->all();
        }

        return $this->categoriesById;
    }

    /**
     * @return array<string, Category>
     */
    private function categoriesByName(): array
    {
        if ($this->categoriesByName === null) {
            $categories = Category::query()->get();
            $this->categoriesByName = [];

            foreach ($categories as $category) {
                $this->categoriesByName[strtolower((string) $category->name)] = $category;
            }
        }

        return $this->categoriesByName;
    }
}
