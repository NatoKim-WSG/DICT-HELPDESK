<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskOpsStatus extends Command
{
    protected $signature = 'helpdesk:ops-status {--fail-on-warning : Exit with a failure code when warnings are present}';

    protected $description = 'Show production-facing runtime and queue checks for the helpdesk app.';

    public function handle(): int
    {
        $warnings = [];
        $phpRequirement = $this->composerPhpRequirement();
        $minimumPhpVersion = $this->minimumPhpVersion($phpRequirement);
        $phpSupported = $minimumPhpVersion === null || version_compare(PHP_VERSION, $minimumPhpVersion, '>=');
        $queueConnection = (string) config('queue.default', 'sync');
        $queueWorkerRequired = $queueConnection !== 'sync';
        $queueWorkerRunning = $this->queueWorkerRunningStatus($queueConnection);
        $pendingJobs = $this->countTableRows('jobs');
        $failedJobs = $this->countTableRows('failed_jobs');

        if (! $phpSupported && $phpRequirement !== null) {
            $warnings[] = sprintf(
                'PHP %s does not satisfy composer.json require.php (%s).',
                PHP_VERSION,
                $phpRequirement
            );
        }

        if ($queueWorkerRequired && $queueWorkerRunning === false) {
            $warnings[] = sprintf(
                'Queue connection "%s" requires a running queue worker for queued mail and jobs.',
                $queueConnection
            );
        }

        if (is_int($pendingJobs) && $pendingJobs > 0) {
            $warnings[] = sprintf('There are %d pending jobs in the queue.', $pendingJobs);
        }

        if (is_int($failedJobs) && $failedJobs > 0) {
            $warnings[] = sprintf('There are %d failed jobs that need attention.', $failedJobs);
        }

        $this->info('Helpdesk operations status');
        $this->line('Environment: '.app()->environment());
        $this->line('App URL: '.(string) config('app.url'));
        $this->line('PHP version: '.PHP_VERSION);
        $this->line('Composer PHP requirement: '.($phpRequirement ?? 'not declared'));
        $this->line('PHP platform status: '.($phpSupported ? 'supported' : 'unsupported'));
        $this->line('Queue connection: '.$queueConnection);
        $this->line('Queue worker required: '.($queueWorkerRequired ? 'yes' : 'no'));
        $this->line('Queue worker status: '.$this->formatQueueWorkerStatus($queueWorkerRequired, $queueWorkerRunning));
        $this->line('Pending jobs: '.$this->formatCount($pendingJobs));
        $this->line('Failed jobs: '.$this->formatCount($failedJobs));
        $this->line('Mail mailer: '.(string) config('mail.default', 'log'));
        $this->line('Scheduled alert command: tickets:send-alert-emails (every 5 minutes)');

        if ($warnings === []) {
            $this->info('Warnings: none');

            return self::SUCCESS;
        }

        $this->warn('Warnings:');
        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        return $this->option('fail-on-warning')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function composerPhpRequirement(): ?string
    {
        $composerJsonPath = base_path('composer.json');
        if (! is_file($composerJsonPath)) {
            return null;
        }

        $composerJson = json_decode((string) file_get_contents($composerJsonPath), true);
        if (! is_array($composerJson)) {
            return null;
        }

        $requirement = $composerJson['require']['php'] ?? null;

        return is_string($requirement) && trim($requirement) !== ''
            ? $requirement
            : null;
    }

    private function minimumPhpVersion(?string $requirement): ?string
    {
        if (! is_string($requirement) || trim($requirement) === '') {
            return null;
        }

        return preg_match('/(\d+\.\d+(?:\.\d+)?)/', $requirement, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function countTableRows(string $table): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->count();
    }

    private function queueWorkerRunningStatus(string $queueConnection): ?bool
    {
        if ($queueConnection === 'sync') {
            return true;
        }

        $configuredStatus = config('helpdesk.ops.queue_worker_running');
        if (is_bool($configuredStatus)) {
            return $configuredStatus;
        }

        if (DIRECTORY_SEPARATOR === '\\' || ! function_exists('shell_exec')) {
            return null;
        }

        $disabledFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabledFunctions, true)) {
            return null;
        }

        $processList = shell_exec('ps -eo command= 2>/dev/null');
        if (! is_string($processList) || trim($processList) === '') {
            return null;
        }

        foreach (preg_split("/\r\n|\n|\r/", $processList) as $command) {
            $normalizedCommand = trim((string) $command);

            if (
                $normalizedCommand !== ''
                && (
                    str_contains($normalizedCommand, 'artisan queue:work')
                    || str_contains($normalizedCommand, 'artisan queue:listen')
                    || str_contains($normalizedCommand, 'artisan horizon')
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function formatCount(?int $count): string
    {
        return $count === null ? 'unavailable' : (string) $count;
    }

    private function formatQueueWorkerStatus(bool $queueWorkerRequired, ?bool $queueWorkerRunning): string
    {
        if (! $queueWorkerRequired) {
            return 'not required';
        }

        return match ($queueWorkerRunning) {
            true => 'running',
            false => 'not detected',
            default => 'unavailable',
        };
    }
}
