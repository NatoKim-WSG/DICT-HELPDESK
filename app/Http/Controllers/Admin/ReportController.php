<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows, $monthlyGraphPoints] = $this->buildMonthlyReportData(clone $scopedTickets);
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

        $totalTickets = (clone $scopedTickets)->count();
        $resolvedTicketsCount = (clone $scopedTickets)
            ->whereIn('status', Ticket::CLOSED_STATUSES)
            ->count();

        $stats = [
            'total_tickets' => $totalTickets,
            'open_tickets' => (clone $scopedTickets)->open()->count(),
            'closed_tickets' => $resolvedTicketsCount,
            'unassigned_open_tickets' => (clone $scopedTickets)
                ->open()
                ->whereNull('assigned_to')
                ->count(),
            'urgent_open_tickets' => (clone $scopedTickets)
                ->open()
                ->where('priority', 'urgent')
                ->count(),
            'resolution_rate' => $totalTickets > 0
                ? round(($resolvedTicketsCount / $totalTickets) * 100, 1)
                : 0,
            'average_resolution_minutes' => $this->averageResolutionMinutes((clone $scopedTickets)),
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
        $backlogThisPeriod = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $selectedPeriodEnd);
        $backlogPreviousPeriod = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $previousPeriodEnd);
        $slaThisPeriod = $this->buildSlaMetricsForPeriod(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
        $slaPreviousPeriod = $this->buildSlaMetricsForPeriod(clone $scopedTickets, $previousPeriodStart, $previousPeriodEnd);
        $majorIssueSummary = $this->buildMajorIssueSummary(clone $scopedTickets, $selectedPeriodStart, $selectedPeriodEnd);
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
            'previous_urgent' => $this->buildMajorIssueSummary(clone $scopedTickets, $previousPeriodStart, $previousPeriodEnd)['urgent_total'] ?? 0,
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

        $categoryBreakdownBuckets = $this->buildCategoryBucketsForPeriod(
            clone $scopedTickets,
            $selectedPeriodStart,
            $selectedPeriodEnd
        );
        $priorityBreakdownBuckets = $this->buildPriorityBucketsForPeriod(
            clone $scopedTickets,
            $selectedPeriodStart,
            $selectedPeriodEnd
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
        $dailyTicketStatistics = $this->buildDailyTicketStatisticsForPeriod(
            clone $scopedTickets,
            $selectedPeriodStart,
            $selectedPeriodEnd
        );

        $ticketsByStatus = (clone $scopedTickets)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $ticketsByPriority = (clone $scopedTickets)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $ticketsByCategory = (clone $scopedTickets)
            ->with('category:id,name')
            ->get()
            ->groupBy(fn (Ticket $ticket) => optional($ticket->category)->name ?? 'Uncategorized')
            ->map(fn ($tickets, $categoryName) => [
                'name' => $categoryName,
                'count' => $tickets->count(),
                'share' => $totalTickets > 0 ? round(($tickets->count() / $totalTickets) * 100, 1) : 0,
            ])
            ->sortByDesc('count')
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
        $monthlyReportRowsDescending = $monthlyReportRows->reverse()->values();

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

        $topTechnicianRows = (clone $scopedTickets)
            ->whereNotNull('assigned_to')
            ->whereHas('assignedUser', function ($query) {
                $query->visibleDirectory();
            })
            ->selectRaw("assigned_to, COUNT(*) as total_tickets, SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_tickets")
            ->groupBy('assigned_to')
            ->orderByDesc('total_tickets')
            ->take(5)
            ->get();

        $technicianDirectory = User::query()
            ->visibleDirectory()
            ->whereIn('id', $topTechnicianRows->pluck('assigned_to')->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $topTechnicians = $topTechnicianRows
            ->map(function ($row) use ($technicianDirectory) {
                $technician = $technicianDirectory->get((int) $row->assigned_to);

                return [
                    'name' => $technician?->publicDisplayName() ?? 'Unknown technical user',
                    'total_tickets' => (int) $row->total_tickets,
                    'resolved_tickets' => (int) $row->resolved_tickets,
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
            'monthlyReportRowsDescending',
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
            'dailyTicketStatistics',
            'previousMonthRow'
        ));
    }

    public function monthlyPdf(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows] = $this->buildMonthlyReportData(clone $scopedTickets);
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

    private function scopedTicketQueryForUser(User $user): Builder
    {
        $query = Ticket::query();

        if ($user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private function averageResolutionMinutes(Builder $scopedTickets): int
    {
        $durations = $scopedTickets
            ->where(function ($query) {
                $query->whereNotNull('resolved_at')
                    ->orWhereNotNull('closed_at');
            })
            ->get(['created_at', 'resolved_at', 'closed_at'])
            ->map(function (Ticket $ticket) {
                $completedAt = $ticket->resolved_at ?? $ticket->closed_at;
                if (! $ticket->created_at || ! $completedAt) {
                    return null;
                }

                return $ticket->created_at->diffInMinutes($completedAt);
            })
            ->filter();

        if ($durations->isEmpty()) {
            return 0;
        }

        return (int) round($durations->avg());
    }

    private function buildMonthlyReportData(Builder $scopedTickets): array
    {
        $startMonth = Carbon::now()->startOfMonth()->subMonths(11);
        $endMonth = Carbon::now()->startOfMonth();

        $monthDefinitions = collect();
        for ($cursor = $startMonth->copy(); $cursor->lte($endMonth); $cursor->addMonth()) {
            $monthDefinitions->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'start' => $cursor->copy()->startOfMonth(),
                'end' => $cursor->copy()->endOfMonth(),
            ]);
        }

        $reportRows = $monthDefinitions->map(function (array $month) use ($scopedTickets) {
            $receivedCount = (clone $scopedTickets)
                ->whereBetween('created_at', [$month['start'], $month['end']])
                ->count();

            $resolvedCount = $this->countResolvedTicketsWithinRange(
                clone $scopedTickets,
                $month['start'],
                $month['end']
            );

            $openAtMonthEnd = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $month['end']);

            return [
                'month_key' => $month['key'],
                'month_label' => $month['label'],
                'month_start' => $month['start']->toDateString(),
                'month_end' => $month['end']->toDateString(),
                'received' => $receivedCount,
                'resolved' => $resolvedCount,
                'open_end_of_month' => $openAtMonthEnd,
                'resolution_rate' => $receivedCount > 0
                    ? round(($resolvedCount / $receivedCount) * 100, 1)
                    : 0.0,
            ];
        });

        $graphPoints = $reportRows->map(fn (array $row) => [
            'key' => $row['month_key'],
            'month_label' => $row['month_label'],
            'label' => Carbon::createFromFormat('Y-m', $row['month_key'])->format('M'),
            'received' => $row['received'],
            'resolved' => $row['resolved'],
            'resolution_rate' => $row['resolution_rate'],
        ]);

        return [$reportRows, $graphPoints];
    }

    private function countOpenTicketsAtCutoff(Builder $scopedTickets, Carbon $cutoff): int
    {
        return (clone $scopedTickets)
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
    }

    private function countResolvedTicketsWithinRange(Builder $scopedTickets, Carbon $start, Carbon $end): int
    {
        return (clone $scopedTickets)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('resolved_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->count();
    }

    private function resolveSelectedMonthKey(mixed $requestedMonth, Collection $monthlyReportRows): string
    {
        $availableMonths = $monthlyReportRows->pluck('month_key')->all();
        $fallbackMonth = ! empty($availableMonths)
            ? (string) end($availableMonths)
            : now()->format('Y-m');

        if (! is_string($requestedMonth)) {
            return $fallbackMonth;
        }

        $normalized = trim($requestedMonth);

        return in_array($normalized, $availableMonths, true)
            ? $normalized
            : $fallbackMonth;
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
        $statusCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(Ticket::STATUSES)->mapWithKeys(fn (string $status) => [
            $status => (int) ($statusCounts[$status] ?? 0),
        ])->all();
    }

    private function buildPriorityBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $priorityCounts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return collect(Ticket::PRIORITIES)->mapWithKeys(fn (string $priority) => [
            $priority => (int) ($priorityCounts[$priority] ?? 0),
        ])->all();
    }

    private function buildCategoryBreakdownForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $tickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->with('category:id,name')
            ->get();

        $total = max(1, $tickets->count());

        return $tickets
            ->groupBy(fn (Ticket $ticket) => optional($ticket->category)->name ?? 'Uncategorized')
            ->map(function ($groupedTickets, $categoryName) use ($total) {
                $count = $groupedTickets->count();

                return [
                    'name' => (string) $categoryName,
                    'count' => $count,
                    'share' => round(($count / $total) * 100, 1),
                ];
            })
            ->sortByDesc('count')
            ->values();
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
        $completedTickets = (clone $scopedTickets)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('resolved_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->get(['resolved_at', 'closed_at', 'due_date']);

        $eligible = 0;
        $met = 0;

        foreach ($completedTickets as $ticket) {
            if (! $ticket->due_date) {
                continue;
            }

            $eligible++;
            $completedAt = $ticket->resolved_at ?? $ticket->closed_at;
            if ($completedAt && $completedAt->lte($ticket->due_date)) {
                $met++;
            }
        }

        return [
            'eligible' => $eligible,
            'met' => $met,
            'rate' => $eligible > 0 ? round(($met / $eligible) * 100, 1) : 0.0,
        ];
    }

    private function buildMajorIssueSummary(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $incidentTickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('priority', ['urgent', 'high'])
            ->with('category:id,name')
            ->get(['id', 'priority', 'status', 'category_id']);

        $majorCount = $incidentTickets->count();
        $urgentTotal = $incidentTickets->where('priority', 'urgent')->count();
        $openMajorCount = $incidentTickets->whereIn('status', Ticket::OPEN_STATUSES)->count();

        $incidentByCategory = $incidentTickets
            ->groupBy(fn (Ticket $ticket) => $this->normalizeCategoryBucket(optional($ticket->category)->name))
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(3);

        return [
            'major_count' => $majorCount,
            'open_major_count' => $openMajorCount,
            'urgent_total' => $urgentTotal,
            'top_categories' => $incidentByCategory,
        ];
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
        $bucketOrder = ['Hardware', 'Software', 'Network', 'Access / Permissions', 'Security', 'Other'];
        $bucketCounts = array_fill_keys($bucketOrder, 0);

        $tickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->with('category:id,name')
            ->get(['id', 'category_id']);

        foreach ($tickets as $ticket) {
            $bucket = $this->normalizeCategoryBucket(optional($ticket->category)->name);
            $bucketCounts[$bucket] = ($bucketCounts[$bucket] ?? 0) + 1;
        }

        return collect($bucketCounts)->map(fn (int $count, string $name) => [
            'name' => $name,
            'count' => $count,
        ])->values()->all();
    }

    private function normalizeCategoryBucket(?string $categoryName): string
    {
        $value = strtolower(trim((string) $categoryName));

        if ($value === '') {
            return 'Other';
        }

        if (str_contains($value, 'hardware')) {
            return 'Hardware';
        }

        if (str_contains($value, 'software') || str_contains($value, 'application')) {
            return 'Software';
        }

        if (str_contains($value, 'network') || str_contains($value, 'connect')) {
            return 'Network';
        }

        if (str_contains($value, 'access') || str_contains($value, 'permission') || str_contains($value, 'account')) {
            return 'Access / Permissions';
        }

        if (str_contains($value, 'security')) {
            return 'Security';
        }

        return 'Other';
    }

    private function buildPriorityBucketsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $counts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return [
            ['name' => 'Critical', 'count' => (int) ($counts['urgent'] ?? 0)],
            ['name' => 'High', 'count' => (int) ($counts['high'] ?? 0)],
            ['name' => 'Medium', 'count' => (int) ($counts['medium'] ?? 0)],
            ['name' => 'Low', 'count' => (int) ($counts['low'] ?? 0)],
        ];
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

    private function buildDailyTicketStatisticsForPeriod(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $receivedCounts = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as stat_date, COUNT(*) as count')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('stat_date')
            ->pluck('count', 'stat_date');

        $inProgressCounts = (clone $scopedTickets)
            ->selectRaw('DATE(created_at) as stat_date, COUNT(*) as count')
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'in_progress')
            ->groupBy('stat_date')
            ->pluck('count', 'stat_date');

        $resolvedCounts = [];
        $resolvedTickets = (clone $scopedTickets)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('resolved_at', [$start, $end])
                    ->orWhereBetween('closed_at', [$start, $end]);
            })
            ->get(['resolved_at', 'closed_at']);

        foreach ($resolvedTickets as $ticket) {
            $completedAt = $ticket->resolved_at ?? $ticket->closed_at;
            if (! $completedAt) {
                continue;
            }

            $dateKey = $completedAt->toDateString();
            $resolvedCounts[$dateKey] = ($resolvedCounts[$dateKey] ?? 0) + 1;
        }

        $rows = collect();
        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();
            $rows->push([
                'date' => $dateKey,
                'label' => $cursor->format('M j, Y'),
                'received' => (int) ($receivedCounts[$dateKey] ?? 0),
                'in_progress' => (int) ($inProgressCounts[$dateKey] ?? 0),
                'resolved' => (int) ($resolvedCounts[$dateKey] ?? 0),
            ]);
        }

        return $rows->reverse()->values();
    }
}
