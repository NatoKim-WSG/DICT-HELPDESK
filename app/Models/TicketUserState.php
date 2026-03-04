<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $dismissed_at
 * @property-read Ticket $ticket
 * @property-read User $user
 */
class TicketUserState extends Model
{
    use HasFactory;

    private const ACTIVE_CONSOLE_USER_IDS_CACHE_KEY = 'header_notification_console_user_ids_v1';

    private const ACTIVE_CONSOLE_USER_IDS_CACHE_SECONDS = 30;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'last_seen_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function markSeen(
        Ticket $ticket,
        int $userId,
        Carbon $seenAt,
        bool $clearDismissedAt = false
    ): self {
        /** @var self $state */
        $state = static::query()->firstOrNew([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
        ]);

        $currentSeenAt = $state->last_seen_at;
        $nextSeenAt = $currentSeenAt && $currentSeenAt->gt($seenAt)
            ? $currentSeenAt
            : $seenAt;

        $state->last_seen_at = $nextSeenAt;
        if ($clearDismissedAt) {
            $state->dismissed_at = null;
        }
        $state->save();
        static::forgetHeaderNotificationCacheForUser($userId);

        return $state;
    }

    public static function markSeenAndDismiss(
        Ticket $ticket,
        int $userId,
        Carbon $seenAt
    ): self {
        $state = static::markSeen($ticket, $userId, $seenAt);
        if (! $state->dismissed_at || $state->dismissed_at->lt($state->last_seen_at)) {
            $state->dismissed_at = $state->last_seen_at;
            $state->save();
        }
        static::forgetHeaderNotificationCacheForUser($userId);

        return $state;
    }

    public function hasViewedActivity(Carbon $activityAt): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gte($activityAt);
    }

    public static function resolveSeenAt(Ticket $ticket, mixed $rawActivityAt): \Carbon\CarbonInterface
    {
        if (is_string($rawActivityAt) && trim($rawActivityAt) !== '') {
            try {
                return Carbon::parse($rawActivityAt);
            } catch (\Throwable) {
                // Fall through to a reliable fallback.
            }
        }

        return $ticket->updated_at ?? now();
    }

    public static function headerNotificationCacheKeyForUser(int $userId): string
    {
        return 'header_notifications_payload_user_'.$userId.'_v1';
    }

    public static function forgetHeaderNotificationCacheForUser(int $userId): void
    {
        Cache::forget(static::headerNotificationCacheKeyForUser($userId));
    }

    public static function forgetHeaderNotificationCachesForTicket(Ticket $ticket): void
    {
        $consoleUserIds = static::activeConsoleUserIds();

        $candidateUserIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                $consoleUserIds,
                [(int) $ticket->user_id, (int) ($ticket->assigned_to ?? 0)]
            )
        ))));

        foreach ($candidateUserIds as $userId) {
            static::forgetHeaderNotificationCacheForUser((int) $userId);
        }
    }

    public static function activeConsoleUserIds(): array
    {
        return Cache::remember(
            self::ACTIVE_CONSOLE_USER_IDS_CACHE_KEY,
            now()->addSeconds(self::ACTIVE_CONSOLE_USER_IDS_CACHE_SECONDS),
            fn () => User::query()
                ->whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn (int|string $id) => (int) $id)
                ->values()
                ->all()
        );
    }
}
