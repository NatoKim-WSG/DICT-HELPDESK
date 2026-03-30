<?php

namespace App\Services;

use App\Models\User;

class SupportBrandingService
{
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
        $normalizedDepartment = strtolower(trim((string) $department));
        $departmentToken = preg_replace('/[^a-z0-9]+/', '', $normalizedDepartment);
        $normalizedRole = User::normalizeRole($role);

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
            in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL], true) => $this->departmentBrandKey($this->supportDepartment()),
            default => 'others',
        };
    }

    public function departmentBrandMap(): array
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

    public function departmentBrandAssets(?string $department, ?string $role = null): array
    {
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
}
