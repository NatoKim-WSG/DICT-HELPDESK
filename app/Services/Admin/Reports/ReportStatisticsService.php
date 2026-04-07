<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Services\Admin\ReportBreakdownService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportStatisticsService
{
    private array $resolvedTicketCountCache = [];

    private array $closedTicketCountCache = [];

    private array $statusBreakdownCache = [];

    private array $priorityBreakdownCache = [];

    private array $categoryBreakdownCache = [];

    private array $majorIssueSummaryCache = [];

    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
    ) {}

    public function buildKpiSummary(Builder $scopedTickets): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $closedStatusesSqlList = "'".implode("','", Ticket::CLOSED_STATUSES)."'";

        $summary = (clone $scopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$closedStatusesSqlList}) THEN 1 ELSE 0 END) as closed_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND priority = 'severity_1' THEN 1 ELSE 0 END) as urgent_open_tickets")
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];

        return [
            'total_tickets' => (int) ($summaryRow['total_tickets'] ?? 0),
            'open_tickets' => (int) ($summaryRow['open_tickets'] ?? 0),
            'closed_tickets' => (int) ($summaryRow['closed_tickets'] ?? 0),
            'unassigned_open_tickets' => (int) ($summaryRow['unassigned_open_tickets'] ?? 0),
            'urgent_open_tickets' => (int) ($summaryRow['urgent_open_tickets'] ?? 0),
        ];
    }

    public function buildReportStatusBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $breakdown = $this->buildStatusBreakdownForPeriod($scopedTickets, $start, $end);
        $resolvedOnlyCount = (int) $breakdown['resolved'];
        $breakdown['resolved_only'] = $resolvedOnlyCount;
        $breakdown['resolved'] = $resolvedOnlyCount + (int) ($breakdown['closed'] ?? 0);

        return $breakdown;
    }

    public function buildReportStatusBreakdownForAllTime(Builder $scopedTickets): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|all_time';
        if (array_key_exists($cacheKey, $this->statusBreakdownCache)) {
            return $this->statusBreakdownCache[$cacheKey];
        }

        $statusCounts = (clone $scopedTickets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (int) ($statusCounts[$status] ?? 0),
        ])->all();

        $resolvedOnlyCount = (int) $breakdown['resolved'];
        $breakdown['resolved_only'] = $resolvedOnlyCount;
        $breakdown['resolved'] = $resolvedOnlyCount + (int) ($breakdown['closed'] ?? 0);

        $this->statusBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildPriorityBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->priorityBreakdownCache)) {
            return $this->priorityBreakdownCache[$cacheKey];
        }

        $priorityCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $breakdown = collect(Ticket::PRIORITIES)->mapWithKeys(fn (string $priority) => [
            $priority => (int) ($priorityCounts[$priority] ?? 0),
        ])->all();
        $breakdown = array_merge([
            'pending_review' => (int) ($priorityCounts[''] ?? $priorityCounts[null] ?? 0),
        ], $breakdown);

        $this->priorityBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildPriorityBreakdownForAllTime(Builder $scopedTickets): array
    {
        $priorityCounts = (clone $scopedTickets)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return array_merge([
            'pending_review' => (int) ($priorityCounts[''] ?? $priorityCounts[null] ?? 0),
        ], collect(Ticket::PRIORITIES)->mapWithKeys(fn (string $priority) => [
            $priority => (int) ($priorityCounts[$priority] ?? 0),
        ])->all());
    }

    public function buildCategoryBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->categoryBreakdownCache)) {
            return $this->categoryBreakdownCache[$cacheKey];
        }

        $breakdown = $this->reportBreakdowns->buildCategoryBreakdownForScope(
            clone $scopedTickets,
            $start,
            $end
        );

        $this->categoryBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    public function buildCategoryBreakdownForAllTime(Builder $scopedTickets): Collection
    {
        return $this->reportBreakdowns->categoryCountsForScope(clone $scopedTickets)
            ->map(fn (object $row): array => [
                'category_name' => (string) ($row->category_name ?? ''),
                'count' => (int) ($row->count ?? 0),
            ]);
    }

    public function buildCategoryBucketsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        return $this->reportBreakdowns->buildCategoryBucketsForScope(clone $scopedTickets, $start, $end);
    }

    public function buildCategoryBucketsForAllTime(Builder $scopedTickets): array
    {
        $bucketOrder = ['Hardware', 'Software', 'Network', 'Access / Permissions', 'Security', 'Other'];
        $bucketCounts = array_fill_keys($bucketOrder, 0);

        $categoryCounts = $this->reportBreakdowns->categoryCountsForScope(clone $scopedTickets);
        foreach ($categoryCounts as $row) {
            $bucket = $this->reportBreakdowns->normalizeCategoryBucket((string) $row->category_name);
            $bucketCounts[$bucket] = ($bucketCounts[$bucket] ?? 0) + (int) $row->count;
        }

        return collect($bucketCounts)->map(fn (int $count, string $name) => [
            'name' => $name,
            'count' => $count,
        ])->values()->all();
    }

    public function buildPriorityBucketsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        return $this->reportBreakdowns->buildPriorityBucketsForScope(clone $scopedTickets, $start, $end);
    }

    public function buildPriorityBucketsForAllTime(Builder $scopedTickets): array
    {
        $counts = (clone $scopedTickets)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return [
            ['name' => 'Pending Review', 'count' => (int) ($counts[''] ?? $counts[null] ?? 0)],
            ['name' => 'Severity 1', 'count' => (int) ($counts['severity_1'] ?? 0)],
            ['name' => 'Severity 2', 'count' => (int) ($counts['severity_2'] ?? 0)],
            ['name' => 'Severity 3', 'count' => (int) ($counts['severity_3'] ?? 0)],
        ];
    }

    public function buildMajorIssueSummary(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->majorIssueSummaryCache)) {
            return $this->majorIssueSummaryCache[$cacheKey];
        }

        $incidentScopedTickets = (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->where('priority', 'severity_1');

        $summary = $this->buildMajorIssueAggregate($incidentScopedTickets);
        $this->majorIssueSummaryCache[$cacheKey] = $summary;

        return $summary;
    }

    public function buildMajorIssueSummaryForAllTime(Builder $scopedTickets): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets).'|all_time_major_issues';
        if (array_key_exists($cacheKey, $this->majorIssueSummaryCache)) {
            return $this->majorIssueSummaryCache[$cacheKey];
        }

        $summary = $this->buildMajorIssueAggregate((clone $scopedTickets)->where('priority', 'severity_1'));
        $this->majorIssueSummaryCache[$cacheKey] = $summary;

        return $summary;
    }

    public function buildDailyVolumeSeries(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $counts = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as point_date, COUNT(*) as count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('point_date')
            ->orderBy('point_date')
            ->pluck('count', 'point_date');

        $series = collect();
        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $series->push([
                'key' => $date,
                'label' => $cursor->format('M j'),
                'short_label' => $cursor->format('m/d'),
                'count' => (int) ($counts[$date] ?? 0),
            ]);
        }

        return $series;
    }

    public function buildWeeklyVolumeSeries(Builder $scopedTickets, int $weeks = 12): Collection
    {
        $weeks = max(4, $weeks);
        $start = now()->copy()->startOfWeek()->subWeeks($weeks - 1);
        $end = now()->copy()->endOfWeek();

        $tickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        $counts = [];
        foreach ($tickets as $ticket) {
            /** @var Ticket $ticket */
            if (! $ticket->created_at) {
                continue;
            }

            $weekKey = $ticket->created_at->copy()->startOfWeek()->toDateString();
            $counts[$weekKey] = ($counts[$weekKey] ?? 0) + 1;
        }

        $series = collect();
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addWeek()) {
            $weekKey = $cursor->toDateString();
            $series->push([
                'key' => $weekKey,
                'label' => 'Wk '.$cursor->isoWeek,
                'short_label' => $cursor->format('M j'),
                'count' => (int) ($counts[$weekKey] ?? 0),
            ]);
        }

        return $series;
    }

    public function buildDailyTicketStatisticsForDate(Builder $scopedTickets, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return [
            'mode' => 'day',
            'date' => $start->toDateString(),
            'label' => $start->format('M j, Y'),
            'received' => (clone $scopedTickets)->whereBetween('created_at', [$start, $end])->count(),
            'in_progress' => (clone $scopedTickets)->whereBetween('created_at', [$start, $end])->where('status', 'in_progress')->count(),
            'resolved' => $this->countResolvedTicketsWithinRange(clone $scopedTickets, $start, $end),
            'closed' => $this->countClosedTicketsWithinRange(clone $scopedTickets, $start, $end),
        ];
    }

    public function buildDailyTicketStatisticsForRange(
        Builder $scopedTickets,
        Carbon $start,
        Carbon $end,
        string $rangeLabel
    ): array {
        return [
            'mode' => 'month',
            'date' => null,
            'label' => 'All days in '.$rangeLabel,
            'received' => (clone $scopedTickets)->whereBetween('created_at', [$start, $end])->count(),
            'in_progress' => (clone $scopedTickets)->whereBetween('created_at', [$start, $end])->where('status', 'in_progress')->count(),
            'resolved' => $this->countResolvedTicketsWithinRange(clone $scopedTickets, $start, $end),
            'closed' => $this->countClosedTicketsWithinRange(clone $scopedTickets, $start, $end),
        ];
    }

    private function buildStatusBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->statusBreakdownCache)) {
            return $this->statusBreakdownCache[$cacheKey];
        }

        $statusCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (int) ($statusCounts[$status] ?? 0),
        ])->all();

        $this->statusBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    private function countResolvedTicketsWithinRange(Builder $scopedTickets, Carbon $start, Carbon $end): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->resolvedTicketCountCache)) {
            return $this->resolvedTicketCountCache[$cacheKey];
        }

        $count = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', Ticket::CLOSED_STATUSES)
            ->count();

        $this->resolvedTicketCountCache[$cacheKey] = (int) $count;

        return (int) $count;
    }

    private function countClosedTicketsWithinRange(Builder $scopedTickets, Carbon $start, Carbon $end): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->closedTicketCountCache)) {
            return $this->closedTicketCountCache[$cacheKey];
        }

        $count = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'closed')
            ->count();

        $this->closedTicketCountCache[$cacheKey] = (int) $count;

        return (int) $count;
    }

    private function buildMajorIssueAggregate(Builder $incidentScopedTickets): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $incidentScopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as major_count')
            ->selectRaw("SUM(CASE WHEN priority = 'severity_1' THEN 1 ELSE 0 END) as urgent_total")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_major_count")
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];

        $incidentByCategory = $this->reportBreakdowns
            ->categoryCountsForScope(clone $incidentScopedTickets)
            ->map(function (object $row): array {
                $count = (int) ($row->count ?? 0);

                return [
                    'name' => $this->reportBreakdowns->normalizeCategoryBucket((string) ($row->category_name ?? '')),
                    'count' => $count,
                ];
            })
            ->groupBy('name')
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'count' => (int) $group->sum('count'),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(3);

        return [
            'major_count' => (int) ($summaryRow['major_count'] ?? 0),
            'open_major_count' => (int) ($summaryRow['open_major_count'] ?? 0),
            'urgent_total' => (int) ($summaryRow['urgent_total'] ?? 0),
            'top_categories' => $incidentByCategory,
        ];
    }

    private function queryScopeSignature(Builder $scopedTickets): string
    {
        return sha1($scopedTickets->toSql().'|'.json_encode($scopedTickets->getBindings()));
    }
}
