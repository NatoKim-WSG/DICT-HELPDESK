<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'total_tickets' => $user->tickets()->count(),
            'open_tickets' => $user->tickets()->open()->count(),
            'closed_tickets' => $user->tickets()->closed()->count(),
            'urgent_tickets' => $user->tickets()->byPriority('urgent')->open()->count(),
        ];

        $recentTickets = $user->tickets()
            ->with(['category', 'assignedUser'])
            ->latest()
            ->take(5)
            ->get();

        return view('client.dashboard', compact('stats', 'recentTickets'));
    }
}