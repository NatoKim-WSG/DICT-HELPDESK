<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user) {
                $view->with('headerNotifications', collect());

                return;
            }

            if (! $user->canAccessAdminTickets()) {
                $tickets = Ticket::where('user_id', $user->id)
                    ->with('user')
                    ->latest('updated_at')
                    ->take(20)
                    ->get();
            } else {
                $notificationsQuery = Ticket::with('user')
                    ->open();

                if ($user->isTechnician()) {
                    $notificationsQuery->where('assigned_to', $user->id);
                }

                $tickets = $notificationsQuery
                    ->latest('updated_at')
                    ->take(20)
                    ->get();
            }

            $notifications = $this->buildNotificationsForUser($user, $tickets);
            $view->with('headerNotifications', $notifications);
        });
    }

    private function buildNotificationsForUser(User $user, Collection $tickets): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $states = TicketUserState::where('user_id', $user->id)
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get()
            ->keyBy('ticket_id');
        $latestRepliesByTicket = $this->latestCounterpartRepliesForTickets($user, $tickets, $states);

        return $tickets
            ->map(function (Ticket $ticket) use ($user, $states, $latestRepliesByTicket) {
                /** @var TicketUserState|null $state */
                $state = $states->get($ticket->id);
                $lastSeenAt = optional($state)->last_seen_at;
                $latestCounterpartReply = $latestRepliesByTicket->get((int) $ticket->id);
                $activityAt = $latestCounterpartReply
                    ? $latestCounterpartReply->created_at
                    : optional($ticket->updated_at);

                if (! $activityAt) {
                    return null;
                }

                if ($lastSeenAt && $activityAt->lte($lastSeenAt)) {
                    return null;
                }

                if ($state?->dismissed_at && $activityAt->lte($state->dismissed_at)) {
                    return null;
                }

                $isTicketConsoleUser = $user->canAccessAdminTickets();
                $senderName = optional($latestCounterpartReply?->user)->name;
                $replyPreview = Str::of((string) optional($latestCounterpartReply)->message)->squish()->toString();
                $meta = $ticket->subject;
                $title = 'Ticket update';

                if ($latestCounterpartReply) {
                    $title = $isTicketConsoleUser ? 'New client message' : 'New technical message';
                    $meta = $ticket->subject
                        .($senderName ? ' - '.$senderName : '')
                        .($replyPreview !== '' ? ': '.Str::limit($replyPreview, 70) : '');
                } elseif ($isTicketConsoleUser) {
                    $meta = $ticket->subject.' - '.optional($ticket->user)->name;
                }

                $activityTs = $activityAt->timestamp;
                $key = 'ticket-'.$ticket->id.'-'.$activityTs;
                $openRoute = $isTicketConsoleUser
                    ? route('admin.notifications.open', [
                        'ticket' => $ticket,
                        'ticket_id' => $ticket->id,
                        'activity_at' => $activityAt->toIso8601String(),
                    ])
                    : route('client.notifications.open', [
                        'ticket' => $ticket,
                        'ticket_id' => $ticket->id,
                        'activity_at' => $activityAt->toIso8601String(),
                    ]);

                return [
                    'title' => $title,
                    'meta' => $meta,
                    'time' => $activityAt->diffForHumans(),
                    'url' => $openRoute,
                    'key' => $key,
                    'ticket_id' => $ticket->id,
                    'activity_at' => $activityAt->toIso8601String(),
                    'activity_ts' => $activityTs,
                    'can_dismiss' => $isTicketConsoleUser,
                    'dismiss_url' => $isTicketConsoleUser ? route('admin.notifications.dismiss') : null,
                ];
            })
            ->filter()
            ->sortByDesc('activity_ts')
            ->take(5)
            ->values();
    }

    private function latestCounterpartRepliesForTickets(User $user, Collection $tickets, Collection $states): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $tickets
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $ticketUserIds = $tickets
            ->mapWithKeys(fn (Ticket $ticket) => [(int) $ticket->id => (int) $ticket->user_id])
            ->all();
        $ticketStateCutoff = $states
            ->mapWithKeys(fn (TicketUserState $state) => [(int) $state->ticket_id => $state->last_seen_at])
            ->all();

        $query = TicketReply::query()
            ->with('user')
            ->whereIn('ticket_id', $ticketIds)
            ->where('is_internal', false);

        if ($user->canAccessAdminTickets()) {
            $query->whereIn('user_id', collect($ticketUserIds)->unique()->values());
        } else {
            $query->where('user_id', '!=', $user->id);
        }

        $latestRepliesByTicket = [];
        $query->orderByDesc('created_at')
            ->get()
            ->each(function (TicketReply $reply) use (
                &$latestRepliesByTicket,
                $ticketUserIds,
                $ticketStateCutoff,
                $user
            ) {
                $ticketId = (int) $reply->ticket_id;
                if (isset($latestRepliesByTicket[$ticketId])) {
                    return;
                }

                if ($user->canAccessAdminTickets()) {
                    $expectedUserId = $ticketUserIds[$ticketId] ?? null;
                    if ($expectedUserId === null || (int) $reply->user_id !== $expectedUserId) {
                        return;
                    }
                }

                $lastSeenAt = $ticketStateCutoff[$ticketId] ?? null;
                if ($lastSeenAt && $reply->created_at && $reply->created_at->lte($lastSeenAt)) {
                    return;
                }

                $latestRepliesByTicket[$ticketId] = $reply;
            });

        return collect($latestRepliesByTicket);
    }
}
