<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_created_technical_is_forced_to_ione_department(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user@example.com',
            'phone' => '09100000000',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->post(route('admin.users.store'), [
            'name' => 'Technical User',
            'email' => 'technical-user@example.com',
            'phone' => '09222222222',
            'department' => 'DAR',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'technical-user@example.com',
            'role' => User::ROLE_TECHNICAL,
            'department' => 'iOne',
        ]);
    }
}
