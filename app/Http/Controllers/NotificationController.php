<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notifications\DismissNotificationRequest;
use App\Models\Ticket;
use App\Models\TicketUserState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    private const CLEAR_NOTIFICATION_CHUNK_SIZE = 250;

    public function clientDismiss(DismissNotificationRequest $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->integer('ticket_id'));
        $this->authorize('view', $ticket);

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
        $userId = (int) auth()->id();

        $this->clearNotificationsForTickets(
            Ticket::query()->where('user_id', $userId),
            $userId
        );

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function clientSeen(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->input('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return response()->json([
            'ok' => true,
            'seen_at' => $seenAt->toIso8601String(),
        ]);
    }

    public function clientOpen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $seenAt = TicketUserState::resolveSeenAt($ticket, $request->query('activity_at'));
        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $seenAt);

        return redirect()->route('client.tickets.show', $ticket);
    }

    public function adminDismiss(DismissNotificationRequest $request): RedirectResponse
    {
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
        $userId = (int) auth()->id();
        $ticketsQuery = Ticket::query()->open();

        if ($authUser && $authUser->isTechnician()) {
            $ticketsQuery->where('assigned_to', $authUser->id);
        }

        $this->clearNotificationsForTickets($ticketsQuery, $userId);

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
        $this->authorize('view', $ticket);
    }

    /**
     * @param  Builder<Ticket>  $ticketsQuery
     */
    private function clearNotificationsForTickets(Builder $ticketsQuery, int $userId): void
    {
        $now = now();

        (clone $ticketsQuery)
            ->select('id')
            ->orderBy('id')
            ->chunkById(self::CLEAR_NOTIFICATION_CHUNK_SIZE, function ($tickets) use ($now, $userId): void {
                /** @var Collection<int, Ticket> $tickets */
                $rows = $tickets->map(fn (Ticket $ticket) => [
                    'ticket_id' => (int) $ticket->id,
                    'user_id' => $userId,
                    'last_seen_at' => $now,
                    'dismissed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                TicketUserState::upsert(
                    $rows,
                    ['ticket_id', 'user_id'],
                    ['last_seen_at', 'dismissed_at', 'updated_at']
                );
            });

        TicketUserState::forgetHeaderNotificationCacheForUser($userId);
    }
}
