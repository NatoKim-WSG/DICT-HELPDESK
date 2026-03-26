<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

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
        'username',
        'email',
        'phone',
        'department',
        'client_notes',
        'role',
        'password',
        'is_active',
        'is_profile_locked',
        'must_change_password',
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
            'must_change_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (trim((string) $user->username) !== '') {
                return;
            }

            $user->username = self::generateAvailableUsername((string) $user->name);
        });

        static::updating(function (User $user): void {
            if (trim((string) $user->username) !== '') {
                return;
            }

            $user->username = self::generateAvailableUsername((string) $user->name, (int) $user->id);
        });
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assignments')
            ->withTimestamps();
    }

    public function ticketReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function ticketStates(): HasMany
    {
        return $this->hasMany(TicketUserState::class);
    }

    public function legalConsents(): HasMany
    {
        return $this->hasMany(UserLegalConsent::class);
    }

    public function credentialHandoff(): HasOne
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

    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
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
            ? self::supportTeamName()
            : (string) $this->name;
    }

    public static function supportDepartment(): string
    {
        return trim((string) config('helpdesk.support_department', 'iOne')) ?: 'iOne';
    }

    public static function supportBrandName(): string
    {
        return trim((string) config('helpdesk.support_brand_name', self::supportDepartment())) ?: self::supportDepartment();
    }

    public static function supportOrganizationName(): string
    {
        return trim((string) config('helpdesk.support_organization_name', 'iOne Resources Inc.')) ?: 'iOne Resources Inc.';
    }

    public static function supportTeamName(): string
    {
        return trim((string) config('helpdesk.support_team_name', 'iOne Technical Team')) ?: 'iOne Technical Team';
    }

    public static function supportLogoPath(): string
    {
        return trim((string) config('helpdesk.support_logo_path', 'images/iOne Logo.png')) ?: 'images/iOne Logo.png';
    }

    public static function supportLogoUrl(): string
    {
        $logoPath = self::supportLogoPath();

        if (! file_exists(public_path($logoPath))) {
            $logoPath = 'images/iOne Logo.png';
        }

        return asset($logoPath);
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
            in_array($normalizedRole, [self::ROLE_SHADOW, self::ROLE_ADMIN, self::ROLE_SUPER_USER, self::ROLE_TECHNICAL], true) => self::departmentBrandKey(self::supportDepartment()),
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
        $supportBrandKey = self::departmentBrandKey(self::supportDepartment());
        $fallbackBrand = $brandMap[$supportBrandKey] ?? $brandMap['ione'];
        $brand = $brandMap[$brandKey] ?? $fallbackBrand;
        $defaultLogoPath = $fallbackBrand['logo'] ?? self::supportLogoPath();
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
            'name' => $brand['name'] ?? ($fallbackBrand['name'] ?? self::supportBrandName()),
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

    public static function generateAvailableUsername(string $name, ?int $ignoreUserId = null): string
    {
        $baseUsername = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        if ($baseUsername === '') {
            $baseUsername = 'user';
        }

        $baseUsername = mb_substr($baseUsername, 0, 45);
        $candidate = $baseUsername;
        $suffix = 1;

        while (self::query()
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreUserId))
            ->whereRaw('LOWER(username) = ?', [strtolower($candidate)])
            ->exists()
        ) {
            $suffix++;
            $candidate = mb_substr($baseUsername, 0, 40).'.'.$suffix;
        }

        return $candidate;
    }

    public function scopeVisibleDirectory(Builder $query): Builder
    {
        return $query->where('role', '!=', self::ROLE_SHADOW);
    }
}
