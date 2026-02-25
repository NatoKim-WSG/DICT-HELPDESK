<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $ticketQuery = $user->tickets();
        $liveSnapshotToken = $this->buildLiveSnapshotToken(clone $ticketQuery);

        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $stats = [
            'total_tickets' => (clone $ticketQuery)->count(),
            'open_tickets' => (clone $ticketQuery)->open()->count(),
            'closed_tickets' => (clone $ticketQuery)->closed()->count(),
            'urgent_tickets' => (clone $ticketQuery)->byPriority('urgent')->open()->count(),
        ];

        $recentTickets = (clone $ticketQuery)
            ->with(['category', 'assignedUser'])
            ->latest()
            ->take(5)
            ->get();

        return view('client.dashboard', compact('stats', 'recentTickets', 'liveSnapshotToken'));
    }

    private function buildLiveSnapshotToken(Builder|HasMany $ticketQuery): string
    {
        $latestUpdatedAt = (clone $ticketQuery)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'open_tickets' => (clone $ticketQuery)->open()->count(),
            'total_tickets' => (clone $ticketQuery)->count(),
        ]));
    }
}
