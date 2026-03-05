<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketReplyPolicy;
use App\Services\SystemLogService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    private const HEADER_NOTIFICATION_TICKET_WINDOW = 120;

    private const HEADER_NOTIFICATION_RENDER_LIMIT = 5;

    private const HEADER_NOTIFICATION_CACHE_SECONDS = 20;

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
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketReply::class, TicketReplyPolicy::class);

        $this->deleteStaleViteHotFile();
        $this->registerSlowQueryTelemetry();

        View::composer('layouts.app', function ($view) {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user) {
                $view->with('headerNotifications', collect());
                $view->with('headerNotificationUnreadCount', 0);

                return;
            }

            $cacheKey = TicketUserState::headerNotificationCacheKeyForUser((int) $user->id);
            $payload = Cache::remember(
                $cacheKey,
                now()->addSeconds(self::HEADER_NOTIFICATION_CACHE_SECONDS),
                function () use ($user): array {
                    $tickets = $this->headerNotificationTicketsForUser($user);
                    $notificationItems = $this->buildNotificationsForUser($user, $tickets)
                        ->sortByDesc('activity_ts')
                        ->values();

                    return [
                        'notifications' => $notificationItems
                            ->take(self::HEADER_NOTIFICATION_RENDER_LIMIT)
                            ->values()
                            ->all(),
                        'unread_count' => (int) $notificationItems
                            ->where('is_viewed', false)
                            ->count(),
                    ];
                }
            );

            $view->with('headerNotifications', collect($payload['notifications'] ?? []));
            $view->with('headerNotificationUnreadCount', (int) ($payload['unread_count'] ?? 0));
        });
    }

    private function headerNotificationTicketsForUser(User $user): Collection
    {
        if (! $user->canAccessAdminTickets()) {
            return Ticket::where('user_id', $user->id)
                ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at'])
                ->with('user:id,name')
                ->latest('updated_at')
                ->limit(self::HEADER_NOTIFICATION_TICKET_WINDOW)
                ->get();
        }

        $notificationsQuery = Ticket::query()
            ->select(['id', 'user_id', 'subject', 'created_at', 'updated_at', 'assigned_to'])
            ->with('user:id,name')
            ->open()
            ->latest('updated_at');

        if ($user->isTechnician()) {
            $notificationsQuery->where('assigned_to', $user->id);
        }

        return $notificationsQuery
            ->limit(self::HEADER_NOTIFICATION_TICKET_WINDOW)
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

    private function deleteStaleViteHotFile(): void
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return;
        }

        $hotUrl = trim((string) @file_get_contents($hotFile));
        if ($hotUrl === '') {
            @unlink($hotFile);

            return;
        }

        $parsedUrl = parse_url($hotUrl);
        if (! is_array($parsedUrl) || empty($parsedUrl['host'])) {
            @unlink($hotFile);

            return;
        }

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = (string) $parsedUrl['host'];
        $port = (int) ($parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80));

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
        if ($socket === false) {
            @unlink($hotFile);

            return;
        }

        fclose($socket);
    }

    private function registerSlowQueryTelemetry(): void
    {
        $configuredEnabledFlag = config('observability.slow_queries.enabled');
        $slowQueryTelemetryEnabled = is_bool($configuredEnabledFlag)
            ? $configuredEnabledFlag
            : app()->environment(['staging', 'production']);

        if (! $slowQueryTelemetryEnabled) {
            return;
        }

        $thresholdMs = (int) config('observability.slow_queries.threshold_ms', 250);
        if ($thresholdMs < 1) {
            return;
        }

        $includeBindings = (bool) config('observability.slow_queries.include_bindings', false);
        $logToSystemLogs = (bool) config('observability.slow_queries.log_to_system_logs', false);

        DB::listen(function (QueryExecuted $query) use ($thresholdMs, $includeBindings, $logToSystemLogs): void {
            if ($query->time < $thresholdMs) {
                return;
            }

            $rawSql = $query->toRawSql();

            $payload = [
                'connection' => $query->connectionName,
                'duration_ms' => round((float) $query->time, 2),
                'sql' => Str::limit((string) $rawSql, 4000),
            ];

            if ($includeBindings) {
                $payload['bindings'] = $this->sanitizeSlowQueryBindings($query->bindings);
            }

            Log::warning('Slow query detected.', $payload);

            if (! $logToSystemLogs) {
                return;
            }

            static $recordingSystemLog = false;
            if ($recordingSystemLog) {
                return;
            }

            try {
                $recordingSystemLog = true;
                app(SystemLogService::class)->record(
                    'database.query.slow',
                    'Slow database query exceeded configured threshold.',
                    [
                        'category' => 'performance',
                        'metadata' => $payload,
                    ]
                );
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                $recordingSystemLog = false;
            }
        });
    }

    private function sanitizeSlowQueryBindings(array $bindings): array
    {
        return array_map(function (mixed $binding): mixed {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format(DATE_ATOM);
            }

            if (is_scalar($binding) || $binding === null) {
                if (is_string($binding)) {
                    return Str::limit($binding, 250);
                }

                return $binding;
            }

            return '['.get_debug_type($binding).']';
        }, $bindings);
    }

    private function resolveNotificationActivityAt(
        User $user,
        Ticket $ticket,
        ?TicketReply $latestCounterpartReply
    ): ?\Illuminate\Support\Carbon {
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
        $ticketUserIds = $tickets
            ->mapWithKeys(fn (Ticket $ticket) => [(int) $ticket->id => (int) $ticket->user_id])
            ->all();

        $query = TicketReply::query()
            ->select(['id', 'ticket_id', 'user_id', 'message', 'created_at'])
            ->with('user:id,name')
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

                $latestRepliesByTicket[$ticketId] = $reply;
            });

        return collect($latestRepliesByTicket);
    }
}
