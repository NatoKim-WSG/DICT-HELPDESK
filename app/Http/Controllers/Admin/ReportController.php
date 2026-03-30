<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\ReportBreakdownService;
use App\Services\Admin\Reports\MonthlyReportDatasetService;
use App\Services\Admin\Reports\ReportScopeService;
use App\Services\Admin\Reports\ReportStatisticsService;
use App\Services\Admin\Reports\SlaReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportBreakdownService $reportBreakdowns,
        private MonthlyReportDatasetService $monthlyReportDatasets,
        private ReportScopeService $reportScopes,
        private ReportStatisticsService $reportStatistics,
        private SlaReportService $slaReports,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
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

        $periodStatusSummary = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildReportStatusBreakdownForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildReportStatusBreakdownForPeriod(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
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
            $mixStatusSummary = $this->reportStatistics->buildReportStatusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
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
            $mixStatusSummary = $this->reportStatistics->buildReportStatusBreakdownForAllTime(clone $scopedTickets);
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

        $detailStatusSummary = $this->reportStatistics->buildReportStatusBreakdownForPeriod(clone $scopedTickets, $detailScopeStart, $detailScopeEnd);
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

        $selectedMonthStatuses = $selectedMonthIsAllTime
            ? $this->reportStatistics->buildReportStatusBreakdownForAllTime(clone $scopedTickets)
            : $this->reportStatistics->buildReportStatusBreakdownForPeriod(clone $scopedTickets, $selectedMonthRange['start'], $selectedMonthRange['end']);
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

        $viewData = compact(
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
            'slaReport'
        );

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.reports.partials.shell', $viewData)->render(),
            ]);
        }

        return view('admin.reports.index', $viewData);
    }

    public function monthlyPdf(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows] = $this->monthlyReportDatasets->build(clone $scopedTickets);
        $selectedMonthKey = $this->reportScopes->resolveSelectedMonthKey($request->query('month'), $monthlyReportRows);
        $selectedMonthRange = $this->reportScopes->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
            ?? $this->reportScopes->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange);

        $pdf = Pdf::loadView('admin.reports.monthly-pdf', [
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
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query;
    }
}
