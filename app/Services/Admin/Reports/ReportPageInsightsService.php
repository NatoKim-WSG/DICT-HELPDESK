<?php

namespace App\Services\Admin\Reports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportPageInsightsService
{
    public function __construct(
        private ReportPageQueryMetricsService $queryMetrics,
        private ReportStatisticsService $reportStatistics,
    ) {}

    public function buildStats(array $kpiSummary): array
    {
        $totalTickets = (int) ($kpiSummary['total_tickets'] ?? 0);
        $resolvedTicketsCount = (int) ($kpiSummary['closed_tickets'] ?? 0);

        return [
            'total_tickets' => $totalTickets,
            'open_tickets' => (int) ($kpiSummary['open_tickets'] ?? 0),
            'closed_tickets' => $resolvedTicketsCount,
            'unassigned_open_tickets' => (int) ($kpiSummary['unassigned_open_tickets'] ?? 0),
            'severity_one_open_tickets' => (int) ($kpiSummary['severity_one_open_tickets'] ?? 0),
            'resolution_rate' => $totalTickets > 0 ? round(($resolvedTicketsCount / $totalTickets) * 100, 1) : 0,
        ];
    }

    public function buildPeriodOverview(
        array $selectedMonthRange,
        Carbon $selectedPeriodStart,
        Carbon $selectedPeriodEnd,
        int $totalTicketsThisPeriod,
        array $majorIssueSummary,
        array $periodStatusSummary,
        int $backlogThisPeriod,
    ): array {
        return [
            'label' => (string) $selectedMonthRange['label'],
            'start' => $selectedPeriodStart->toDateString(),
            'end' => $selectedPeriodEnd->toDateString(),
            'total_tickets' => $totalTicketsThisPeriod,
            'major_issues_count' => (int) ($majorIssueSummary['major_count'] ?? 0),
            'total_created' => $totalTicketsThisPeriod,
            'open' => (int) ($periodStatusSummary['open'] ?? 0),
            'in_progress' => (int) ($periodStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($periodStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($periodStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($periodStatusSummary['closed'] ?? 0),
            'backlog_end' => $backlogThisPeriod,
        ];
    }

    /**
     * @return array{selectedMonthStatuses: array<string, int>, selectedMonthPriorities: array<string, int>, selectedMonthCategories: Collection<int, array<string, int|string>>}
     */
    public function buildSelectedMonthBreakdowns(
        Builder $scopedTickets,
        bool $selectedMonthIsAllTime,
        array $selectedMonthRange,
    ): array {
        return [
            'selectedMonthStatuses' => $this->queryMetrics->statusBreakdownForScope(
                clone $scopedTickets,
                $selectedMonthIsAllTime,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'selectedMonthPriorities' => $selectedMonthIsAllTime
                ? $this->reportStatistics->buildPriorityBreakdownForAllTime(clone $scopedTickets)
                : $this->reportStatistics->buildPriorityBreakdownForPeriod(
                    clone $scopedTickets,
                    $selectedMonthRange['start'],
                    $selectedMonthRange['end']
                ),
            'selectedMonthCategories' => $selectedMonthIsAllTime
                ? $this->reportStatistics->buildCategoryBreakdownForAllTime(clone $scopedTickets)
                : $this->reportStatistics->buildCategoryBreakdownForPeriod(
                    clone $scopedTickets,
                    $selectedMonthRange['start'],
                    $selectedMonthRange['end']
                ),
        ];
    }

    public function buildTicketsByCategory(Collection $categoryCounts, int $totalTickets): Collection
    {
        return $categoryCounts
            ->map(fn (object $row) => [
                'name' => (string) $row->category_name,
                'count' => (int) $row->count,
                'share' => $totalTickets > 0 ? round(((int) $row->count / $totalTickets) * 100, 1) : 0.0,
            ])
            ->values();
    }

    public function buildMixBreakdownData(
        Builder $scopedTickets,
        bool $detailFilterApplied,
        Carbon $detailScopeStart,
        Carbon $detailScopeEnd,
        string $detailScopeLabel
    ): array {
        if ($detailFilterApplied) {
            $mixStatusSummary = $this->queryMetrics->statusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
            $mixTotalCreated = $this->queryMetrics->createdTicketCountForRange(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);

            return [
                'mixScopeLabel' => $detailScopeLabel,
                'ticketHistoryScope' => [
                    'created_from' => $detailScopeStart->toDateString(),
                    'created_to' => $detailScopeEnd->toDateString(),
                    'report_scope' => $detailScopeLabel,
                ],
                'ticketsBreakdownOverview' => [
                    'label' => $detailScopeLabel,
                    'start' => $detailScopeStart->toDateString(),
                    'end' => $detailScopeEnd->toDateString(),
                    'total_created' => $mixTotalCreated,
                    'open' => (int) ($mixStatusSummary['open'] ?? 0),
                    'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
                    'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
                    'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
                    'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
                ],
                'categoryBreakdownBuckets' => $this->reportStatistics->buildCategoryBucketsForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd),
                'priorityBreakdownBuckets' => $this->reportStatistics->buildPriorityBucketsForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd),
            ];
        }

        $mixStatusSummary = $this->queryMetrics->statusBreakdownForAllTime(clone $scopedTickets);
        $mixTotalCreated = $this->queryMetrics->ticketCount(clone $scopedTickets);
        $priorityCounts = $this->queryMetrics->priorityCountsForScope(clone $scopedTickets);
        $categoryCounts = $this->queryMetrics->categoryCountsForScope(clone $scopedTickets);

        return [
            'mixScopeLabel' => 'All Time',
            'ticketHistoryScope' => [],
            'ticketsBreakdownOverview' => [
                'label' => 'All Time',
                'start' => null,
                'end' => null,
                'total_created' => $mixTotalCreated,
                'open' => (int) ($mixStatusSummary['open'] ?? 0),
                'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
                'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
                'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
                'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
            ],
            'categoryBreakdownBuckets' => $this->reportStatistics->buildCategoryBucketsFromCounts($categoryCounts),
            'priorityBreakdownBuckets' => $this->reportStatistics->buildPriorityBucketsFromCounts($priorityCounts),
        ];
    }

    public function buildDetailOverview(
        Builder $scopedTickets,
        Carbon $detailScopeStart,
        Carbon $detailScopeEnd,
        string $detailScopeLabel,
        ?Carbon $detailSelectedDate
    ): array {
        $detailStatusSummary = $this->queryMetrics->statusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
        $detailTotalCreated = $this->queryMetrics->createdTicketCountForRange(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);

        return [
            'label' => $detailScopeLabel,
            'mode' => $detailSelectedDate ? 'day' : 'month',
            'start' => $detailScopeStart->toDateString(),
            'end' => $detailScopeEnd->toDateString(),
            'total_created' => $detailTotalCreated,
            'open' => (int) ($detailStatusSummary['open'] ?? 0),
            'in_progress' => (int) ($detailStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($detailStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($detailStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($detailStatusSummary['closed'] ?? 0),
            'backlog_end' => $this->queryMetrics->openTicketCountAtCutoff(clone $scopedTickets, $detailScopeEnd),
        ];
    }

    public function buildTicketTrend(Builder $scopedTickets): Collection
    {
        $trendStart = Carbon::now()->startOfDay()->subDays(29);
        $trendEnd = Carbon::now()->startOfDay();
        $trendCounts = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as ticket_date, COUNT(*) as count')
            ->whereBetween('created_at', [$trendStart, $trendEnd->copy()->endOfDay()])
            ->groupBy('ticket_date')
            ->orderBy('ticket_date')
            ->pluck('count', 'ticket_date');
        $ticketTrend = collect();
        for ($cursor = $trendStart->copy(); $cursor->lte($trendEnd); $cursor->addDay()) {
            $dateLabel = $cursor->toDateString();
            $ticketTrend->push(['date' => $dateLabel, 'count' => (int) ($trendCounts[$dateLabel] ?? 0)]);
        }

        return $ticketTrend;
    }

    public function buildTopTechnicians(
        Builder $scopedTickets,
        bool $detailFilterApplied,
        Carbon $detailScopeStart,
        Carbon $detailScopeEnd
    ): Collection {
        $topTechnicianScopedTickets = clone $scopedTickets;
        if ($detailFilterApplied) {
            $topTechnicianScopedTickets->whereBetween('tickets.created_at', [$detailScopeStart, $detailScopeEnd]);
        }

        $topTechnicianRows = (clone $topTechnicianScopedTickets)
            ->join('ticket_assignments', 'tickets.id', '=', 'ticket_assignments.ticket_id')
            ->join('users', 'ticket_assignments.user_id', '=', 'users.id')
            ->where('users.role', '!=', User::ROLE_SHADOW)
            ->selectRaw("ticket_assignments.user_id as technician_id, users.name as technician_name, COUNT(*) as total_tickets, SUM(CASE WHEN tickets.status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_tickets")
            ->groupBy('ticket_assignments.user_id', 'users.name')
            ->orderByDesc('total_tickets')
            ->take(5)
            ->toBase()
            ->get();

        return $topTechnicianRows
            ->map(function (object $row) {
                $rowData = (array) $row;

                return [
                    'name' => (string) ($rowData['technician_name'] ?? 'Unknown technical user'),
                    'total_tickets' => (int) ($rowData['total_tickets'] ?? 0),
                    'resolved_tickets' => (int) ($rowData['resolved_tickets'] ?? 0),
                ];
            })
            ->values();
    }
}
