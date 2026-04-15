<?php

namespace App\Services\TicketImport;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Traversable;

class LegacyTicketXlsxImporter
{
    private const PERSIST_BATCH_SIZE = 250;

    public function __construct(
        private readonly HelpdeskTrackerXlsxParser $parser,
        private readonly HelpdeskTrackerDescriptionFormatter $formatter,
        private readonly ImportedTicketService $importedTickets,
        private readonly TicketImportPersistenceService $persistence,
        private readonly ImportPathResolver $pathResolver,
        private readonly ImportEntityLookupCache $lookupCache,
        private readonly TicketImportReferenceResolver $references,
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
        $resolvedPaths = array_map(fn (string $path) => $this->pathResolver->resolve($path), $paths);
        $preparedRows = new PreparedImportRowSpool;

        $summary = [
            'paths' => $resolvedPaths,
            'files' => count($resolvedPaths),
            'rows' => 0,
            'validated' => 0,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $settings['dry_run'],
        ];

        try {
            foreach ($this->iteratePreparedRows($resolvedPaths, $settings) as $preparedRow) {
                $preparedRows->append($preparedRow);
                $summary['rows']++;
                $summary['validated']++;
            }

            if ($settings['dry_run']) {
                return $summary;
            }

            $batch = [];
            foreach ($preparedRows->rows() as $preparedRow) {
                $batch[] = $preparedRow;

                if (count($batch) < self::PERSIST_BATCH_SIZE) {
                    continue;
                }

                $summary = $this->mergePersistenceSummary(
                    $summary,
                    $this->persistence->persist(
                        $batch,
                        $settings['update_existing'],
                        fn (array $rows): array => $this->loadExistingTicketsByMatchKey($rows)
                    )
                );
                $batch = [];
            }

            if ($batch !== []) {
                $summary = $this->mergePersistenceSummary(
                    $summary,
                    $this->persistence->persist(
                        $batch,
                        $settings['update_existing'],
                        fn (array $rows): array => $this->loadExistingTicketsByMatchKey($rows)
                    )
                );
            }

            return $summary;
        } finally {
            unset($preparedRows);
        }
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
     *     match_key: string,
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
            'match_key' => $this->buildMatchKey($subject, $createdAt),
            'match_subject' => $subject,
            'match_created_at' => $createdAt,
            'attributes' => $this->importedTickets->applyImportMetadata([
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
            ]),
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
        $matchSubjects = [];
        $matchCreatedAts = [];
        $existingTicketsByKey = [];

        foreach ($preparedRows as $preparedRow) {
            if ($preparedRow['ticket_number'] !== null) {
                continue;
            }

            $matchKey = $this->buildMatchKey($preparedRow['match_subject'], $preparedRow['match_created_at']);

            if (array_key_exists($matchKey, $existingTicketsByKey)) {
                continue;
            }

            $existingTicketsByKey[$matchKey] = collect();
            $matchSubjects[] = $preparedRow['match_subject'];
            $matchCreatedAts[] = $preparedRow['match_created_at']->copy();
        }

        if ($existingTicketsByKey === []) {
            return [];
        }

        $existingTickets = Ticket::query()
            ->whereIn('subject', array_values(array_unique($matchSubjects)))
            ->whereIn('created_at', array_values(array_unique(array_map(
                fn (Carbon $createdAt) => $createdAt->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'),
                $matchCreatedAts
            ))))
            ->orderBy('id')
            ->get();

        foreach ($existingTickets as $ticket) {
            $matchKey = $this->buildMatchKey((string) $ticket->subject, $ticket->created_at->copy());
            if (! array_key_exists($matchKey, $existingTicketsByKey)) {
                continue;
            }

            $existingTicketsByKey[$matchKey]->push($ticket);
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
        $defaultPriority = Ticket::normalizePriorityValue($this->nullableString($options['default_priority'] ?? null) ?? 'severity_2');
        $defaultStatus = $this->nullableString($options['default_status'] ?? null) ?? 'open';

        if ($defaultPriority === null || ! in_array($defaultPriority, Ticket::PRIORITIES, true)) {
            throw new InvalidArgumentException('Unsupported default priority: '.($options['default_priority'] ?? ''));
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

    private function resolveUserReference(?string $reference, int $row, string $context): User
    {
        return $this->references->resolveUserReference($reference, $row, $context, 'Spreadsheet row');
    }

    private function resolveCategoryReference(?string $reference, int $row): Category
    {
        return $this->references->resolveCategoryReference($reference, $row, 'Spreadsheet row');
    }

    private function resolveOptionalSupportUserByDisplayName(?string $displayName): ?User
    {
        $displayName = $this->references->normalizeLookupKey($displayName);
        if ($displayName === null) {
            return null;
        }

        return $this->lookupCache->supportUserByDisplayName($displayName);
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
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

    /**
     * @param  list<string>  $resolvedPaths
     * @param  array{
     *     default_user: string|null,
     *     default_category: string|null,
     *     default_priority: string,
     *     default_status: string,
     *     source_timezone: string,
     *     dry_run: bool,
     *     update_existing: bool
     * }  $settings
     * @return Traversable<int, array{
     *     ticket_number: string|null,
     *     match_key: string,
     *     match_subject: string,
     *     match_created_at: Carbon,
     *     attributes: array<string, mixed>
     * }>
     */
    private function iteratePreparedRows(array $resolvedPaths, array $settings): Traversable
    {
        foreach ($resolvedPaths as $resolvedPath) {
            foreach ($this->parser->parse($resolvedPath) as $row) {
                yield $this->prepareRow($resolvedPath, $row, $settings);
            }
        }
    }

    /**
     * @param  array{
     *     paths: list<string>,
     *     files: int,
     *     rows: int,
     *     validated: int,
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     dry_run: bool
     * }  $summary
     * @param  array{imported: int, updated: int, skipped: int}  $persistenceSummary
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
    private function mergePersistenceSummary(array $summary, array $persistenceSummary): array
    {
        $summary['imported'] += $persistenceSummary['imported'];
        $summary['updated'] += $persistenceSummary['updated'];
        $summary['skipped'] += $persistenceSummary['skipped'];

        return $summary;
    }
}
