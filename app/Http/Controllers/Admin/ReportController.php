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
            ->selectRaw("assigned_to, COUNT(*) as total_tickets, SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) as resolved_tickets")
            ->groupBy('assigned_to')
            ->orderByDesc('total_tickets')
            ->take(5)
            ->get();

        $technicianDirectory = User::query()
            ->whereIn('id', $topTechnicianRows->pluck('assigned_to')->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $topTechnicians = $topTechnicianRows
            ->map(function ($row) use ($technicianDirectory) {
                $technician = $technicianDirectory->get((int) $row->assigned_to);

                return [
                    'name' => $technician?->name ?? 'Unknown technical user',
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
            'selectedMonthCategories'
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
}
