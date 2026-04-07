<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\ReportBreakdownService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportPageDataService
{
    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
        private MonthlyReportDatasetService $monthlyReportDatasets,
        private ReportScopeService $reportScopes,
        private ReportStatisticsService $reportStatistics,
        private SlaReportService $slaReports,
    ) {}

    public function buildIndexViewData(Request $request, User $user): array
    {
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows, $monthlyGraphPoints] = $this->monthlyReportDatasets->build(clone $scopedTickets);

        $selectedMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('month'),
            $monthlyReportRows,
            allowAllTime: true
        );
        $selectedMonthIsAllTime = $selectedMonthKey === ReportScopeService::ALL_TIME_MONTH_KEY;
        $selectedMonthRange = $selectedMonthIsAllTime
            ? $this->reportScopes->allTimeRangeForScope(clone $scopedTickets)
            : $this->reportScopes->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $selectedMonthIsAllTime
            ? $this->reportScopes->allTimeReportRow(clone $scopedTickets, $selectedMonthRange, $this->monthlyReportDatasets)
            : ($monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
                ?? $this->reportScopes->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange));
        $selectedPeriodStart = $selectedMonthRange['start']->copy();
        $selectedPeriodEnd = $selectedMonthRange['end']->copy();
        $detailFilterApplied = $request->boolean('apply_details_filter');

        $detailMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('detail_month'),
            $monthlyReportRows,
            $selectedMonthKey
        );
        $detailMonthRange = $this->reportScopes->monthRangeFromKey($detailMonthKey);
        $detailDateOptions = $this->reportScopes->buildDateOptionsForRange($detailMonthRange['start'], $detailMonthRange['end']);
        $detailSelectedDate = $this->reportScopes->resolveRequestedDate($request->query('detail_date'));
        if (
            $detailSelectedDate
            && (
                $detailSelectedDate->lt($detailMonthRange['start']->copy()->startOfDay())
                || $detailSelectedDate->gt($detailMonthRange['end']->copy()->startOfDay())
            )
        ) {
            $detailSelectedDate = null;
        }
        $detailDateValue = $detailSelectedDate?->toDateString();
        $detailScopeStart = $detailSelectedDate?->copy()->startOfDay() ?? $detailMonthRange['start']->copy();
        $detailScopeEnd = $detailSelectedDate?->copy()->endOfDay() ?? $detailMonthRange['end']->copy();
        $detailScopeLabel = $detailSelectedDate?->format('M j, Y') ?? (string) $detailMonthRange['label'];

        $dailyMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('daily_month'),
            $monthlyReportRows,
            now()->format('Y-m')
        );
        $dailyMonthRange = $this->reportScopes->monthRangeFromKey($dailyMonthKey);
        if ($detailFilterApplied) {
            $dailyMonthKey = $detailMonthKey;
            $dailyMonthRange = $detailMonthRange;
        }

        $dailyDateOptions = $this->reportScopes->buildDateOptionsForRange($dailyMonthRange['start'], $dailyMonthRange['end']);
        $requestedDailyDate = is_string($request->query('daily_date'))
            ? trim((string) $request->query('daily_date'))
            : '';
        $dailyAllDaysSelected = $requestedDailyDate === 'all';
        $dailySelectedDate = $dailyAllDaysSelected
            ? null
            : $this->reportScopes->resolveRequestedDate($request->query('daily_date'));
        if ($detailFilterApplied && $detailSelectedDate) {
            $dailySelectedDate = $detailSelectedDate->copy();
            $dailyAllDaysSelected = false;
        }
        if (
            ! $dailyAllDaysSelected
            && (
                ! $dailySelectedDate
                || $dailySelectedDate->lt($dailyMonthRange['start']->copy()->startOfDay())
                || $dailySelectedDate->gt($dailyMonthRange['end']->copy()->startOfDay())
            )
        ) {
            $today = now()->startOfDay();
            if (
                $today->gte($dailyMonthRange['start']->copy()->startOfDay())
                && $today->lte($dailyMonthRange['end']->copy()->startOfDay())
            ) {
                $dailySelectedDate = $today;
            } else {
                $dailySelectedDate = $dailyMonthRange['end']->copy()->startOfDay();
            }
        }
        $dailySelectedDateValue = $dailyAllDaysSelected ? 'all' : $dailySelectedDate->toDateString();

        $kpiScopedTickets = clone $scopedTickets;
        if ($detailFilterApplied) {
            $kpiScopedTickets->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd]);
        }

        $kpiSummary = $this->reportStatistics->buildKpiSummary(clone $kpiScopedTickets);
        $totalTickets = (int) $kpiSummary['total_tickets'];
        $resolvedTicketsCount = (int) $kpiSummary['closed_tickets'];
        $stats = [
            'total_tickets' => $totalTickets,
            'open_tickets' => (int) $kpiSummary['open_tickets'],
            'closed_tickets' => $resolvedTicketsCount,
            'unassigned_open_tickets' => (int) $kpiSummary['unassigned_open_tickets'],
            'urgent_open_tickets' => (int) $kpiSummary['urgent_open_tickets'],
            'resolution_rate' => $totalTickets > 0 ? round(($resolvedTicketsCount / $totalTickets) * 100, 1) : 0,
        ];

        $periodStatusSummary = $this->statusBreakdownForScope(
            clone $scopedTickets,
            $selectedMonthIsAllTime,
            $selectedPeriodStart,
            $selectedPeriodEnd
        );
        $totalTicketsThisPeriod = $selectedMonthIsAllTime ? (clone $scopedTickets)->count() : (int) ($selectedMonthRow['received'] ?? 0);
        $backlogThisPeriod = $this->monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $selectedPeriodEnd);
        $majorIssueSummary = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildMajorIssueSummaryForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildMajorIssueSummary(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
        $periodOverview = [
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

        if ($detailFilterApplied) {
            $mixScopeLabel = $detailScopeLabel;
            $ticketHistoryScope = [
                'created_from' => $detailScopeStart->toDateString(),
                'created_to' => $detailScopeEnd->toDateString(),
                'report_scope' => $mixScopeLabel,
            ];
            $mixStatusSummary = $this->statusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
            $mixTotalCreated = (clone $scopedTickets)->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd])->count();
            $ticketsBreakdownOverview = [
                'label' => $mixScopeLabel,
                'start' => $detailScopeStart->toDateString(),
                'end' => $detailScopeEnd->toDateString(),
                'total_created' => $mixTotalCreated,
                'open' => (int) ($mixStatusSummary['open'] ?? 0),
                'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
                'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
                'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
                'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
            ];
            $categoryBreakdownBuckets = $this->reportStatistics->buildCategoryBucketsForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
            $priorityBreakdownBuckets = $this->reportStatistics->buildPriorityBucketsForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
        } else {
            $mixScopeLabel = 'All Time';
            $ticketHistoryScope = [];
            $mixStatusSummary = $this->statusBreakdownForAllTime(clone $scopedTickets);
            $mixTotalCreated = (clone $scopedTickets)->count();
            $ticketsBreakdownOverview = [
                'label' => $mixScopeLabel,
                'start' => null,
                'end' => null,
                'total_created' => $mixTotalCreated,
                'open' => (int) ($mixStatusSummary['open'] ?? 0),
                'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
                'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
                'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
                'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
            ];
            $categoryBreakdownBuckets = $this->reportStatistics->buildCategoryBucketsForAllTime(clone $scopedTickets);
            $priorityBreakdownBuckets = $this->reportStatistics->buildPriorityBucketsForAllTime(clone $scopedTickets);
        }

        $dailyVolumeStart = $selectedMonthIsAllTime ? now()->copy()->startOfDay()->subDays(29) : $selectedPeriodStart;
        $dailyVolumeEnd = $selectedMonthIsAllTime ? now()->copy()->endOfDay() : $selectedPeriodEnd;
        $volumeSeries = [
            'daily' => $this->reportStatistics->buildDailyVolumeSeries(clone $scopedTickets, $dailyVolumeStart, $dailyVolumeEnd),
            'weekly' => $this->reportStatistics->buildWeeklyVolumeSeries(clone $scopedTickets, 12),
            'monthly' => $monthlyReportRows->map(fn (array $row) => [
                'label' => Carbon::createFromFormat('Y-m', $row['month_key'])->format('M'),
                'count' => (int) $row['received'],
                'key' => $row['month_key'],
            ])->values(),
        ];
        $dailySelectedStats = $dailyAllDaysSelected
            ? $this->reportStatistics->buildDailyTicketStatisticsForRange(
                clone $scopedTickets,
                $dailyMonthRange['start']->copy()->startOfDay(),
                $dailyMonthRange['end']->copy()->endOfDay(),
                (string) $dailyMonthRange['label']
            )
            : $this->reportStatistics->buildDailyTicketStatisticsForDate(clone $scopedTickets, $dailySelectedDate);

        $slaScopeStart = $detailFilterApplied ? $detailScopeStart->copy() : $selectedPeriodStart->copy();
        $slaScopeEnd = $detailFilterApplied ? $detailScopeEnd->copy() : $selectedPeriodEnd->copy();
        $slaScopeLabel = $detailFilterApplied ? $detailScopeLabel : (string) $selectedMonthRange['label'];
        $slaReport = $this->slaReports->build(clone $scopedTickets, $slaScopeStart, $slaScopeEnd, $slaScopeLabel);

        $detailStatusSummary = $this->statusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
        $detailTotalCreated = (clone $scopedTickets)->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd])->count();
        $detailOverview = [
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
            'backlog_end' => $this->monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $detailScopeEnd),
        ];

        $monthlyPerformanceGraphPoints = $monthlyGraphPoints;
        if ($detailFilterApplied) {
            $monthlyPerformanceScopedTickets = (clone $scopedTickets)->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd]);
            [, $monthlyPerformanceGraphPoints] = $this->monthlyReportDatasets->build(clone $monthlyPerformanceScopedTickets);
        }
        $monthlyPerformanceFocusMonthKey = $detailFilterApplied
            ? $detailMonthKey
            : ($selectedMonthIsAllTime
                ? $this->reportScopes->latestAvailableMonthKey($monthlyReportRows)
                : $selectedMonthKey);
        $reportVisuals = $this->buildReportVisuals(
            ($monthlyPerformanceGraphPoints ?? $monthlyGraphPoints)->values()->all(),
            (string) $monthlyPerformanceFocusMonthKey,
            $ticketsBreakdownOverview,
            $categoryBreakdownBuckets,
            $priorityBreakdownBuckets,
            $ticketHistoryScope
        );

        $ticketsByStatus = (clone $scopedTickets)->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $ticketsByPriority = (clone $scopedTickets)->selectRaw('priority, COUNT(*) as count')->groupBy('priority')->pluck('count', 'priority');
        $categoryCounts = $this->reportBreakdowns->categoryCountsForScope(clone $scopedTickets);
        $ticketsByCategory = $categoryCounts
            ->map(fn (object $row) => [
                'name' => (string) $row->category_name,
                'count' => (int) $row->count,
                'share' => $totalTickets > 0 ? round(((int) $row->count / $totalTickets) * 100, 1) : 0.0,
            ])
            ->values();

        $selectedMonthStatuses = $this->statusBreakdownForScope(
            clone $scopedTickets,
            $selectedMonthIsAllTime,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $selectedMonthPriorities = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildPriorityBreakdownForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildPriorityBreakdownForPeriod(clone $scopedTickets, $selectedMonthRange['start'], $selectedMonthRange['end']);
        $selectedMonthCategories = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildCategoryBreakdownForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildCategoryBreakdownForPeriod(clone $scopedTickets, $selectedMonthRange['start'], $selectedMonthRange['end']);

        $monthOptions = $monthlyReportRows
            ->map(fn (array $row) => ['key' => $row['month_key'], 'label' => $row['month_label']])
            ->reverse()
            ->prepend(['key' => ReportScopeService::ALL_TIME_MONTH_KEY, 'label' => 'All Time'])
            ->values();

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
        $topTechnicians = $topTechnicianRows
            ->map(function (object $row) {
                $rowData = (array) $row;

                return [
                    'name' => (string) ($rowData['technician_name'] ?? 'Unknown technical user'),
                    'total_tickets' => (int) ($rowData['total_tickets'] ?? 0),
                    'resolved_tickets' => (int) ($rowData['resolved_tickets'] ?? 0),
                ];
            })
            ->values();

        return compact(
            'stats',
            'ticketsByStatus',
            'ticketsByPriority',
            'ticketsByCategory',
            'ticketTrend',
            'topTechnicians',
            'monthlyReportRows',
            'monthlyGraphPoints',
            'monthlyPerformanceGraphPoints',
            'monthlyPerformanceFocusMonthKey',
            'monthOptions',
            'selectedMonthKey',
            'selectedMonthIsAllTime',
            'selectedMonthRow',
            'selectedMonthStatuses',
            'selectedMonthPriorities',
            'selectedMonthCategories',
            'periodOverview',
            'majorIssueSummary',
            'categoryBreakdownBuckets',
            'priorityBreakdownBuckets',
            'volumeSeries',
            'detailFilterApplied',
            'ticketsBreakdownOverview',
            'dailyMonthKey',
            'dailySelectedDateValue',
            'dailyDateOptions',
            'dailySelectedStats',
            'detailMonthKey',
            'detailDateValue',
            'detailDateOptions',
            'detailOverview',
            'ticketHistoryScope',
            'reportVisuals',
            'slaReport'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $monthlyPerformanceSeries
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScope
     */
    private function buildReportVisuals(
        array $monthlyPerformanceSeries,
        string $monthlyPerformanceFocusMonthKey,
        array $ticketsBreakdownOverview,
        array $categoryBreakdownBuckets,
        array $priorityBreakdownBuckets,
        array $ticketHistoryScope,
    ): array {
        $mixScopeLabel = (string) ($ticketsBreakdownOverview['label'] ?? 'All Time');
        $ticketHistoryScopeParams = collect($ticketHistoryScope)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $pieRadius = 58;
        $pieCircumference = 2 * pi() * $pieRadius;

        $ticketPie = $this->buildPieVisualization(
            $this->buildTicketPieSlices($ticketsBreakdownOverview, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $categoryPie = $this->buildPieVisualization(
            $this->buildCategoryPieSlices($categoryBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $priorityPie = $this->buildPieVisualization(
            $this->buildPriorityPieSlices($priorityBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );

        $monthlyCountMax = max(1, collect($monthlyPerformanceSeries)->map(
            fn (array $point): int => max((int) ($point['received'] ?? 0), (int) ($point['resolved'] ?? 0))
        )->max() ?? 0);
        $chartHeight = 320;
        $chartWidth = max(760, count($monthlyPerformanceSeries) * 70);
        $paddingLeft = 44;
        $paddingRight = 24;
        $paddingTop = 18;
        $paddingBottom = 52;
        $plotWidth = max(1, $chartWidth - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $chartHeight - $paddingTop - $paddingBottom);
        $step = count($monthlyPerformanceSeries) > 0 ? ($plotWidth / count($monthlyPerformanceSeries)) : $plotWidth;
        $gridLines = collect([0, 25, 50, 75, 100])
            ->map(function (int $lineRate) use ($paddingTop, $plotHeight, $monthlyCountMax): array {
                $lineY = $paddingTop + ($plotHeight - (($lineRate / 100) * $plotHeight));

                return [
                    'rate' => $lineRate,
                    'y' => $lineY,
                    'count_label' => (int) round(($lineRate / 100) * $monthlyCountMax),
                ];
            })
            ->all();
        $barWidth = max(8, (int) floor(($step * 0.28)));
        $bars = collect($monthlyPerformanceSeries)
            ->values()
            ->map(function (array $point, int $pointIndex) use ($paddingLeft, $step, $barWidth, $monthlyCountMax, $paddingTop, $plotHeight): array {
                $centerX = $paddingLeft + ($pointIndex * $step) + ($step / 2);
                $received = (int) ($point['received'] ?? 0);
                $resolved = (int) ($point['resolved'] ?? 0);
                $receivedHeight = $received > 0 ? (($received / $monthlyCountMax) * $plotHeight) : 0;
                $resolvedHeight = $resolved > 0 ? (($resolved / $monthlyCountMax) * $plotHeight) : 0;

                return [
                    'label' => (string) ($point['label'] ?? ''),
                    'center_x' => $centerX,
                    'bar_width' => $barWidth,
                    'received_height' => max(1, $receivedHeight),
                    'resolved_height' => max(1, $resolvedHeight),
                    'received_y' => $paddingTop + ($plotHeight - $receivedHeight),
                    'resolved_y' => $paddingTop + ($plotHeight - $resolvedHeight),
                    'label_y' => $paddingTop + $plotHeight + 14,
                ];
            })
            ->all();

        return [
            'mix_scope_label' => $mixScopeLabel,
            'pie_radius' => $pieRadius,
            'pie_circumference' => $pieCircumference,
            'ticket_pie' => $ticketPie,
            'category_pie' => $categoryPie,
            'priority_pie' => $priorityPie,
            'monthly_performance' => [
                'series' => $monthlyPerformanceSeries,
                'count_max' => $monthlyCountMax,
                'chart_height' => $chartHeight,
                'chart_width' => $chartWidth,
                'padding_left' => $paddingLeft,
                'padding_right' => $paddingRight,
                'padding_top' => $paddingTop,
                'padding_bottom' => $paddingBottom,
                'plot_width' => $plotWidth,
                'plot_height' => $plotHeight,
                'step' => $step,
                'grid_lines' => $gridLines,
                'bars' => $bars,
                'selected_point' => collect($monthlyPerformanceSeries)->firstWhere('key', $monthlyPerformanceFocusMonthKey)
                    ?? collect($monthlyPerformanceSeries)->last(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     */
    private function buildPieVisualization(array $slices, float $circumference): array
    {
        $total = max(0, (int) collect($slices)->sum('count'));

        return [
            'total' => $total,
            'slices' => $slices,
            'segments' => $this->buildPieSegments($slices, $total, $circumference),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     * @return array<int, array<string, mixed>>
     */
    private function buildPieSegments(array $slices, int $total, float $circumference): array
    {
        if ($total <= 0) {
            return [];
        }

        $segments = [];
        $accumulatedLength = 0.0;
        foreach ($slices as $slice) {
            $count = (int) ($slice['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $segmentLength = ($count / $total) * $circumference;
            $segments[] = [
                'label' => (string) ($slice['label'] ?? ''),
                'count' => $count,
                'color' => (string) ($slice['color'] ?? '#94a3b8'),
                'length' => $segmentLength,
                'offset' => -$accumulatedLength,
            ];
            $accumulatedLength += $segmentLength;
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $ticketsBreakdownOverview
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildTicketPieSlices(array $ticketsBreakdownOverview, array $ticketHistoryScopeParams): array
    {
        $pieResolved = (int) ($ticketsBreakdownOverview['resolved'] ?? 0);
        $pieClosed = (int) ($ticketsBreakdownOverview['closed'] ?? 0);
        $pieResolvedOnly = max($pieResolved - $pieClosed, 0);

        return collect([
            ['label' => 'Open', 'count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'color' => '#8b5cf6', 'tab' => 'tickets', 'status' => 'open'],
            ['label' => 'In Progress', 'count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'color' => '#0ea5e9', 'tab' => 'tickets', 'status' => 'in_progress'],
            ['label' => 'Pending', 'count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'color' => '#f59e0b', 'tab' => 'tickets', 'status' => 'pending'],
            ['label' => 'Resolved', 'count' => $pieResolvedOnly, 'display_count' => $pieResolved, 'color' => '#10b981', 'tab' => 'history', 'status' => null],
            ['label' => 'Closed', 'count' => $pieClosed, 'display_count' => $pieClosed, 'color' => '#64748b', 'tab' => 'history', 'status' => 'closed'],
        ])->map(function (array $slice) use ($ticketHistoryScopeParams): array {
            $statusLinkParams = array_merge($ticketHistoryScopeParams, ['tab' => $slice['tab']]);
            if ($slice['status'] !== null) {
                $statusLinkParams['status'] = $slice['status'];
            }

            $slice['link'] = route('admin.tickets.index', $statusLinkParams);

            return $slice;
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryPieSlices(array $categoryBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        $categoryPalette = [
            'hardware' => '#0ea5e9',
            'software' => '#8b5cf6',
            'network' => '#14b8a6',
            'access / permissions' => '#f59e0b',
            'security' => '#ef4444',
            'other' => '#64748b',
        ];

        return collect($categoryBreakdownBuckets)
            ->map(function (array $bucket) use ($categoryPalette, $ticketHistoryScopeParams): array {
                $label = (string) ($bucket['name'] ?? 'Other');

                return [
                    'label' => $label,
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => $categoryPalette[strtolower($label)] ?? '#94a3b8',
                    'link' => route('admin.tickets.index', array_merge($ticketHistoryScopeParams, [
                        'tab' => 'all',
                        'category_bucket' => strtolower(str_replace([' / ', ' '], ['_', '_'], $label)),
                    ])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildPriorityPieSlices(array $priorityBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        $priorityPalette = [
            'pending review' => '#64748b',
            'severity 1' => '#ef4444',
            'severity 2' => '#f59e0b',
            'severity 3' => '#10b981',
        ];

        return collect($priorityBreakdownBuckets)
            ->map(function (array $bucket) use ($priorityPalette, $ticketHistoryScopeParams): array {
                $label = (string) ($bucket['name'] ?? 'Other');
                $priorityFilter = match (strtolower($label)) {
                    'pending review' => 'unassigned',
                    'severity 1' => 'severity_1',
                    'severity 2' => 'severity_2',
                    'severity 3' => 'severity_3',
                    default => null,
                };

                return [
                    'label' => $label,
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => $priorityPalette[strtolower($label)] ?? '#94a3b8',
                    'link' => $priorityFilter
                        ? route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all', 'priority' => $priorityFilter]))
                        : route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all'])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }

    public function buildMonthlyPdfViewData(Request $request, User $user): array
    {
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows] = $this->monthlyReportDatasets->build(clone $scopedTickets);
        $selectedMonthKey = $this->reportScopes->resolveSelectedMonthKey($request->query('month'), $monthlyReportRows);
        $selectedMonthRange = $this->reportScopes->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
            ?? $this->reportScopes->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange);

        return [
            'generatedAt' => now(),
            'selectedMonthKey' => $selectedMonthKey,
            'selectedMonthRow' => $selectedMonthRow,
            'selectedMonthRange' => $selectedMonthRange,
            'statusBreakdown' => $this->reportStatistics->buildReportStatusBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'priorityBreakdown' => $this->reportStatistics->buildPriorityBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'categoryBreakdown' => $this->reportStatistics->buildCategoryBreakdownForPeriod(
                clone $scopedTickets,
                $selectedMonthRange['start'],
                $selectedMonthRange['end']
            ),
            'monthlyReportRows' => $monthlyReportRows->reverse()->values(),
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    private function scopedTicketQueryForUser(User $user): Builder
    {
        $query = Ticket::query();

        if ($user->isTechnician()) {
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function statusBreakdownForScope(Builder $query, bool $allTime, Carbon $start, Carbon $end): array
    {
        return $allTime
            ? $this->statusBreakdownForAllTime($query)
            : $this->statusBreakdownForPeriod($query, $start, $end);
    }

    /**
     * @return array<string, int>
     */
    private function statusBreakdownForAllTime(Builder $query): array
    {
        return $this->reportStatistics->buildReportStatusBreakdownForAllTime($query);
    }

    /**
     * @return array<string, int>
     */
    private function statusBreakdownForPeriod(Builder $query, Carbon $start, Carbon $end): array
    {
        return $this->reportStatistics->buildReportStatusBreakdownForPeriod($query, $start, $end);
    }
}
