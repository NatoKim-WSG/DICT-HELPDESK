<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'total_tickets' => Ticket::count(),
            'open_tickets' => Ticket::open()->count(),
            'closed_tickets' => Ticket::closed()->count(),
            'overdue_tickets' => Ticket::where('due_date', '<', now())->open()->count(),
            'attention_tickets' => Ticket::whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16))
                ->count(),
            'urgent_tickets' => Ticket::byPriority('urgent')->open()->count(),
            'total_users' => User::where('role', User::ROLE_CLIENT)->count(),
            'total_staff' => User::whereIn('role', User::TICKET_CONSOLE_ROLES)->count(),
            'assigned_to_me' => Ticket::where('assigned_to', $user->id)
                ->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->count(),
        ];

        $recentTickets = Ticket::with(['user', 'category', 'assignedUser'])
            ->latest()
            ->take(20)
            ->get();

        $ticketsByStatus = Ticket::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $ticketsByPriority = Ticket::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority');

        $ticketsTrend = Ticket::selectRaw('DATE(created_at) as date, COUNT(*) as count')
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
            'isTechnical'
        ));
    }
}
