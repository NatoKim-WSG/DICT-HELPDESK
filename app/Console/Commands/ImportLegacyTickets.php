<?php

namespace App\Console\Commands;

use App\Services\SystemLogService;
use App\Services\TicketImport\LegacyTicketCsvImporter;
use Illuminate\Console\Command;

class ImportLegacyTickets extends Command
{
    protected $signature = 'tickets:import-csv
        {path : Path to the CSV file. Relative paths resolve from storage/app/private/imports.}
        {--default-user= : Default requester user id, email, or username for rows without user columns}
        {--default-category=Other : Default category id or name for rows without category columns}
        {--default-priority=medium : Default priority when the CSV does not include one}
        {--default-status=open : Default status when the CSV does not include one}
        {--source-timezone= : Source timezone for created_at/updated_at values without offsets}
        {--delimiter=, : CSV delimiter}
        {--dry-run : Validate the file without writing any tickets}
        {--update-existing : Update existing tickets when the CSV includes a matching ticket_number}';

    protected $description = 'Import legacy tickets from CSV while preserving historical created_at values.';

    public function handle(LegacyTicketCsvImporter $importer, SystemLogService $systemLogs): int
    {
        try {
            $summary = $importer->import((string) $this->argument('path'), [
                'default_user' => $this->option('default-user'),
                'default_category' => $this->option('default-category'),
                'default_priority' => $this->option('default-priority'),
                'default_status' => $this->option('default-status'),
                'source_timezone' => $this->option('source-timezone'),
                'delimiter' => $this->option('delimiter'),
                'dry_run' => (bool) $this->option('dry-run'),
                'update_existing' => (bool) $this->option('update-existing'),
            ]);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Source: '.$summary['path']);

        if ($summary['dry_run']) {
            $this->info(sprintf(
                'Dry run complete. validated=%d rows=%d',
                $summary['validated'],
                $summary['rows'],
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Import complete. created=%d updated=%d skipped=%d rows=%d',
            $summary['imported'],
            $summary['updated'],
            $summary['skipped'],
            $summary['rows'],
        ));

        $systemLogs->record(
            'ticket.imported_legacy_batch',
            'Imported legacy tickets from CSV.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'path' => $summary['path'],
                    'rows' => $summary['rows'],
                    'imported' => $summary['imported'],
                    'updated' => $summary['updated'],
                    'skipped' => $summary['skipped'],
                ],
            ]
        );

        return self::SUCCESS;
    }
}
