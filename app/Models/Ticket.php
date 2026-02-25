<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'category_id',
        'due_date',
        'resolved_at',
        'closed_at',
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
            'due_date' => 'datetime',
            'assigned_at' => 'datetime',
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

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TK-'.strtoupper(Str::random(8));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function userStates()
    {
        return $this->hasMany(TicketUserState::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function isOpen()
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isClosed()
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isOverdue()
    {
        return $this->due_date && $this->due_date->isPast() && $this->isOpen();
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
        return ucfirst((string) $this->priority);
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
}
