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

    public const ALLOWED_DEPARTMENTS = [
        'iOne',
        'BOC',
        'DSWD',
        'DEPED',
        'PCG',
        'NAVY',
        'DA',
        'DAR',
        'AFP',
        'LGU Pasig',
        'DICT',
        'Others',
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
        return in_array($this->normalizedRole(), self::TICKET_CONSOLE_ROLES, true);
    }

    public function canManageTickets()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function canManageUsers()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
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

    public static function departmentBrandKey(?string $department, ?string $role = null): string
    {
        $normalizedDepartment = strtolower(trim((string) $department));
        $departmentToken = preg_replace('/[^a-z0-9]+/', '', $normalizedDepartment);
        $normalizedRole = self::normalizeRole($role);

        return match (true) {
            in_array($departmentToken, ['ione', 'ioneresources', 'administration', 'it'], true) => 'ione',
            $departmentToken === 'boc' => 'boc',
            $departmentToken === 'dswd' => 'dswd',
            $departmentToken === 'deped' => 'deped',
            $departmentToken === 'pcg' => 'pcg',
            $departmentToken === 'navy' => 'navy',
            $departmentToken === 'dar' => 'dar',
            $departmentToken === 'da' => 'da',
            $departmentToken === 'afp' => 'afp',
            in_array($departmentToken, ['lgupasig', 'lgup'], true) => 'lgu_pasig',
            $departmentToken === 'dict' => 'dict',
            $departmentToken === 'others' => 'others',
            in_array($normalizedRole, [self::ROLE_SUPER_ADMIN, self::ROLE_SUPER_USER, self::ROLE_TECHNICAL], true) => 'ione',
            default => 'dict',
        };
    }

    public static function departmentBrandMap(): array
    {
        return [
            'ione' => ['name' => 'iOne', 'logo' => 'images/iOne Logo.png'],
            'boc' => ['name' => 'BOC', 'logo' => 'images/BOC Logo.png'],
            'dswd' => ['name' => 'DSWD', 'logo' => 'images/DSWD Logo.png'],
            'deped' => ['name' => 'DEPED', 'logo' => 'images/DEPED Logo.png'],
            'pcg' => ['name' => 'PCG', 'logo' => 'images/PCG Logo.png'],
            'navy' => ['name' => 'NAVY', 'logo' => 'images/Navy Logo.png'],
            'da' => ['name' => 'DA', 'logo' => 'images/DA Logo.png'],
            'dar' => ['name' => 'DAR', 'logo' => 'images/DAR Logo.png'],
            'afp' => ['name' => 'AFP', 'logo' => 'images/AFP Logo.png'],
            'lgu_pasig' => ['name' => 'LGU Pasig', 'logo' => 'images/LGUP Logo.png'],
            'dict' => ['name' => 'DICT', 'logo' => 'images/DICT Logo.png'],
            'others' => ['name' => 'Others', 'logo' => 'images/Others Logo.png'],
        ];
    }

    public static function departmentBrandAssets(?string $department, ?string $role = null): array
    {
        $brandKey = self::departmentBrandKey($department, $role);
        $brandMap = self::departmentBrandMap();
        $brand = $brandMap[$brandKey] ?? $brandMap['ione'];
        $defaultLogoPath = $brandMap['ione']['logo'] ?? 'images/iOne Logo.png';
        $logoPath = $brand['logo'] ?? $defaultLogoPath;

        if (!file_exists(public_path($logoPath))) {
            $logoPath = $defaultLogoPath;
        }

        return [
            'key' => $brandKey,
            'name' => $brand['name'] ?? 'iOne',
            'logo_path' => $logoPath,
            'logo_url' => asset($logoPath),
        ];
    }

    public static function allowedDepartments(): array
    {
        $departments = self::ALLOWED_DEPARTMENTS;
        usort($departments, fn (string $left, string $right) => strnatcasecmp($left, $right));

        return $departments;
    }
}
