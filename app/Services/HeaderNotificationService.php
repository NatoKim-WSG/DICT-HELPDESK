<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HeaderNotificationService
{
    private const TICKET_WINDOW = 120;

    private const RENDER_LIMIT = 5;

    private const CACHE_SECONDS = 20;

    public function payloadFor(?User $user): array
    {
        if (! $user) {
            return [
                'notifications' => [],
                'unread_count' => 0,
            ];
        }

        $cacheKey = TicketUserState::headerNotificationCacheKeyForUser((int) $user->id);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CACHE_SECONDS),
            function () use ($user): array {
                $tickets = $this->headerNotificationTicketsForUser($user);
                $notificationItems = $this->buildNotificationsForUser($user, $tickets)
                    ->sortByDesc('activity_ts')
                    ->values();

                return [
                    'notifications' => $notificationItems
                        ->take(self::RENDER_LIMIT)
                        ->values()
                        ->all(),
                    'unread_count' => (int) $notificationItems
                        ->where('is_viewed', false)
                        ->count(),
                ];
            }
        );
    }

    private function headerNotificationTicketsForUser(User $user): Collection
    {
        if (! $user->canAccessAdminTickets()) {
            return Ticket::where('user_id', $user->id)
                ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at'])
                ->with('user:id,name')
                ->latest('updated_at')
                ->limit(self::TICKET_WINDOW)
                ->get();
        }

        $notificationsQuery = Ticket::query()
            ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at', 'assigned_to'])
            ->with('user:id,name')
            ->open()
            ->latest('updated_at');

        if ($user->isTechnician()) {
            Ticket::applyAssignedToConstraint($notificationsQuery, (int) $user->id);
        }

        return $notificationsQuery
            ->limit(self::TICKET_WINDOW)
            ->get();
    }

    private function buildNotificationsForUser(User $user, Collection $tickets): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $states = TicketUserState::where('user_id', $user->id)
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get(['ticket_id', 'last_seen_at', 'dismissed_at'])
            ->keyBy('ticket_id');
        $latestRepliesByTicket = $this->latestCounterpartRepliesForTickets($user, $tickets);

        return $tickets
            ->map(function (Ticket $ticket) use ($user, $states, $latestRepliesByTicket) {
                /** @var TicketUserState|null $state */
                $state = $states->get($ticket->id);
                $lastSeenAt = optional($state)->last_seen_at;
                $latestCounterpartReply = $latestRepliesByTicket->get((int) $ticket->id);
                $activityAt = $this->resolveNotificationActivityAt($user, $ticket, $latestCounterpartReply);

                if (! $activityAt) {
                    return null;
                }

                if ($state?->dismissed_at && $activityAt->lte($state->dismissed_at)) {
                    return null;
                }

                $isTicketConsoleUser = $user->canAccessAdminTickets();
                $isViewed = $lastSeenAt && $activityAt->lte($lastSeenAt);
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
                    $title = 'New ticket received';
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
                    'is_viewed' => (bool) $isViewed,
                    'can_dismiss' => (bool) $isViewed,
                    'dismiss_url' => $isTicketConsoleUser
                        ? route('admin.notifications.dismiss')
                        : route('client.notifications.dismiss'),
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveNotificationActivityAt(
        User $user,
        Ticket $ticket,
        ?TicketReply $latestCounterpartReply
    ): ?Carbon {
        if ($latestCounterpartReply && $latestCounterpartReply->created_at) {
            return $latestCounterpartReply->created_at;
        }

        if ($user->canAccessAdminTickets()) {
            return $ticket->created_at;
        }

        return null;
    }

    private function latestCounterpartRepliesForTickets(User $user, Collection $tickets): Collection
    {
        if ($tickets->isEmpty()) {
            return collect();
        }

        $ticketIds = $tickets
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $query = TicketReply::query()
            ->select('ticket_replies.*')
            ->with('user:id,name')
            ->join('tickets as notification_tickets', 'notification_tickets.id', '=', 'ticket_replies.ticket_id')
            ->whereIn('ticket_replies.ticket_id', $ticketIds)
            ->where('ticket_replies.is_internal', false);

        if (! $user->isShadow()) {
            $query->join('users as reply_users', 'reply_users.id', '=', 'ticket_replies.user_id')
                ->where('reply_users.role', '!=', User::ROLE_SHADOW);
        }

        if ($user->canAccessAdminTickets()) {
            $query->whereColumn('ticket_replies.user_id', 'notification_tickets.user_id');
        } else {
            $query->where('ticket_replies.user_id', '!=', $user->id);
        }

        $query->whereNotExists(function ($subquery) use ($user) {
            $subquery->selectRaw('1')
                ->from('ticket_replies as newer_replies')
                ->join('tickets as newer_tickets', 'newer_tickets.id', '=', 'newer_replies.ticket_id')
                ->whereColumn('newer_replies.ticket_id', 'ticket_replies.ticket_id')
                ->where('newer_replies.is_internal', false)
                ->where(function ($comparisonQuery) {
                    $comparisonQuery->whereColumn('newer_replies.created_at', '>', 'ticket_replies.created_at')
                        ->orWhere(function ($tieBreakerQuery) {
                            $tieBreakerQuery->whereColumn('newer_replies.created_at', 'ticket_replies.created_at')
                                ->whereColumn('newer_replies.id', '>', 'ticket_replies.id');
                        });
                });

            if (! $user->isShadow()) {
                $subquery->join('users as newer_reply_users', 'newer_reply_users.id', '=', 'newer_replies.user_id')
                    ->where('newer_reply_users.role', '!=', User::ROLE_SHADOW);
            }

            if ($user->canAccessAdminTickets()) {
                $subquery->whereColumn('newer_replies.user_id', 'newer_tickets.user_id');
            } else {
                $subquery->where('newer_replies.user_id', '!=', $user->id);
            }
        });

        return $query
            ->orderByDesc('ticket_replies.created_at')
            ->orderByDesc('ticket_replies.id')
            ->get()
            ->keyBy(fn (TicketReply $reply) => (int) $reply->ticket_id);
    }
}
