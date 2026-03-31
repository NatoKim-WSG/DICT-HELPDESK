<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MonthlyReportDatasetService
{
    private array $openTicketCountCache = [];

    public function build(Builder $scopedTickets): array
    {
        $startMonth = Carbon::now()->startOfMonth()->subMonths(11);
        $endMonth = Carbon::now()->startOfMonth();
        $reportingRangeStart = $startMonth->copy()->startOfMonth();
        $reportingRangeEnd = $endMonth->copy()->endOfMonth()->endOfDay();
        $monthKeyExpressionForCreatedAt = $this->monthKeyExpression('created_at', $scopedTickets);
        $monthKeyExpressionForResolvedAt = $this->monthKeyExpression('resolved_at', $scopedTickets);
        $monthKeyExpressionForClosedAt = $this->monthKeyExpression('closed_at', $scopedTickets);

        $receivedByMonth = (clone $scopedTickets)
            ->whereBetween('created_at', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("{$monthKeyExpressionForCreatedAt} as month_key, COUNT(*) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');
        $completedByCreatedMonth = (clone $scopedTickets)
            ->whereBetween('created_at', [$reportingRangeStart, $reportingRangeEnd])
            ->where(function ($query) {
                $query->whereNotNull('resolved_at')
                    ->orWhereNotNull('closed_at')
                    ->orWhereIn('status', Ticket::CLOSED_STATUSES);
            })
            ->selectRaw("{$monthKeyExpressionForCreatedAt} as month_key, COUNT(DISTINCT id) as count")
            ->groupBy('month_key')
            ->pluck('count', 'month_key');

        $resolvedSubQuery = (clone $scopedTickets)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("id as ticket_id, {$monthKeyExpressionForResolvedAt} as month_key");
        $closedSubQuery = (clone $scopedTickets)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$reportingRangeStart, $reportingRangeEnd])
            ->selectRaw("id as ticket_id, {$monthKeyExpressionForClosedAt} as month_key");

        $completedInPeriodByMonth = DB::query()
            ->fromSub($resolvedSubQuery->unionAll($closedSubQuery), 'completion_points')
            ->selectRaw('month_key, COUNT(DISTINCT ticket_id) as count')
            ->groupBy('month_key')
            ->pluck('count', 'month_key');

        $monthDefinitions = collect();
        for ($cursor = $startMonth->copy(); $cursor->lte($endMonth); $cursor->addMonth()) {
            $monthDefinitions->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $cursor->copy()->endOfMonth(),
            ]);
        }

        $rangeBacklogCutoff = $reportingRangeStart->copy()->subSecond();
        $openBeforeReportingRange = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $rangeBacklogCutoff);
        $runningOpenCount = $openBeforeReportingRange;

        $reportRows = $monthDefinitions->map(function (array $row) use (
            &$runningOpenCount,
            $receivedByMonth,
            $completedByCreatedMonth,
            $completedInPeriodByMonth
        ) {
            $monthKey = (string) $row['key'];
            $receivedCount = (int) ($receivedByMonth[$monthKey] ?? 0);
            $completedInPeriodCount = (int) ($completedInPeriodByMonth[$monthKey] ?? 0);
            $completedFromCreatedCount = (int) ($completedByCreatedMonth[$monthKey] ?? 0);
            $runningOpenCount += $receivedCount - $completedInPeriodCount;
            if ($runningOpenCount < 0) {
                $runningOpenCount = 0;
            }

            return [
                'month_key' => $monthKey,
                'month_label' => (string) $row['label'],
                'month_start' => $row['start']->toDateString(),
                'month_end' => $row['end']->toDateString(),
                'received' => $receivedCount,
                'resolved' => $completedFromCreatedCount,
                'completed_in_period' => $completedInPeriodCount,
                'open_end_of_month' => $runningOpenCount,
                'resolution_rate' => $receivedCount > 0
                    ? round(($completedFromCreatedCount / $receivedCount) * 100, 1)
                    : 0.0,
            ];
        });

        $graphPoints = $reportRows->map(fn (array $row) => [
            'key' => $row['month_key'],
            'month_label' => $row['month_label'],
            'label' => Carbon::createFromFormat('Y-m', $row['month_key'])->format('M Y'),
            'received' => $row['received'],
            'resolved' => $row['resolved'],
            'completed_in_period' => $row['completed_in_period'],
            'resolution_rate' => $row['resolution_rate'],
        ]);

        return [$reportRows, $graphPoints];
    }

    public function countOpenTicketsAtCutoff(Builder $scopedTickets, Carbon $cutoff): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|'.$cutoff->toIso8601String();
        if (array_key_exists($cacheKey, $this->openTicketCountCache)) {
            return $this->openTicketCountCache[$cacheKey];
        }

        $count = (clone $scopedTickets)
            ->where('created_at', '<=', $cutoff)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('resolved_at')
                    ->orWhere('resolved_at', '>', $cutoff);
            })
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('closed_at')
                    ->orWhere('closed_at', '>', $cutoff);
            })
            ->count();

        $this->openTicketCountCache[$cacheKey] = (int) $count;

        return (int) $count;
    }

    private function queryScopeSignature(Builder $scopedTickets): string
    {
        return sha1($scopedTickets->toSql().'|'.json_encode($scopedTickets->getBindings()));
    }

    private function monthKeyExpression(string $column, Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getQuery()->getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
