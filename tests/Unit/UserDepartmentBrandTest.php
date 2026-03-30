<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserDepartmentBrandTest extends TestCase
{
    public function test_unknown_client_department_falls_back_to_others_brand(): void
    {
        $brandKey = User::departmentBrandKey('Unknown Agency', User::ROLE_CLIENT);
        $brandAssets = User::departmentBrandAssets('Unknown Agency', User::ROLE_CLIENT);

        $this->assertSame('others', $brandKey);
        $this->assertSame('others', $brandAssets['key']);
        $this->assertSame('Others', $brandAssets['name']);
    }
}
