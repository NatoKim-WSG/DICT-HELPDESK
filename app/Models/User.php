<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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
        return $this->role === 'admin';
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isClient()
    {
        return $this->role === 'client';
    }

    public function canManageTickets()
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function canManageUsers()
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function canCreateAdmins()
    {
        return $this->role === 'super_admin';
    }

    public function isAdminLevel()
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }
}