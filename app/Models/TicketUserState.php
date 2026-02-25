<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TicketUserState extends Model
{
    use HasFactory;

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

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
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

        return $state;
    }

    public function hasViewedActivity(Carbon $activityAt): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gte($activityAt);
    }

    public static function resolveSeenAt(Ticket $ticket, mixed $rawActivityAt): Carbon
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
}
