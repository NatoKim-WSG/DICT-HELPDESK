<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_technician_is_forced_to_ione_department(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-user@example.com',
            'phone' => '09100000000',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Tech User',
            'email' => 'tech-user@example.com',
            'phone' => '09222222222',
            'department' => 'DAR',
            'role' => User::ROLE_TECHNICIAN,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'tech-user@example.com',
            'role' => User::ROLE_TECHNICIAN,
            'department' => 'iOne',
        ]);
    }
}
