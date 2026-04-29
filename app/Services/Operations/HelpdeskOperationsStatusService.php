<?php

namespace App\Services\Operations;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskOperationsStatusService
{
    public function buildStatus(): array
    {
        $warnings = [];
        $phpRequirement = $this->composerPhpRequirement();
        $minimumPhpVersion = $this->minimumPhpVersion($phpRequirement);
        $phpSupported = $minimumPhpVersion === null || version_compare(PHP_VERSION, $minimumPhpVersion, '>=');
        $queueConnection = (string) config('queue.default', 'sync');
        $queueWorkerRequired = $queueConnection !== 'sync';
        $queueWorkerRunning = $this->queueWorkerRunningStatus($queueConnection);
        $databaseReachable = $this->databaseReachable();
        $pendingJobs = $databaseReachable ? $this->countTableRows('jobs') : null;
        $failedJobs = $databaseReachable ? $this->countTableRows('failed_jobs') : null;

        if (! $phpSupported && $phpRequirement !== null) {
            $warnings[] = sprintf(
                'PHP %s does not satisfy composer.json require.php (%s).',
                PHP_VERSION,
                $phpRequirement
            );
        }

        if (! $databaseReachable) {
            $warnings[] = 'Database connectivity check failed.';
        }

        if ($queueWorkerRequired && $queueWorkerRunning !== true) {
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

        $status = $databaseReachable && $phpSupported
            ? ($warnings === [] ? 'ok' : 'degraded')
            : 'failed';

        return [
            'status' => $status,
            'checked_at' => Carbon::now()->toIso8601String(),
            'environment' => app()->environment(),
            'app_url' => (string) config('app.url'),
            'php_version' => PHP_VERSION,
            'php_requirement' => $phpRequirement,
            'php_supported' => $phpSupported,
            'database_reachable' => $databaseReachable,
            'cache_store' => (string) config('cache.default', 'unknown'),
            'queue_connection' => $queueConnection,
            'queue_worker_required' => $queueWorkerRequired,
            'queue_worker_running' => $queueWorkerRunning,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'mail_mailer' => (string) config('mail.default', 'log'),
            'scheduled_alert_command' => 'tickets:send-alert-emails (every 5 minutes)',
            'warnings' => $warnings,
        ];
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

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
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
}
