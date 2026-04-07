<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_to
 * @property int|null $category_id
 * @property string $ticket_number
 * @property string $subject
 * @property string|null $priority
 * @property string $status
 * @property bool $is_imported
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property-read User $user
 * @property-read User|null $assignedUser
 * @property-read Collection<int, User> $assignedUsers
 * @property-read User|null $closedBy
 * @property-read Category|null $category
 * @property-read Collection<int, TicketReply> $replies
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, TicketUserState> $userStates
 *
 * @method static Builder<static> open()
 * @method static Builder<static> closed()
 * @method static Builder<static> byPriority(string $priority)
 * @method static Builder<static> assignedTo(int $userId)
 */
class Ticket extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const STATUSES = ['open', 'in_progress', 'pending', 'resolved', 'closed'];

    public const OPEN_STATUSES = ['open', 'in_progress', 'pending'];

    public const CLOSED_STATUSES = ['resolved', 'closed'];

    protected $fillable = [
        'ticket_number',
        'name',
        'contact_number',
        'email',
        'province',
        'municipality',
        'subject',
        'description',
        'priority',
        'status',
        'user_id',
        'assigned_to',
        'assigned_at',
        'is_imported',
        'category_id',
        'resolved_at',
        'closed_at',
        'closed_by',
        'satisfaction_rating',
        'satisfaction_comment',
        'consent_accepted_at',
        'consent_version',
        'consent_ip_address',
        'consent_user_agent',
        'super_users_notified_new_at',
        'technical_user_notified_assignment_at',
        'super_users_notified_unchecked_at',
        'super_users_notified_unassigned_sla_at',
        'technical_user_notified_sla_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'is_imported' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'satisfaction_rating' => 'integer',
            'consent_accepted_at' => 'datetime',
            'super_users_notified_new_at' => 'datetime',
            'technical_user_notified_assignment_at' => 'datetime',
            'super_users_notified_unchecked_at' => 'datetime',
            'super_users_notified_unassigned_sla_at' => 'datetime',
            'technical_user_notified_sla_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Ticket $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = self::generateUniqueTicketNumber();
            }
        });

        static::created(function (Ticket $ticket) {
            if ($ticket->assigned_to) {
                $ticket->assignedUsers()->syncWithoutDetaching([(int) $ticket->assigned_to]);
            }
        });

        static::saved(function (Ticket $ticket) {
            TicketUserState::forgetHeaderNotificationCachesForTicket($ticket);
        });

        static::deleted(function (Ticket $ticket) {
            TicketUserState::forgetHeaderNotificationCachesForTicket($ticket);
        });
    }

    private static function generateUniqueTicketNumber(int $maxAttempts = 20): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = 'TK-'.strtoupper(Str::random(8));
            if (! self::query()->where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'TK-'.strtoupper(Str::random(12));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_assignments')
            ->withTimestamps();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<TicketReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<TicketUserState, $this> */
    public function userStates(): HasMany
    {
        return $this->hasMany(TicketUserState::class);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyAssignedToConstraint(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $builder) use ($userId) {
            $builder->where('assigned_to', $userId)
                ->orWhereHas('assignedUsers', function (Builder $assignmentQuery) use ($userId) {
                    $assignmentQuery->where('users.id', $userId);
                });
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return self::applyAssignedToConstraint($query, $userId);
    }

    public function isOpen()
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed()
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isImported(): bool
    {
        return (bool) $this->is_imported;
    }

    public function hasAssignedUser(int $userId): bool
    {
        if ($this->relationLoaded('assignedUsers')) {
            return $this->assignedUsers->contains(fn (User $user) => (int) $user->id === $userId);
        }

        if ((int) ($this->assigned_to ?? 0) === $userId) {
            return true;
        }

        return $this->assignedUsers()->where('users.id', $userId)->exists();
    }

    public function getAssignedUserIdsAttribute(): array
    {
        $assignedUserIds = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $this->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();

        $primaryAssignedTo = $this->assigned_to ? (int) $this->assigned_to : null;
        if ($primaryAssignedTo === null) {
            return $assignedUserIds;
        }

        return array_values(array_unique([
            $primaryAssignedTo,
            ...$assignedUserIds,
        ]));
    }

    public function getAssignedUsersLabelAttribute(): string
    {
        $names = collect();

        /** @var User|null $primaryAssignedUser */
        $primaryAssignedUser = $this->relationLoaded('assignedUser')
            ? $this->assignedUser
            : ($this->assigned_to ? $this->assignedUser()->first() : null);

        if ($primaryAssignedUser && ! $primaryAssignedUser->isShadow()) {
            $names->push($primaryAssignedUser->publicDisplayName());
        }

        /** @var Collection<int, User> $assignedUsers */
        $assignedUsers = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers
            : $this->assignedUsers()->get();

        foreach ($assignedUsers as $assignedUser) {
            if ($primaryAssignedUser && (int) $primaryAssignedUser->id === (int) $assignedUser->id) {
                continue;
            }

            if ($assignedUser->isShadow()) {
                continue;
            }

            $names->push($assignedUser->publicDisplayName());
        }

        $label = $names
            ->filter(fn (?string $name) => $name !== null && trim($name) !== '')
            ->unique()
            ->implode(', ');

        return $label !== '' ? $label : 'Unassigned';
    }

    public function getPriorityColorAttribute()
    {
        return match (strtolower(trim((string) $this->priority))) {
            'urgent' => 'bg-red-100 text-red-800',
            'high' => 'bg-amber-100 text-amber-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match (strtolower(trim((string) $this->priority))) {
            'urgent' => 'bg-red-100 text-red-800',
            'high' => 'bg-amber-100 text-amber-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        $priority = strtolower(trim((string) $this->priority));

        return $priority !== ''
            ? ucfirst($priority)
            : 'Pending review';
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'open' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-purple-100 text-purple-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'resolved' => 'bg-green-100 text-green-800',
            'closed' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'open' => 'bg-[#00494b] text-white',
            'pending' => 'bg-amber-400 text-white',
            'in_progress' => 'bg-sky-500 text-white',
            'resolved' => 'bg-emerald-500 text-white',
            'closed' => 'bg-slate-500 text-white',
            default => 'bg-slate-400 text-white',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return str_replace('_', ' ', (string) $this->status);
    }

    public function getActivityDotClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-emerald-500',
            'in_progress' => 'bg-sky-500',
            'resolved', 'closed' => 'bg-slate-400',
            default => 'bg-amber-400',
        };
    }

    public function getActivityLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Awaiting customer response',
            'in_progress' => 'In progress',
            'resolved', 'closed' => 'Read',
            default => 'Unread',
        };
    }

    public static function reopenedLifecycleResetAttributes(): array
    {
        return [
            'resolved_at' => null,
            'closed_at' => null,
            'closed_by' => null,
            'satisfaction_rating' => null,
            'satisfaction_comment' => null,
            'super_users_notified_unassigned_sla_at' => null,
            'technical_user_notified_sla_at' => null,
        ];
    }
}
