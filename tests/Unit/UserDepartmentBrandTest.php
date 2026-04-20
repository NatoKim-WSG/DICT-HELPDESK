<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDepartmentBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_client_department_falls_back_to_others_brand(): void
    {
        $brandKey = User::departmentBrandKey('Unknown Agency', User::ROLE_CLIENT);
        $brandAssets = User::departmentBrandAssets('Unknown Agency', User::ROLE_CLIENT);

        $this->assertSame('others', $brandKey);
        $this->assertSame('others', $brandAssets['key']);
        $this->assertSame('Others', $brandAssets['name']);
    }

    public function test_custom_department_returns_dynamic_brand_assets(): void
    {
        Department::query()->create([
            'name' => 'NEDA',
            'slug' => 'neda',
            'logo_path' => 'images/departments/neda.png',
        ]);

        $brandKey = User::departmentBrandKey('NEDA', User::ROLE_TECHNICAL);
        $brandAssets = User::departmentBrandAssets('NEDA', User::ROLE_TECHNICAL);

        $this->assertSame('neda', $brandKey);
        $this->assertSame('neda', $brandAssets['key']);
        $this->assertSame('NEDA', $brandAssets['name']);
    }
}
