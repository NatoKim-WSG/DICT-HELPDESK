<?php

namespace App\Console\Commands;

use App\Services\SystemLogService;
use App\Services\TicketImport\LegacyTicketXlsxImporter;
use Illuminate\Console\Command;

class ImportLegacyTicketXlsx extends Command
{
    protected $signature = 'tickets:import-xlsx
        {paths* : One or more XLSX files. Relative paths resolve from storage/app/private/imports.}
        {--default-user= : Default requester user id, email, or username}
        {--default-category=Other : Default category id or name}
        {--default-priority=medium : Default priority for imported tickets}
        {--default-status=open : Default status for unresolved rows}
        {--source-timezone= : Source timezone for date/time values without offsets}
        {--dry-run : Validate workbook rows without writing tickets}
        {--update-existing : Update existing rows matched by subject + created_at}';

    protected $description = 'Import legacy helpdesk tracker XLSX files while preserving source date and time values.';

    public function handle(LegacyTicketXlsxImporter $importer, SystemLogService $systemLogs): int
    {
        try {
            $summary = $importer->import((array) $this->argument('paths'), [
                'default_user' => $this->option('default-user'),
                'default_category' => $this->option('default-category'),
                'default_priority' => $this->option('default-priority'),
                'default_status' => $this->option('default-status'),
                'source_timezone' => $this->option('source-timezone'),
                'dry_run' => (bool) $this->option('dry-run'),
                'update_existing' => (bool) $this->option('update-existing'),
            ]);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($summary['paths'] as $path) {
            $this->line('Source: '.$path);
        }

        if ($summary['dry_run']) {
            $this->info(sprintf(
                'Dry run complete. files=%d validated=%d rows=%d',
                $summary['files'],
                $summary['validated'],
                $summary['rows'],
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Import complete. files=%d created=%d updated=%d skipped=%d rows=%d',
            $summary['files'],
            $summary['imported'],
            $summary['updated'],
            $summary['skipped'],
            $summary['rows'],
        ));

        $systemLogs->record(
            'ticket.imported_legacy_xlsx_batch',
            'Imported legacy tickets from XLSX tracker files.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'paths' => $summary['paths'],
                    'files' => $summary['files'],
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
