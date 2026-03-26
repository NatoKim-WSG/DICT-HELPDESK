<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property int|null $reply_to_id
 * @property string|null $message
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property-read Ticket $ticket
 * @property-read User $user
 * @property-read TicketReply|null $replyTo
 * @property-read Collection<int, Attachment> $attachments
 */
class TicketReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'reply_to_id',
        'message',
        'is_internal',
        'edited_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (TicketReply $reply) {
            $reply->loadMissing('ticket:id,user_id,assigned_to');
            if ($reply->ticket) {
                TicketUserState::forgetHeaderNotificationCachesForTicket($reply->ticket);
            }
        });

        static::deleted(function (TicketReply $reply) {
            $reply->loadMissing('ticket:id,user_id,assigned_to');
            if ($reply->ticket) {
                TicketUserState::forgetHeaderNotificationCachesForTicket($reply->ticket);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'reply_to_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeVisibleToViewer(Builder $query, ?User $viewer = null): Builder
    {
        if ($viewer?->isShadow()) {
            return $query;
        }

        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', '!=', User::ROLE_SHADOW);
        });
    }

    public function isVisibleTo(?User $viewer = null): bool
    {
        return $viewer?->isShadow() || ! $this->user?->isShadow();
    }
}
