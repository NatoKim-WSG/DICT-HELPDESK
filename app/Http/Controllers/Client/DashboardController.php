<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        /** @var Builder<Ticket> $ticketQuery */
        $ticketQuery = $user->tickets()->getQuery();
        $liveSnapshotToken = $this->buildLiveSnapshotToken(clone $ticketQuery);

        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $stats = [
            'total_tickets' => (clone $ticketQuery)->count(),
            'open_tickets' => (clone $ticketQuery)->open()->count(),
            'in_progress_tickets' => (clone $ticketQuery)->where('status', 'in_progress')->count(),
            'severity_one_tickets' => (clone $ticketQuery)->byPriority('severity_1')->open()->count(),
        ];

        $recentStart = now()->subDays(10)->startOfDay();
        $recentEnd = now()->endOfDay();

        $recentTickets = (clone $ticketQuery)
            ->whereBetween('created_at', [$recentStart, $recentEnd])
            ->with(['category', 'assignedUser', 'assignedUsers'])
            ->latest()
            ->take(5)
            ->get();

        return view('client.dashboard', compact('stats', 'recentTickets', 'liveSnapshotToken'));
    }

    /**
     * @param  Builder<Ticket>  $ticketQuery
     */
    private function buildLiveSnapshotToken(Builder $ticketQuery): string
    {
        $latestUpdatedAt = (clone $ticketQuery)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;
        $totalTickets = (clone $ticketQuery)->count();

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total_tickets' => $totalTickets,
        ]));
    }
}
