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
 * @property int|null $created_by_user_id
 * @property int|null $assigned_to
 * @property int|null $category_id
 * @property string $ticket_number
 * @property string $ticket_type
 * @property string|null $creation_source
 * @property string $subject
 * @property string|null $priority
 * @property string $status
 * @property bool $is_imported
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property-read User $user
 * @property-read User|null $createdByUser
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

    public const PRIORITIES = ['severity_1', 'severity_2', 'severity_3'];

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_EXTERNAL = 'external';

    public const TYPES = [
        self::TYPE_INTERNAL,
        self::TYPE_EXTERNAL,
    ];

    public const CREATION_SOURCE_CLIENT_SELF_SERVICE = 'client_self_service';

    public const CREATION_SOURCE_STAFF_FOR_CLIENT = 'staff_for_client';

    public const CREATION_SOURCE_STAFF_FOR_STAFF = 'staff_for_staff';

    public const CREATION_SOURCE_IMPORTED = 'imported';

    public const CREATION_SOURCES = [
        self::CREATION_SOURCE_CLIENT_SELF_SERVICE,
        self::CREATION_SOURCE_STAFF_FOR_CLIENT,
        self::CREATION_SOURCE_STAFF_FOR_STAFF,
        self::CREATION_SOURCE_IMPORTED,
    ];

    public const PRIORITY_ALIASES = [
        'severity_1' => 'severity_1',
        'severity 1' => 'severity_1',
        'severity-1' => 'severity_1',
        'urgent' => 'severity_1',
        'high' => 'severity_1',
        'severity_2' => 'severity_2',
        'severity 2' => 'severity_2',
        'severity-2' => 'severity_2',
        'medium' => 'severity_2',
        'severity_3' => 'severity_3',
        'severity 3' => 'severity_3',
        'severity-3' => 'severity_3',
        'low' => 'severity_3',
    ];

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
        'created_by_user_id',
        'assigned_to',
        'assigned_at',
        'is_imported',
        'category_id',
        'ticket_type',
        'creation_source',
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
                ->orWhereExists(function ($assignmentQuery) use ($userId) {
                    self::configureAssignmentExistsQuery($assignmentQuery, $userId);
                });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyClosedInternalRequesterConstraint(Builder $query, int $userId): Builder
    {
        return $query->where('ticket_type', self::TYPE_INTERNAL)
            ->where('user_id', $userId)
            ->whereIn('status', self::CLOSED_STATUSES);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyInternalRequesterConstraint(Builder $query, int $userId): Builder
    {
        return $query->where('ticket_type', self::TYPE_INTERNAL)
            ->where('user_id', $userId);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyHistoryClientRequesterConstraint(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', User::ROLE_CLIENT);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyHistoryStaffRequesterConstraint(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery->where('role', '!=', User::ROLE_CLIENT);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyReportableConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('ticket_type')
                ->orWhere('ticket_type', '!=', self::TYPE_INTERNAL);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyAssignedConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNotNull('assigned_to')
                ->orWhereExists(function ($assignmentQuery) {
                    self::configureAssignmentExistsQuery($assignmentQuery);
                });
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function applyUnassignedConstraint(Builder $query): Builder
    {
        return $query->whereNull('assigned_to')
            ->whereNotExists(function ($assignmentQuery) {
                self::configureAssignmentExistsQuery($assignmentQuery);
            });
    }

    public static function normalizeAssignedUserIds(array $userIds, ?int $primaryAssignedTo = null): array
    {
        $normalizedIds = collect($userIds)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();

        if ($primaryAssignedTo === null || $primaryAssignedTo <= 0) {
            return $normalizedIds;
        }

        return array_values(array_unique([
            $primaryAssignedTo,
            ...$normalizedIds,
        ]));
    }

    public static function primaryAssignedUserId(array $userIds, ?int $primaryAssignedTo = null): ?int
    {
        return self::normalizeAssignedUserIds($userIds, $primaryAssignedTo)[0] ?? null;
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
        return $query->where('priority', self::normalizePriorityValue($priority));
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return self::applyAssignedToConstraint($query, $userId);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isImported(): bool
    {
        return (bool) $this->is_imported;
    }

    public static function normalizePriorityValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('-', '_', $value)));
        if ($normalized === '') {
            return null;
        }

        return self::PRIORITY_ALIASES[$normalized] ?? null;
    }

    public function setPriorityAttribute(mixed $value): void
    {
        $this->attributes['priority'] = self::normalizePriorityValue($value);
    }

    public static function normalizeTicketTypeValue(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::TYPES, true)
            ? $normalized
            : self::TYPE_EXTERNAL;
    }

    public function setTicketTypeAttribute(mixed $value): void
    {
        $this->attributes['ticket_type'] = self::normalizeTicketTypeValue($value);
    }

    public static function normalizeCreationSourceValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, self::CREATION_SOURCES, true)
            ? $normalized
            : null;
    }

    public function setCreationSourceAttribute(mixed $value): void
    {
        $this->attributes['creation_source'] = self::normalizeCreationSourceValue($value);
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

    public function isClosedInternalRequesterTicketFor(int $userId): bool
    {
        return $this->ticket_type === self::TYPE_INTERNAL
            && (int) $this->user_id === $userId
            && $this->isClosed();
    }

    public function getAssignedUserIdsAttribute(): array
    {
        $assignedUserIds = $this->relationLoaded('assignedUsers')
            ? $this->assignedUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $this->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();

        return self::normalizeAssignedUserIds(
            $assignedUserIds,
            $this->assigned_to ? (int) $this->assigned_to : null
        );
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

    public function getRequesterDisplayLabelAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->publicDisplayName();
        }

        if ($this->user_id) {
            $requester = $this->user()->first();
            if ($requester instanceof User) {
                return $requester->publicDisplayName();
            }
        }

        return (string) ($this->name ?: 'Unknown requester');
    }

    public function getCreationSourceLabelAttribute(): string
    {
        return match ($this->resolvedCreationSource()) {
            self::CREATION_SOURCE_CLIENT_SELF_SERVICE => 'Client Submitted',
            self::CREATION_SOURCE_STAFF_FOR_CLIENT => 'Staff Logged for Client',
            self::CREATION_SOURCE_STAFF_FOR_STAFF => 'Staff Logged for Staff',
            self::CREATION_SOURCE_IMPORTED => 'Imported',
            default => 'Requester Linked',
        };
    }

    public function getCreationSourceSummaryAttribute(): string
    {
        $creatorName = $this->creatorDisplayName();

        return match ($this->resolvedCreationSource()) {
            self::CREATION_SOURCE_CLIENT_SELF_SERVICE => 'Submitted directly by client',
            self::CREATION_SOURCE_STAFF_FOR_CLIENT => $creatorName
                ? "Logged by {$creatorName} for client"
                : 'Logged by staff for client',
            self::CREATION_SOURCE_STAFF_FOR_STAFF => $creatorName
                ? "Logged by {$creatorName} for staff"
                : 'Logged by staff for staff',
            self::CREATION_SOURCE_IMPORTED => 'Imported from legacy records',
            default => $this->resolvedRequesterSourceSummary(),
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return $this->resolvePriorityBadgeClasses('bg-gray-100 text-gray-800');
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return $this->resolvePriorityBadgeClasses('bg-slate-100 text-slate-700');
    }

    private function resolvePriorityBadgeClasses(string $defaultClasses): string
    {
        return match (strtolower(trim((string) $this->priority))) {
            'severity_1' => 'bg-emerald-100 text-emerald-800',
            'severity_2' => 'bg-amber-100 text-amber-800',
            'severity_3' => 'bg-red-100 text-red-800',
            default => $defaultClasses,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        $priority = strtolower(trim((string) $this->priority));

        return match ($priority) {
            'severity_1' => 'Severity 1',
            'severity_2' => 'Severity 2',
            'severity_3' => 'Severity 3',
            default => 'Pending review',
        };
    }

    public function getTicketTypeBadgeClassAttribute(): string
    {
        return match ($this->ticket_type) {
            self::TYPE_INTERNAL => 'bg-slate-100 text-slate-700',
            self::TYPE_EXTERNAL => 'bg-cyan-100 text-cyan-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function getTicketTypeLabelAttribute(): string
    {
        return ucfirst(self::normalizeTicketTypeValue($this->ticket_type));
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
            'super_users_notified_unchecked_at' => null,
            'super_users_notified_unassigned_sla_at' => null,
            'technical_user_notified_sla_at' => null,
        ];
    }

    private static function configureAssignmentExistsQuery(mixed $assignmentQuery, ?int $userId = null): void
    {
        $assignmentQuery->selectRaw('1')
            ->from('ticket_assignments')
            ->whereColumn('ticket_assignments.ticket_id', 'tickets.id');

        if ($userId !== null) {
            $assignmentQuery->where('ticket_assignments.user_id', $userId);
        }
    }

    private function resolvedCreationSource(): ?string
    {
        if ($this->is_imported) {
            return self::CREATION_SOURCE_IMPORTED;
        }

        $normalizedSource = self::normalizeCreationSourceValue($this->creation_source);
        if ($normalizedSource !== null) {
            return $normalizedSource;
        }

        /** @var User|null $requester */
        $requester = $this->relationLoaded('user')
            ? $this->user
            : ($this->user_id ? $this->user()->first() : null);

        if (! $requester instanceof User) {
            return null;
        }

        if ($requester->isClient() && (int) ($this->created_by_user_id ?? 0) === (int) $requester->id) {
            return self::CREATION_SOURCE_CLIENT_SELF_SERVICE;
        }

        if ($requester->isClient() && $this->ticket_type === self::TYPE_INTERNAL) {
            return self::CREATION_SOURCE_STAFF_FOR_CLIENT;
        }

        if ($requester->isClient() && (int) ($this->created_by_user_id ?? 0) > 0) {
            return self::CREATION_SOURCE_STAFF_FOR_CLIENT;
        }

        if (! $requester->isClient() && $this->ticket_type === self::TYPE_INTERNAL) {
            return self::CREATION_SOURCE_STAFF_FOR_STAFF;
        }

        return null;
    }

    private function creatorDisplayName(): ?string
    {
        /** @var User|null $creator */
        $creator = $this->relationLoaded('createdByUser')
            ? $this->createdByUser
            : ($this->created_by_user_id ? $this->createdByUser()->first() : null);

        return $creator?->publicDisplayName();
    }

    private function resolvedRequesterSourceSummary(): string
    {
        /** @var User|null $requester */
        $requester = $this->relationLoaded('user')
            ? $this->user
            : ($this->user_id ? $this->user()->first() : null);

        if ($requester?->isClient()) {
            return 'Requester account: client';
        }

        if ($requester instanceof User) {
            return 'Requester account: staff';
        }

        return 'Requester account linked';
    }
}
