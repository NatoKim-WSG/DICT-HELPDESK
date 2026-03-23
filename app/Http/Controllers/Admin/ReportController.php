<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\ReportBreakdownService;
use App\Services\Admin\Reports\MonthlyReportDatasetService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    private array $resolvedTicketCountCache = [];

    private array $statusBreakdownCache = [];

    private array $priorityBreakdownCache = [];

    private array $categoryBreakdownCache = [];

    private array $slaMetricsCache = [];

    private array $majorIssueSummaryCache = [];

    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
        private MonthlyReportDatasetService $monthlyReportDatasets,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows, $monthlyGraphPoints] = $this->monthlyReportDatasets->build(clone $scopedTickets);
        $selectedMonthKey = $this->resolveSelectedMonthKey($request->query('month'), $monthlyReportRows);
        $selectedMonthRange = $this->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
            ?? $this->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange);
        $selectedPeriodStart = $selectedMonthRange['start']->copy();
        $selectedPeriodEnd = $selectedMonthRange['end']->copy();
        $previousPeriodStart = $selectedPeriodStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousPeriodEnd = $selectedPeriodStart->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay();
        $previousMonthKey = $previousPeriodStart->format('Y-m');
        $previousMonthRow = $monthlyReportRows->firstWhere('month_key', $previousMonthKey)
            ?? $this->emptyMonthlyReportRow($previousMonthKey, [
                'label' => $previousPeriodStart->format('M Y'),
                'start' => $previousPeriodStart,
                'end' => $previousPeriodEnd,
            ]);
        $detailFilterApplied = $request->boolean('apply_details_filter');

        $detailMonthKey = $this->resolveSelectedMonthKey(
            $request->query('detail_month'),
            $monthlyReportRows,
            $selectedMonthKey
        );
        $detailMonthRange = $this->monthRangeFromKey($detailMonthKey);
        $detailDateOptions = $this->buildDateOptionsForRange($detailMonthRange['start'], $detailMonthRange['end']);
        $detailSelectedDate = $this->resolveRequestedDate($request->query('detail_date'));
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

        $dailyMonthKey = $this->resolveSelectedMonthKey(
            $request->query('daily_month'),
            $monthlyReportRows,
            now()->format('Y-m')
        );
        $dailyMonthRange = $this->monthRangeFromKey($dailyMonthKey);
        if ($detailFilterApplied) {
            $dailyMonthKey = $detailMonthKey;
            $dailyMonthRange = $detailMonthRange;
        }

        $dailyDateOptions = $this->buildDateOptionsForRange($dailyMonthRange['start'], $dailyMonthRange['end']);
        $requestedDailyDate = is_string($request->query('daily_date'))
            ? trim((string) $request->query('daily_date'))
            : '';
        $dailyAllDaysSelected = $requestedDailyDate === 'all';
        $dailySelectedDate = $dailyAllDaysSelected
            ? null
            : $this->resolveRequestedDate($request->query('daily_date'));
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
        $dailySelectedDateValue = $dailyAllDaysSelected
            ? 'all'
            : $dailySelectedDate->toDateString();

        $kpiScopedTickets = clone $scopedTickets;
        if ($detailFilterApplied) {
            $kpiScopedTickets->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd]);
        }

        $kpiSummary = $this->buildKpiSummary(clone $kpiScopedTickets);
        $totalTickets = (int) $kpiSummary['total_tickets'];
        $resolvedTicketsCount = (int) $kpiSummary['closed_tickets'];

        $stats = [
            'total_tickets' => $totalTickets,
            'open_tickets' => (int) $kpiSummary['open_tickets'],
            'closed_tickets' => $resolvedTicketsCount,
            'unassigned_open_tickets' => (int) $kpiSummary['unassigned_open_tickets'],
            'urgent_open_tickets' => (int) $kpiSummary['urgent_open_tickets'],
            'resolution_rate' => $totalTickets > 0
                ? round(($resolvedTicketsCount / $totalTickets) * 100, 1)
                : 0,
        ];

        $periodStatusSummary = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $selectedPeriodStart,
            $selectedPeriodEnd
        );
        $previousPeriodStatusSummary = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $previousPeriodStart,
            $previousPeriodEnd
        );

        $totalTicketsThisPeriod = (int) ($selectedMonthRow['received'] ?? 0);
        $totalTicketsPreviousPeriod = (int) ($previousMonthRow['received'] ?? 0);
        $periodPercentChange = $this->percentageChange($totalTicketsThisPeriod, $totalTicketsPreviousPeriod);
        $backlogThisPeriod = $this->monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $selectedPeriodEnd);
        $backlogPreviousPeriod = $this->monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $previousPeriodEnd);
        $slaThisPeriod = $this->buildSlaMetricsForPeriod(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
        $slaPreviousPeriod = $this->buildSlaMetricsForPeriod(clone $scopedTickets, $previousPeriodStart, $previousPeriodEnd);
        $majorIssueSummary = $this->buildMajorIssueSummary(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
        $previousMajorIssueSummary = $this->buildMajorIssueSummary(clone $scopedTickets, $previousPeriodStart, $previousPeriodEnd);
        $keyInsights = $this->buildImprovementAndRiskSummary([
            'period_label' => $selectedMonthRange['label'],
            'current_total' => $totalTicketsThisPeriod,
            'previous_total' => $totalTicketsPreviousPeriod,
            'current_completion_rate' => (float) ($selectedMonthRow['resolution_rate'] ?? 0),
            'previous_completion_rate' => (float) ($previousMonthRow['resolution_rate'] ?? 0),
            'current_sla_rate' => (float) ($slaThisPeriod['rate'] ?? 0),
            'previous_sla_rate' => (float) ($slaPreviousPeriod['rate'] ?? 0),
            'current_backlog' => $backlogThisPeriod,
            'previous_backlog' => $backlogPreviousPeriod,
            'current_pending' => (int) ($periodStatusSummary['pending'] ?? 0),
            'previous_pending' => (int) ($previousPeriodStatusSummary['pending'] ?? 0),
            'current_urgent' => (int) ($majorIssueSummary['urgent_total'] ?? 0),
            'previous_urgent' => (int) ($previousMajorIssueSummary['urgent_total'] ?? 0),
        ]);

        $periodOverview = [
            'label' => (string) $selectedMonthRange['label'],
            'start' => $selectedPeriodStart->toDateString(),
            'end' => $selectedPeriodEnd->toDateString(),
            'total_tickets' => $totalTicketsThisPeriod,
            'percent_change_vs_previous' => $periodPercentChange,
            'sla_compliance_rate' => (float) ($slaThisPeriod['rate'] ?? 0),
            'major_issues_count' => (int) ($majorIssueSummary['major_count'] ?? 0),
            'total_created' => $totalTicketsThisPeriod,
            'in_progress' => (int) ($periodStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($periodStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($periodStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($periodStatusSummary['closed'] ?? 0),
            'backlog_end' => $backlogThisPeriod,
        ];

        $mixScopeStart = $detailFilterApplied ? $detailScopeStart : $selectedPeriodStart;
        $mixScopeEnd = $detailFilterApplied ? $detailScopeEnd : $selectedPeriodEnd;
        $mixScopeLabel = $detailFilterApplied ? $detailScopeLabel : (string) $selectedMonthRange['label'];
        $ticketHistoryScope = [
            'created_from' => $mixScopeStart->toDateString(),
            'created_to' => $mixScopeEnd->toDateString(),
            'report_scope' => $mixScopeLabel,
        ];
        $mixStatusSummary = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $mixScopeStart,
            $mixScopeEnd
        );
        $mixTotalCreated = (clone $scopedTickets)
            ->whereBetween('created_at', [$mixScopeStart, $mixScopeEnd])
            ->count();
        $ticketsBreakdownOverview = [
            'label' => $mixScopeLabel,
            'start' => $mixScopeStart->toDateString(),
            'end' => $mixScopeEnd->toDateString(),
            'total_created' => $mixTotalCreated,
            'in_progress' => (int) ($mixStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($mixStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($mixStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($mixStatusSummary['closed'] ?? 0),
        ];

        $categoryBreakdownBuckets = $this->buildCategoryBucketsForPeriod(
            clone $scopedTickets,
            $mixScopeStart,
            $mixScopeEnd
        );
        $priorityBreakdownBuckets = $this->buildPriorityBucketsForPeriod(
            clone $scopedTickets,
            $mixScopeStart,
            $mixScopeEnd
        );

        $volumeSeries = [
            'daily' => $this->buildDailyVolumeSeries(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd),
            'weekly' => $this->buildWeeklyVolumeSeries(clone $scopedTickets, 12),
            'monthly' => $monthlyReportRows->map(fn (array $row) => [
                'label' => Carbon::createFromFormat('Y-m', $row['month_key'])->format('M'),
                'count' => (int) $row['received'],
                'key' => $row['month_key'],
            ])->values(),
        ];
        $dailySelectedStats = $dailyAllDaysSelected
            ? $this->buildDailyTicketStatisticsForRange(
                clone $scopedTickets,
                $dailyMonthRange['start']->copy()->startOfDay(),
                $dailyMonthRange['end']->copy()->endOfDay(),
                (string) $dailyMonthRange['label']
            )
            : $this->buildDailyTicketStatisticsForDate(
                clone $scopedTickets,
                $dailySelectedDate
            );

        $detailStatusSummary = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $detailScopeStart,
            $detailScopeEnd
        );
        $detailSlaMetrics = $this->buildSlaMetricsForPeriod(
            clone $scopedTickets,
            $detailScopeStart,
            $detailScopeEnd
        );
        $detailMajorIssueSummary = $this->buildMajorIssueSummary(
            clone $scopedTickets,
            $detailScopeStart,
            $detailScopeEnd
        );
        $detailTotalCreated = (clone $scopedTickets)
            ->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd])
            ->count();
        $detailOverview = [
            'label' => $detailScopeLabel,
            'mode' => $detailSelectedDate ? 'day' : 'month',
            'start' => $detailScopeStart->toDateString(),
            'end' => $detailScopeEnd->toDateString(),
            'total_created' => $detailTotalCreated,
            'in_progress' => (int) ($detailStatusSummary['in_progress'] ?? 0),
            'pending' => (int) ($detailStatusSummary['pending'] ?? 0),
            'resolved' => (int) ($detailStatusSummary['resolved'] ?? 0),
            'closed' => (int) ($detailStatusSummary['closed'] ?? 0),
            'backlog_end' => $this->monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $detailScopeEnd),
            'sla_compliance_rate' => (float) ($detailSlaMetrics['rate'] ?? 0),
        ];

        $monthlyPerformanceGraphPoints = $monthlyGraphPoints;
        if ($detailFilterApplied) {
            $monthlyPerformanceScopedTickets = (clone $scopedTickets)
                ->whereBetween('created_at', [$detailScopeStart, $detailScopeEnd]);
            [, $monthlyPerformanceGraphPoints] = $this->monthlyReportDatasets->build(clone $monthlyPerformanceScopedTickets);
        }
        $monthlyPerformanceFocusMonthKey = $detailFilterApplied
            ? $detailMonthKey
            : $selectedMonthKey;

        $ticketsByStatus = (clone $scopedTickets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $ticketsByPriority = (clone $scopedTickets)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $categoryCounts = $this->reportBreakdowns->categoryCountsForScope(clone $scopedTickets);
        $ticketsByCategory = $categoryCounts
            ->map(fn (object $row) => [
                'name' => (string) $row->category_name,
                'count' => (int) $row->count,
                'share' => $totalTickets > 0 ? round(((int) $row->count / $totalTickets) * 100, 1) : 0.0,
            ])
            ->values();

        $selectedMonthStatuses = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $selectedMonthPriorities = $this->buildPriorityBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $selectedMonthCategories = $this->buildCategoryBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $monthOptions = $monthlyReportRows
            ->map(fn (array $row) => [
                'key' => $row['month_key'],
                'label' => $row['month_label'],
            ])
            ->reverse()
            ->values();
        $detailResetMonthKey = $this->resolveSelectedMonthKey(
            now()->format('Y-m'),
            $monthlyReportRows,
            $selectedMonthKey
        );
        $detailResetMonthRange = $this->monthRangeFromKey($detailResetMonthKey);
        $detailResetDate = now()->startOfDay();
        if (
            $detailResetDate->lt($detailResetMonthRange['start']->copy()->startOfDay())
            || $detailResetDate->gt($detailResetMonthRange['end']->copy()->startOfDay())
        ) {
            $detailResetDate = $detailResetMonthRange['end']->copy()->startOfDay();
        }
        $detailClearParams = [
            'month' => $detailResetMonthKey,
            'daily_month' => $detailResetMonthKey,
            'daily_date' => $detailResetDate->toDateString(),
            'detail_month' => $detailResetMonthKey,
            'detail_date' => $detailResetDate->toDateString(),
            'apply_details_filter' => 1,
        ];
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
            $ticketTrend->push([
                'date' => $dateLabel,
                'count' => (int) ($trendCounts[$dateLabel] ?? 0),
            ]);
        }

        $topTechnicianScopedTickets = clone $scopedTickets;
        if ($detailFilterApplied) {
            $topTechnicianScopedTickets->whereBetween('tickets.created_at', [$detailScopeStart, $detailScopeEnd]);
        }

        $topTechnicianRows = (clone $topTechnicianScopedTickets)
            ->whereNotNull('assigned_to')
            ->join('users', 'tickets.assigned_to', '=', 'users.id')
            ->where('users.role', '!=', User::ROLE_SHADOW)
            ->selectRaw("tickets.assigned_to, users.name as technician_name, COUNT(*) as total_tickets, SUM(CASE WHEN tickets.status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_tickets")
            ->groupBy('tickets.assigned_to', 'users.name')
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

        return view('admin.reports.index', compact(
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
            'selectedMonthRow',
            'selectedMonthStatuses',
            'selectedMonthPriorities',
            'selectedMonthCategories',
            'periodOverview',
            'majorIssueSummary',
            'keyInsights',
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
            'detailMajorIssueSummary',
            'previousMonthRow',
            'ticketHistoryScope',
            'detailClearParams'
        ));
    }

    public function monthlyPdf(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows] = $this->monthlyReportDatasets->build(clone $scopedTickets);
        $selectedMonthKey = $this->resolveSelectedMonthKey($request->query('month'), $monthlyReportRows);
        $selectedMonthRange = $this->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
            ?? $this->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange);

        $statusBreakdown = $this->buildStatusBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $priorityBreakdown = $this->buildPriorityBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );
        $categoryBreakdown = $this->buildCategoryBreakdownForPeriod(
            clone $scopedTickets,
            $selectedMonthRange['start'],
            $selectedMonthRange['end']
        );

        $pdf = Pdf::loadView('admin.reports.monthly-pdf', [
            'generatedAt' => now(),
            'selectedMonthKey' => $selectedMonthKey,
            'selectedMonthRow' => $selectedMonthRow,
            'selectedMonthRange' => $selectedMonthRange,
            'statusBreakdown' => $statusBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'monthlyReportRows' => $monthlyReportRows->reverse()->values(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('ticket-monthly-report-'.$selectedMonthKey.'.pdf');
    }

    /**
     * @return Builder<Ticket>
     */
    private function scopedTicketQueryForUser(User $user): Builder
    {
        $query = Ticket::query();

        if ($user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private function buildKpiSummary(Builder $scopedTickets): array
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $closedStatusesSqlList = "'".implode("','", Ticket::CLOSED_STATUSES)."'";

        $summary = (clone $scopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$closedStatusesSqlList}) THEN 1 ELSE 0 END) as closed_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_open_tickets")
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) AND priority = 'urgent' THEN 1 ELSE 0 END) as urgent_open_tickets")
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

    private function countResolvedTicketsWithinRange(Builder $scopedTickets, Carbon $start, Carbon $end): int
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->resolvedTicketCountCache)) {
            return $this->resolvedTicketCountCache[$cacheKey];
        }

        $count = (clone $scopedTickets)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('resolved_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->count();

        $this->resolvedTicketCountCache[$cacheKey] = (int) $count;

        return (int) $count;
    }

    private function resolveSelectedMonthKey(
        mixed $requestedMonth,
        Collection $monthlyReportRows,
        mixed $preferredFallbackMonth = null
    ): string {
        $availableMonths = $monthlyReportRows->pluck('month_key')->all();
        $fallbackMonth = ! empty($availableMonths)
            ? (string) end($availableMonths)
            : now()->format('Y-m');

        $preferredFallback = $this->normalizeMonthKey($preferredFallbackMonth);
        if ($preferredFallback && in_array($preferredFallback, $availableMonths, true)) {
            $fallbackMonth = $preferredFallback;
        }

        $normalized = $this->normalizeMonthKey($requestedMonth);
        if (! $normalized) {
            return $fallbackMonth;
        }

        return in_array($normalized, $availableMonths, true)
            ? $normalized
            : $fallbackMonth;
    }

    private function normalizeMonthKey(mixed $monthKey): ?string
    {
        if (! is_string($monthKey)) {
            return null;
        }

        $normalized = trim($monthKey);
        if (! preg_match('/^\d{4}-\d{2}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function monthRangeFromKey(string $monthKey): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $start->copy()->endOfMonth()->endOfDay(),
            'label' => $start->format('M Y'),
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

    private function buildPriorityBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
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

        $this->priorityBreakdownCache[$cacheKey] = $breakdown;

        return $breakdown;
    }

    private function buildCategoryBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
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

    private function emptyMonthlyReportRow(string $monthKey, array $monthRange): array
    {
        return [
            'month_key' => $monthKey,
            'month_label' => (string) ($monthRange['label'] ?? $monthKey),
            'month_start' => isset($monthRange['start']) ? $monthRange['start']->toDateString() : null,
            'month_end' => isset($monthRange['end']) ? $monthRange['end']->toDateString() : null,
            'received' => 0,
            'resolved' => 0,
            'open_end_of_month' => 0,
            'resolution_rate' => 0.0,
        ];
    }

    private function percentageChange(int $current, int $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function buildSlaMetricsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->slaMetricsCache)) {
            return $this->slaMetricsCache[$cacheKey];
        }

        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $scopedTickets)
            ->toBase()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('resolved_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->selectRaw('SUM(CASE WHEN due_date IS NOT NULL THEN 1 ELSE 0 END) as eligible')
            ->selectRaw('SUM(CASE WHEN due_date IS NOT NULL AND COALESCE(resolved_at, closed_at) <= due_date THEN 1 ELSE 0 END) as met')
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];
        $eligible = (int) ($summaryRow['eligible'] ?? 0);
        $met = (int) ($summaryRow['met'] ?? 0);

        $metrics = [
            'eligible' => $eligible,
            'met' => $met,
            'rate' => $eligible > 0 ? round(($met / $eligible) * 100, 1) : 0.0,
        ];

        $this->slaMetricsCache[$cacheKey] = $metrics;

        return $metrics;
    }

    private function buildMajorIssueSummary(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $cacheKey = $this->queryScopeSignature($scopedTickets)
            .'|'.$start->toIso8601String()
            .'|'.$end->toIso8601String();
        if (array_key_exists($cacheKey, $this->majorIssueSummaryCache)) {
            return $this->majorIssueSummaryCache[$cacheKey];
        }

        $incidentScopedTickets = (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->whereIn('priority', ['urgent', 'high']);

        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $incidentScopedTickets)
            ->toBase()
            ->selectRaw('COUNT(*) as major_count')
            ->selectRaw("SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_total")
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

        $summary = [
            'major_count' => (int) ($summaryRow['major_count'] ?? 0),
            'open_major_count' => (int) ($summaryRow['open_major_count'] ?? 0),
            'urgent_total' => (int) ($summaryRow['urgent_total'] ?? 0),
            'top_categories' => $incidentByCategory,
        ];

        $this->majorIssueSummaryCache[$cacheKey] = $summary;

        return $summary;
    }

    private function buildImprovementAndRiskSummary(array $metrics): array
    {
        $improvements = [];
        $risks = [];

        $completionDelta = round(($metrics['current_completion_rate'] ?? 0) - ($metrics['previous_completion_rate'] ?? 0), 1);
        $slaDelta = round(($metrics['current_sla_rate'] ?? 0) - ($metrics['previous_sla_rate'] ?? 0), 1);
        $backlogDelta = (int) ($metrics['current_backlog'] ?? 0) - (int) ($metrics['previous_backlog'] ?? 0);
        $pendingDelta = (int) ($metrics['current_pending'] ?? 0) - (int) ($metrics['previous_pending'] ?? 0);
        $urgentDelta = (int) ($metrics['current_urgent'] ?? 0) - (int) ($metrics['previous_urgent'] ?? 0);
        $ticketDelta = (int) ($metrics['current_total'] ?? 0) - (int) ($metrics['previous_total'] ?? 0);

        if ($completionDelta > 0) {
            $improvements[] = 'Completion rate improved by '.number_format($completionDelta, 1).' percentage points.';
        }
        if ($slaDelta > 0) {
            $improvements[] = 'SLA compliance improved by '.number_format($slaDelta, 1).' percentage points.';
        }
        if ($backlogDelta < 0) {
            $improvements[] = 'Backlog reduced by '.abs($backlogDelta).' tickets.';
        }
        if ($ticketDelta > 0 && $backlogDelta <= 0) {
            $improvements[] = 'Handled higher ticket volume without increasing backlog.';
        }

        if ($completionDelta < 0) {
            $risks[] = 'Completion rate dropped by '.number_format(abs($completionDelta), 1).' percentage points.';
        }
        if ($slaDelta < 0) {
            $risks[] = 'SLA compliance dropped by '.number_format(abs($slaDelta), 1).' percentage points.';
        }
        if ($backlogDelta > 0) {
            $risks[] = 'Backlog increased by '.$backlogDelta.' tickets.';
        }
        if ($pendingDelta > 0) {
            $risks[] = 'Pending queue grew by '.$pendingDelta.' tickets.';
        }
        if ($urgentDelta > 0) {
            $risks[] = 'Urgent/high priority incidents increased by '.$urgentDelta.'.';
        }

        if ($improvements === []) {
            $improvements[] = 'No major positive trend detected for this period.';
        }
        if ($risks === []) {
            $risks[] = 'No major operational risk spike detected for this period.';
        }

        return [
            'improvements' => $improvements,
            'risks' => $risks,
        ];
    }

    private function buildCategoryBucketsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        return $this->reportBreakdowns->buildCategoryBucketsForScope(clone $scopedTickets, $start, $end);
    }

    private function buildPriorityBucketsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        return $this->reportBreakdowns->buildPriorityBucketsForScope(clone $scopedTickets, $start, $end);
    }

    private function buildDailyVolumeSeries(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
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

    private function buildWeeklyVolumeSeries(Builder $scopedTickets, int $weeks = 12): Collection
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

    private function buildDailyTicketStatisticsForDate(Builder $scopedTickets, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $received = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $inProgress = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'in_progress')
            ->count();

        return [
            'mode' => 'day',
            'date' => $start->toDateString(),
            'label' => $start->format('M j, Y'),
            'received' => $received,
            'in_progress' => $inProgress,
            'resolved' => $this->countResolvedTicketsWithinRange(clone $scopedTickets, $start, $end),
        ];
    }

    private function buildDailyTicketStatisticsForRange(
        Builder $scopedTickets,
        Carbon $start,
        Carbon $end,
        string $rangeLabel
    ): array {
        $received = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $inProgress = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'in_progress')
            ->count();

        return [
            'mode' => 'month',
            'date' => null,
            'label' => 'All days in '.$rangeLabel,
            'received' => $received,
            'in_progress' => $inProgress,
            'resolved' => $this->countResolvedTicketsWithinRange(clone $scopedTickets, $start, $end),
        ];
    }

    private function resolveRequestedDate(mixed $requestedDate): ?Carbon
    {
        if (! is_string($requestedDate)) {
            return null;
        }

        $normalized = trim($requestedDate);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function queryScopeSignature(Builder $scopedTickets): string
    {
        return sha1($scopedTickets->toSql().'|'.json_encode($scopedTickets->getBindings()));
    }

    private function buildDateOptionsForRange(Carbon $start, Carbon $end): Collection
    {
        $options = collect();
        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->startOfDay();

        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
            $options->push([
                'value' => $cursor->toDateString(),
                'label' => $cursor->format('M j, Y'),
            ]);
        }

        return $options->reverse()->values();
    }
}
