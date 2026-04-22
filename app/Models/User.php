<?php

namespace App\Models;

use App\Services\SupportBrandingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static array $departmentBrandAssetCache = [];

    protected static ?array $allowedDepartmentCache = null;

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

    public const TICKET_ASSIGNABLE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SUPER_USER,
        self::ROLE_TECHNICAL,
    ];

    public const USER_MANAGEMENT_CLIENT_ONLY_ROLES = [
        self::ROLE_CLIENT,
    ];

    public const SYSTEM_RESERVED_EMAIL_DOMAIN = '@system.local';

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

    public function isSuperAdmin(): bool
    {
        return in_array($this->normalizedRole(), [self::ROLE_ADMIN, self::ROLE_SHADOW], true);
    }

    public function isShadow(): bool
    {
        return $this->normalizedRole() === self::ROLE_SHADOW;
    }

    public function isClient(): bool
    {
        return $this->normalizedRole() === self::ROLE_CLIENT;
    }

    public function isTechnician(): bool
    {
        return $this->normalizedRole() === self::ROLE_TECHNICAL;
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    public function canAccessAdminTickets(): bool
    {
        return in_array($this->normalizedRole(), self::TICKET_CONSOLE_ROLES, true);
    }

    public function canManageTickets(): bool
    {
        return in_array($this->normalizedRole(), self::ADMIN_LEVEL_ROLES, true);
    }

    public function canManageUsers(): bool
    {
        return $this->canManageTickets();
    }

    public function canCreateClientTickets(): bool
    {
        return in_array($this->normalizedRole(), [
            self::ROLE_SUPER_USER,
            self::ROLE_TECHNICAL,
        ], true);
    }

    public function canManageTicketType(): bool
    {
        return $this->normalizedRole() === self::ROLE_SUPER_USER;
    }

    public function canManageStaffAccounts(): bool
    {
        return in_array($this->normalizedRole(), [self::ROLE_SHADOW, self::ROLE_ADMIN], true);
    }

    public function canManageUserTarget(User $targetUser): bool
    {
        if ($this->isShadow()) {
            return true;
        }

        $targetRole = $targetUser->normalizedRole();
        if ($this->normalizedRole() === self::ROLE_ADMIN) {
            return ! in_array($targetRole, [self::ROLE_SHADOW, self::ROLE_ADMIN], true);
        }

        return in_array($targetRole, self::USER_MANAGEMENT_CLIENT_ONLY_ROLES, true);
    }

    public function canEditManagedUserPassword(User $targetUser): bool
    {
        if ($this->isShadow()) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return ! $targetUser->isShadow();
        }

        return ! (
            $this->normalizedRole() === self::ROLE_SUPER_USER
            && $targetUser->normalizedRole() === self::ROLE_CLIENT
        );
    }

    public function manageableUserCreationRoles(): array
    {
        $roles = self::USER_MANAGEMENT_CLIENT_ONLY_ROLES;

        if ($this->isShadow()) {
            return array_merge($roles, [
                self::ROLE_ADMIN,
                self::ROLE_TECHNICAL,
                self::ROLE_SUPER_USER,
            ]);
        }

        if ($this->normalizedRole() === self::ROLE_ADMIN) {
            return array_merge($roles, [
                self::ROLE_TECHNICAL,
                self::ROLE_SUPER_USER,
            ]);
        }

        return $roles;
    }

    public function manageableUserRoleOptions(?User $targetUser = null): array
    {
        $roles = $this->manageableUserCreationRoles();

        if (
            $targetUser instanceof User
            && $this->canManageUserTarget($targetUser)
        ) {
            $roles[] = $targetUser->normalizedRole();
        }

        return array_values(array_unique($roles));
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
        return app(SupportBrandingService::class)->supportDepartment();
    }

    public static function supportBrandName(): string
    {
        return app(SupportBrandingService::class)->supportBrandName();
    }

    public static function supportOrganizationName(): string
    {
        return app(SupportBrandingService::class)->supportOrganizationName();
    }

    public static function supportTeamName(): string
    {
        return app(SupportBrandingService::class)->supportTeamName();
    }

    public static function supportLogoPath(): string
    {
        return app(SupportBrandingService::class)->supportLogoPath();
    }

    public static function supportLogoUrl(): string
    {
        return app(SupportBrandingService::class)->supportLogoUrl();
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

    public function isSystemReplacementAccount(): bool
    {
        return self::emailUsesReservedSystemDomain($this->email);
    }

    public static function managedDepartmentForRole(string $role, string $department): string
    {
        $trimmedDepartment = trim($department);

        return $trimmedDepartment !== ''
            ? $trimmedDepartment
            : self::supportDepartment();
    }

    public static function emailUsesReservedSystemDomain(?string $email): bool
    {
        return str_ends_with(strtolower(trim((string) $email)), self::SYSTEM_RESERVED_EMAIL_DOMAIN);
    }

    public static function departmentBrandKey(?string $department, ?string $role = null): string
    {
        return app(SupportBrandingService::class)->departmentBrandKey($department, $role);
    }

    public static function departmentBrandMap(): array
    {
        return app(SupportBrandingService::class)->departmentBrandMap();
    }

    public static function departmentBrandAssets(?string $department, ?string $role = null): array
    {
        $cacheKey = mb_strtolower(trim((string) $department)).'|'.self::normalizeRole($role);

        if (isset(self::$departmentBrandAssetCache[$cacheKey])) {
            return self::$departmentBrandAssetCache[$cacheKey];
        }
        $assets = app(SupportBrandingService::class)->departmentBrandAssets($department, $role);

        self::$departmentBrandAssetCache[$cacheKey] = $assets;

        return $assets;
    }

    public static function allowedDepartments(): array
    {
        if (self::$allowedDepartmentCache !== null) {
            return self::$allowedDepartmentCache;
        }

        if (Schema::hasTable('departments')) {
            $departments = Department::query()
                ->orderByRaw('LOWER(name)')
                ->pluck('name')
                ->all();

            if ($departments !== []) {
                return self::$allowedDepartmentCache = $departments;
            }
        }

        $departments = self::ALLOWED_DEPARTMENTS;
        usort($departments, fn (string $left, string $right) => strnatcasecmp($left, $right));

        return self::$allowedDepartmentCache = $departments;
    }

    public static function flushDepartmentCaches(): void
    {
        self::$allowedDepartmentCache = null;
        self::$departmentBrandAssetCache = [];
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
