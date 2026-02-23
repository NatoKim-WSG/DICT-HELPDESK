<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsDepartmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_change_department_from_account_settings(): void
    {
        $client = User::create([
            'name' => 'Client A',
            'email' => 'client-a@example.com',
            'phone' => '09123456789',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->put(route('account.settings.update'), [
            'name' => 'Client A Updated',
            'email' => 'client-a@example.com',
            'phone' => '09999999999',
            'department' => 'DEPED',
        ]);

        $response->assertRedirect(route('account.settings'));

        $client->refresh();
        $this->assertSame('Client A Updated', $client->name);
        $this->assertSame('DICT', $client->department);
    }

    public function test_admin_can_change_department_from_account_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin A',
            'email' => 'admin-a@example.com',
            'phone' => '09111111111',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('account.settings.update'), [
            'name' => 'Admin A',
            'email' => 'admin-a@example.com',
            'phone' => '09111111111',
            'department' => 'DAR',
        ]);

        $response->assertRedirect(route('account.settings'));

        $admin->refresh();
        $this->assertSame('DAR', $admin->department);
    }
}
