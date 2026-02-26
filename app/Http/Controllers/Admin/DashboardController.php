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
            'closed_tickets' => (clone $scopedTickets)->closed()->count(),
            'overdue_tickets' => (clone $scopedTickets)->where('due_date', '<', now())->open()->count(),
            'attention_tickets' => (clone $scopedTickets)->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16))
                ->count(),
            'urgent_tickets' => (clone $scopedTickets)->byPriority('urgent')->open()->count(),
            'total_users' => User::query()->visibleDirectory()->count(),
            'technical_users' => User::query()->where('role', User::ROLE_TECHNICAL)->count(),
            'client_users' => User::query()->where('role', User::ROLE_CLIENT)->count(),
        ];

        $recentTickets = (clone $scopedTickets)->with(['user', 'category', 'assignedUser'])
            ->latest()
            ->take(20)
            ->get();

        $ticketsByStatus = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $ticketsByPriority = (clone $scopedTickets)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority');

        $ticketsTrend = (clone $scopedTickets)->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $isTechnical = $user->isTechnician();
        $dashboardTitle = $isTechnical ? 'Technical Dashboard' : 'Support Dashboard';
        $dashboardSubtitle = $isTechnical
            ? 'Monitor assigned work, aging tickets, and priorities from one place.'
            : 'Operational overview, ticket health, and quick actions in one place.';

        return view('admin.dashboard', compact(
            'stats',
            'recentTickets',
            'ticketsByStatus',
            'ticketsByPriority',
            'ticketsTrend',
            'dashboardTitle',
            'dashboardSubtitle',
            'isTechnical',
            'liveSnapshotToken'
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
