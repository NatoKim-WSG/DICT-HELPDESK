<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ReportController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        [$monthlyReportRows, $monthlyGraphPoints] = $this->buildMonthlyReportData(clone $scopedTickets);
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
            'monthlyGraphPoints'
        ));
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
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at'])
            ->map(function (Ticket $ticket) {
                if (! $ticket->created_at || ! $ticket->resolved_at) {
                    return null;
                }

                return $ticket->created_at->diffInMinutes($ticket->resolved_at);
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

            $resolvedCount = (clone $scopedTickets)
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$month['start'], $month['end']])
                ->count();

            $openAtMonthEnd = $this->countOpenTicketsAtCutoff(clone $scopedTickets, $month['end']);

            return [
                'month_key' => $month['key'],
                'month_label' => $month['label'],
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
            ->count();
    }
}
