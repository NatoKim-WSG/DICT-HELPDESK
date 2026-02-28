<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static array $departmentBrandAssetCache = [];

    public const ROLE_CLIENT = 'client';

    public const ROLE_SHADOW = 'shadow';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TECHNICAL = 'technical';

    public const ROLE_SUPER_USER = 'super_user';

    // Compatibility alias used by tests and existing naming conventions.
    public const ROLE_SUPER_ADMIN = self::ROLE_ADMIN;

    public const TICKET_CONSOLE_ROLES = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
    ];

    public const ADMIN_LEVEL_ROLES = [
        self::ROLE_SHADOW,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
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
        'COMELEC',
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
        'is_profile_locked',
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
            'is_profile_locked' => 'boolean',
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

    public function ticketStates()
    {
        return $this->hasMany(TicketUserState::class);
    }

    public function legalConsents()
    {
        return $this->hasMany(UserLegalConsent::class);
    }

    public function credentialHandoff()
    {
        return $this->hasOne(CredentialHandoff::class, 'target_user_id');
    }

    public function hasAcceptedCurrentLegalConsent(): bool
    {
        return UserLegalConsent::hasCurrentConsentForUser($this);
    }

    public function isAdmin()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function isSuperAdmin()
    {
        return in_array($this->normalizedRole(), [self::ROLE_ADMIN, self::ROLE_SHADOW], true);
    }

    public function isShadow(): bool
    {
        return $this->normalizedRole() === self::ROLE_SHADOW;
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
        return $this->normalizedRole() === self::ROLE_SHADOW;
    }

    public function isAdminLevel()
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function normalizedRole(): string
    {
        return self::normalizeRole($this->role);
    }

    public function publicRole(): string
    {
        return self::publicRoleValue($this->role);
    }

    public function publicDisplayName(): string
    {
        return $this->isShadow()
            ? 'iOne Technical Team'
            : (string) $this->name;
    }

    public static function normalizeRole(?string $role): string
    {
        return strtolower(trim((string) $role));
    }

    public static function publicRoleValue(?string $role): string
    {
        $normalizedRole = self::normalizeRole($role);

        return $normalizedRole === self::ROLE_SHADOW
            ? self::ROLE_ADMIN
            : $normalizedRole;
    }

    public static function publicRoleLabel(?string $role): string
    {
        return ucfirst(str_replace('_', ' ', self::publicRoleValue($role)));
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
            $departmentToken === 'comelec' => 'comelec',
            $departmentToken === 'afp' => 'afp',
            in_array($departmentToken, ['lgupasig', 'lgup'], true) => 'lgu_pasig',
            $departmentToken === 'dict' => 'dict',
            $departmentToken === 'others' => 'others',
            in_array($normalizedRole, [self::ROLE_SHADOW, self::ROLE_ADMIN, self::ROLE_SUPER_USER, self::ROLE_TECHNICAL], true) => 'ione',
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
            'comelec' => ['name' => 'COMELEC', 'logo' => 'images/COMELEC Logo.png'],
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
        $cacheKey = $brandKey.'|'.$logoPath;

        if (isset(self::$departmentBrandAssetCache[$cacheKey])) {
            return self::$departmentBrandAssetCache[$cacheKey];
        }

        if (! file_exists(public_path($logoPath))) {
            $logoPath = $defaultLogoPath;
        }

        $assets = [
            'key' => $brandKey,
            'name' => $brand['name'] ?? 'iOne',
            'logo_path' => $logoPath,
            'logo_url' => asset($logoPath),
        ];

        self::$departmentBrandAssetCache[$cacheKey] = $assets;

        return $assets;
    }

    public static function allowedDepartments(): array
    {
        $departments = self::ALLOWED_DEPARTMENTS;
        usort($departments, fn (string $left, string $right) => strnatcasecmp($left, $right));

        return $departments;
    }

    public function scopeVisibleDirectory(Builder $query): Builder
    {
        return $query->where('role', '!=', self::ROLE_SHADOW);
    }
}

