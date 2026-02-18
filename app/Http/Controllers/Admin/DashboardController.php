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
        $stats = [
            'total_tickets' => Ticket::count(),
            'open_tickets' => Ticket::open()->count(),
            'closed_tickets' => Ticket::closed()->count(),
            'overdue_tickets' => Ticket::where('due_date', '<', now())->open()->count(),
            'urgent_tickets' => Ticket::byPriority('urgent')->open()->count(),
            'total_users' => User::where('role', 'client')->count(),
            'total_admins' => User::whereIn('role', ['admin', 'super_admin'])->count(),
        ];

        $recentTickets = Ticket::with(['user', 'category', 'assignedUser'])
            ->latest()
            ->take(10)
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

        return view('admin.dashboard', compact(
            'stats',
            'recentTickets',
            'ticketsByStatus',
            'ticketsByPriority',
            'ticketsTrend'
        ));
    }
}