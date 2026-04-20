<?php

namespace App\Models;

use App\Services\SupportBrandingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            User::flushDepartmentCaches();
            SupportBrandingService::flushDepartmentCache();
        });

        static::deleted(function (): void {
            User::flushDepartmentCaches();
            SupportBrandingService::flushDepartmentCache();
        });
    }

    public function getResolvedLogoPathAttribute(): string
    {
        $logoPath = trim((string) $this->logo_path);
        if ($logoPath !== '' && file_exists(public_path($logoPath))) {
            return $logoPath;
        }

        return 'images/Others Logo.png';
    }

    public function getLogoUrlAttribute(): string
    {
        return asset($this->resolved_logo_path);
    }

    public function usesManagedLogo(): bool
    {
        return str_starts_with((string) $this->logo_path, 'images/departments/');
    }

    public function managedLogoStoragePath(): ?string
    {
        if (! $this->usesManagedLogo()) {
            return null;
        }

        return Str::after((string) $this->logo_path, 'images/departments/');
    }

    public function deleteManagedLogoIfPresent(): void
    {
        $storagePath = $this->managedLogoStoragePath();
        if ($storagePath === null) {
            return;
        }

        Storage::disk('department-logos')->delete($storagePath);
    }

    public static function generateAvailableSlug(string $name, ?int $ignoreDepartmentId = null): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'department';
        }

        $candidate = $baseSlug;
        $suffix = 1;

        while (self::query()
            ->when($ignoreDepartmentId !== null, fn ($query) => $query->where('id', '!=', $ignoreDepartmentId))
            ->where('slug', $candidate)
            ->exists()
        ) {
            $suffix++;
            $candidate = $baseSlug.'-'.$suffix;
        }

        return $candidate;
    }
}
