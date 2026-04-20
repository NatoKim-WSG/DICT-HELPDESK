<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SupportBrandingService
{
    private ?Collection $departmentCache = null;

    public static function flushDepartmentCache(): void
    {
        app()->forgetInstance(self::class);
    }

    public function supportDepartment(): string
    {
        return trim((string) config('helpdesk.support_department', 'iOne')) ?: 'iOne';
    }

    public function supportBrandName(): string
    {
        return trim((string) config('helpdesk.support_brand_name', $this->supportDepartment())) ?: $this->supportDepartment();
    }

    public function supportOrganizationName(): string
    {
        return trim((string) config('helpdesk.support_organization_name', 'iOne Resources Inc.')) ?: 'iOne Resources Inc.';
    }

    public function supportTeamName(): string
    {
        return trim((string) config('helpdesk.support_team_name', 'iOne Technical Team')) ?: 'iOne Technical Team';
    }

    public function supportLogoPath(): string
    {
        return trim((string) config('helpdesk.support_logo_path', 'images/iOne Logo.png')) ?: 'images/iOne Logo.png';
    }

    public function supportLogoUrl(): string
    {
        $logoPath = $this->supportLogoPath();

        if (! file_exists(public_path($logoPath))) {
            $logoPath = 'images/iOne Logo.png';
        }

        return asset($logoPath);
    }

    public function departmentBrandKey(?string $department, ?string $role = null): string
    {
        $normalizedDepartment = trim((string) $department);
        $normalizedRole = User::normalizeRole($role);

        if ($normalizedDepartment === '' && in_array($normalizedRole, User::TICKET_CONSOLE_ROLES, true)) {
            $normalizedDepartment = $this->supportDepartment();
        }

        if ($dynamicDepartment = $this->findDynamicDepartment($normalizedDepartment)) {
            return $dynamicDepartment->slug;
        }

        $departmentToken = preg_replace('/[^a-z0-9]+/', '', strtolower($normalizedDepartment));

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
            default => 'others',
        };
    }

    public function departmentBrandMap(): array
    {
        $brandMap = [
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

        foreach ($this->dynamicDepartments() as $department) {
            $brandMap[(string) $department->slug] = [
                'name' => (string) $department->name,
                'logo' => $department->resolved_logo_path,
            ];
        }

        return $brandMap;
    }

    public function departmentBrandAssets(?string $department, ?string $role = null): array
    {
        if ($dynamicDepartment = $this->findDynamicDepartment($department ?: (in_array(User::normalizeRole($role), User::TICKET_CONSOLE_ROLES, true) ? $this->supportDepartment() : null))) {
            return [
                'key' => (string) $dynamicDepartment->slug,
                'name' => (string) $dynamicDepartment->name,
                'logo_path' => $dynamicDepartment->resolved_logo_path,
                'logo_url' => $dynamicDepartment->logo_url,
            ];
        }

        $brandKey = $this->departmentBrandKey($department, $role);
        $brandMap = $this->departmentBrandMap();
        $supportBrandKey = $this->departmentBrandKey($this->supportDepartment());
        $fallbackBrand = $brandMap[$supportBrandKey] ?? $brandMap['ione'];
        $brand = $brandMap[$brandKey] ?? $fallbackBrand;
        $defaultLogoPath = $fallbackBrand['logo'] ?? $this->supportLogoPath();
        $logoPath = $brand['logo'] ?? $defaultLogoPath;

        if (! file_exists(public_path($logoPath))) {
            $logoPath = $defaultLogoPath;
        }

        return [
            'key' => $brandKey,
            'name' => $brand['name'] ?? ($fallbackBrand['name'] ?? $this->supportBrandName()),
            'logo_path' => $logoPath,
            'logo_url' => asset($logoPath),
        ];
    }

    private function dynamicDepartments(): Collection
    {
        if ($this->departmentCache !== null) {
            return $this->departmentCache;
        }

        if (! Schema::hasTable('departments')) {
            return $this->departmentCache = collect();
        }

        return $this->departmentCache = Department::query()
            ->orderByRaw("LOWER(name)")
            ->get(['id', 'name', 'slug', 'logo_path']);
    }

    private function findDynamicDepartment(?string $department): ?Department
    {
        $normalizedDepartment = trim((string) $department);
        if ($normalizedDepartment === '') {
            return null;
        }

        $normalizedSlug = Str::slug($normalizedDepartment);

        return $this->dynamicDepartments()->first(function (Department $candidate) use ($normalizedDepartment, $normalizedSlug): bool {
            return strcasecmp((string) $candidate->name, $normalizedDepartment) === 0
                || (string) $candidate->slug === $normalizedSlug;
        });
    }
}
