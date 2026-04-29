<?php

namespace App\Http\Controllers;

use App\Services\Operations\HelpdeskOperationsStatusService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __invoke(HelpdeskOperationsStatusService $opsStatus): JsonResponse
    {
        $status = $opsStatus->buildStatus();

        return response()->json([
            'status' => $status['status'],
            'checked_at' => $status['checked_at'],
            'checks' => [
                'database' => $status['database_reachable'] ? 'ok' : 'failed',
                'php' => $status['php_supported'] ? 'ok' : 'failed',
                'queue' => $status['queue_worker_required'] && $status['queue_worker_running'] !== true
                    ? 'degraded'
                    : 'ok',
            ],
            'queue' => [
                'connection' => $status['queue_connection'],
                'worker_required' => $status['queue_worker_required'],
                'worker_running' => $status['queue_worker_running'],
                'pending_jobs' => $status['pending_jobs'],
                'failed_jobs' => $status['failed_jobs'],
            ],
            'warnings' => $status['warnings'],
        ], $status['status'] === 'failed' ? 503 : 200);
    }
}
