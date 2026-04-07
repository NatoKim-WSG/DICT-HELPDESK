<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $scopedTickets = $this->scopedTicketQueryForUser($user);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $liveSnapshotToken = $this->buildDashboardSnapshotToken(clone $scopedTickets);

        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $stats = [
            'total_tickets' => (clone $scopedTickets)->count(),
            'open_tickets' => (clone $scopedTickets)->open()->count(),
            'attention_tickets' => (clone $scopedTickets)->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16))
                ->count(),
            'urgent_tickets' => (clone $scopedTickets)->byPriority('severity_1')->open()->count(),
        ];

        $recentStart = now()->subDays(10)->startOfDay();
        $recentEnd = now()->endOfDay();

        $recentTickets = (clone $scopedTickets)
            ->whereBetween('created_at', [$recentStart, $recentEnd])
            ->with(['user', 'category', 'assignedUser', 'assignedUsers'])
            ->latest()
            ->take(20)
            ->get();

        $ticketsByStatus = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
        $ticketsByStatusDisplay = collect([
            'open' => (int) ($ticketsByStatus->get('open', 0)),
            'in_progress' => (int) ($ticketsByStatus->get('in_progress', 0)),
            'pending' => (int) ($ticketsByStatus->get('pending', 0)),
            'resolved' => (int) ($ticketsByStatus->get('resolved', 0)) + (int) ($ticketsByStatus->get('closed', 0)),
            'closed' => (int) ($ticketsByStatus->get('closed', 0)),
        ]);

        $ticketsByPriority = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority');
        $ticketsByPriority = collect([
            '__pending__' => (int) ($ticketsByPriority[''] ?? $ticketsByPriority[null] ?? 0),
            'severity_1' => (int) ($ticketsByPriority['severity_1'] ?? 0),
            'severity_2' => (int) ($ticketsByPriority['severity_2'] ?? 0),
            'severity_3' => (int) ($ticketsByPriority['severity_3'] ?? 0),
        ]);

        $ticketsTrend = (clone $scopedTickets)->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $isTechnical = $user->isTechnician();
        $dashboardTitle = $isTechnical ? 'Technical Dashboard' : 'Support Dashboard';
        $dashboardSubtitle = $isTechnical
            ? 'Monitor assigned work, aging tickets, and severities from one place.'
            : 'Operational overview, ticket health, and quick actions in one place.';

        return view('admin.dashboard', compact(
            'stats',
            'recentTickets',
            'ticketsByStatus',
            'ticketsByStatusDisplay',
            'ticketsByPriority',
            'ticketsTrend',
            'dashboardTitle',
            'dashboardSubtitle',
            'isTechnical',
            'liveSnapshotToken'
        ));
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
     * @param  Builder<Ticket>  $scopedTickets
     */
    private function buildDashboardSnapshotToken(Builder $scopedTickets): string
    {
        $latestUpdatedAt = (clone $scopedTickets)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;
        $totalTickets = (clone $scopedTickets)->count();

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total' => $totalTickets,
        ]));
    }
}
