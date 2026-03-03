<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketUserState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function clientDismiss(Request $request): RedirectResponse
    {
        $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
            'activity_at' => 'required|date',
        ]);

        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $activityAt = Carbon::parse($request->string('activity_at')->toString());
        $state = TicketUserState::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => (int) auth()->id(),
        ]);

        if (! $state->exists || ! $state->hasViewedActivity($activityAt)) {
            return back()->with('error', 'You can dismiss notifications only after viewing them.');
        }

        $state->dismissed_at = $activityAt;
        $state->save();
        TicketUserState::forgetHeaderNotificationCacheForUser((int) auth()->id());

        return back();
    }

    public function clientClear(Request $request): JsonResponse|RedirectResponse
    {
        $ticketIds = Ticket::query()
            ->where('user_id', (int) auth()->id())
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return $request->expectsJson()
                ? response()->json(['ok' => true])
                : back();
        }

        $now = now();
        $rows = $ticketIds->map(fn ($ticketId) => [
            'ticket_id' => (int) $ticketId,
            'user_id' => (int) auth()->id(),
            'last_seen_at' => $now,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        TicketUserState::upsert(
            $rows,
            ['ticket_id', 'user_id'],
            ['last_seen_at', 'dismissed_at', 'updated_at']
        );
        TicketUserState::forgetHeaderNotificationCacheForUser((int) auth()->id());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function clientSeen(Request $request, Ticket $ticket): JsonResponse
    {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->input('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    }

    public function clientOpen(Request $request, Ticket $ticket): RedirectResponse
    {
        if ((int) $ticket->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->query('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return redirect()->route('client.tickets.show', $ticket);
    }

    public function adminDismiss(Request $request): RedirectResponse
    {
        $request->validate([
            'ticket_id' => 'required|integer|exists:tickets,id',
            'activity_at' => 'required|date',
        ]);

        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        $this->assertAdminCanInteractWithTicket($ticket);

        $activityAt = Carbon::parse($request->string('activity_at')->toString());
        $state = TicketUserState::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => (int) auth()->id(),
        ]);

        if (! $state->exists || ! $state->hasViewedActivity($activityAt)) {
            return back()->with('error', 'You can dismiss notifications only after viewing them.');
        }

        $state->dismissed_at = $activityAt;
        $state->save();
        TicketUserState::forgetHeaderNotificationCacheForUser((int) auth()->id());

        return back();
    }

    public function adminClear(Request $request): JsonResponse|RedirectResponse
    {
        $authUser = auth()->user();
        $ticketsQuery = Ticket::query()->open();

        if ($authUser && $authUser->isTechnician()) {
            $ticketsQuery->where('assigned_to', $authUser->id);
        }

        $ticketIds = $ticketsQuery->pluck('id');
        if ($ticketIds->isEmpty()) {
            return $request->expectsJson()
                ? response()->json(['ok' => true])
                : back();
        }

        $now = now();
        $rows = $ticketIds->map(fn ($ticketId) => [
            'ticket_id' => (int) $ticketId,
            'user_id' => (int) auth()->id(),
            'last_seen_at' => $now,
            'dismissed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        TicketUserState::upsert(
            $rows,
            ['ticket_id', 'user_id'],
            ['last_seen_at', 'dismissed_at', 'updated_at']
        );
        TicketUserState::forgetHeaderNotificationCacheForUser((int) auth()->id());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function adminSeen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->assertAdminCanInteractWithTicket($ticket);

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->input('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    }

    public function adminOpen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->assertAdminCanInteractWithTicket($ticket);

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->query('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return redirect()->route('admin.tickets.show', $ticket);
    }

    private function assertAdminCanInteractWithTicket(Ticket $ticket): void
    {
        $authUser = auth()->user();
        if ($authUser && $authUser->isTechnician() && (int) $ticket->assigned_to !== (int) $authUser->id) {
            abort(403);
        }
    }
}

