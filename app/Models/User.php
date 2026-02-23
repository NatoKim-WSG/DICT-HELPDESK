<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_CLIENT = 'client';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_TECHNICAL = 'technical';
    public const ROLE_SUPER_USER = 'super_user';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const TICKET_CONSOLE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_TECHNICIAN,
        self::ROLE_SUPER_USER,
        self::ROLE_SUPER_ADMIN,
        self::ROLE_TECHNICAL,
    ];

    public const ADMIN_LEVEL_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_SUPER_ADMIN,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'department',
        'role',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function ticketReplies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function isAdmin()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function isSuperAdmin()
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isClient()
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isTechnician()
    {
        return $this->normalizedRole() === self::ROLE_TECHNICAL;
    }

    public function canAccessAdminTickets()
    {
        return in_array($this->role, self::TICKET_CONSOLE_ROLES, true);
    }

    public function canManageTickets()
    {
        return in_array($this->role, self::ADMIN_LEVEL_ROLES, true);
    }

    public function canManageUsers()
    {
        return in_array($this->role, self::ADMIN_LEVEL_ROLES, true);
    }

    public function canCreateAdmins()
    {
        return $this->normalizedRole() === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminLevel()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function normalizedRole(): string
    {
        return self::normalizeRole($this->role);
    }

    public static function normalizeRole(?string $role): string
    {
        return match ($role) {
            self::ROLE_ADMIN => self::ROLE_SUPER_USER,
            self::ROLE_TECHNICIAN => self::ROLE_TECHNICAL,
            default => (string) $role,
        };
    }
}
