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

    public function test_super_user_can_change_department_from_account_settings(): void
    {
        $superUser = User::create([
            'name' => 'Super User A',
            'email' => 'super-user-a@example.com',
            'phone' => '09111111111',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->put(route('account.settings.update'), [
            'name' => 'Super User A',
            'email' => 'super-user-a@example.com',
            'phone' => '09111111111',
            'department' => 'DAR',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superUser->refresh();
        $this->assertSame('DAR', $superUser->department);
    }

    public function test_super_user_and_super_admin_see_department_dropdown_on_account_settings(): void
    {
        $roles = [User::ROLE_SUPER_USER, User::ROLE_SUPER_ADMIN];

        foreach ($roles as $index => $role) {
            $user = User::create([
                'name' => 'Admin Role ' . $index,
                'email' => "admin-role-{$index}@example.com",
                'phone' => '09123450000',
                'department' => 'iOne',
                'role' => $role,
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);

            $response = $this->actingAs($user)->get(route('account.settings'));
            $response->assertOk();
            $this->assertStringContainsString('<select', $response->getContent());
            $this->assertStringContainsString('name="department"', $response->getContent());
        }
    }

    public function test_technical_cannot_change_department_from_account_settings(): void
    {
        $technical = User::create([
            'name' => 'Technical A',
            'email' => 'technical-a@example.com',
            'phone' => '09122223333',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($technical)->put(route('account.settings.update'), [
            'name' => 'Technical A Updated',
            'email' => 'technical-a@example.com',
            'phone' => '09122223333',
            'department' => 'DAR',
        ]);

        $response->assertRedirect(route('account.settings'));

        $technical->refresh();
        $this->assertSame('Technical A Updated', $technical->name);
        $this->assertSame('iOne', $technical->department);
    }

    public function test_technical_sees_department_as_read_only_on_account_settings(): void
    {
        $technical = User::create([
            'name' => 'Technical View',
            'email' => 'technical-view@example.com',
            'phone' => '09122224444',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($technical)->get(route('account.settings'));
        $response->assertOk();
        $this->assertStringContainsString('name="department"', $response->getContent());
        $this->assertStringContainsString('readonly aria-readonly=true', $response->getContent());
    }

    public function test_changing_email_requires_current_password(): void
    {
        $superUser = User::create([
            'name' => 'Super User Email',
            'email' => 'super-user-email@example.com',
            'phone' => '09111112222',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('account.settings'))
            ->put(route('account.settings.update'), [
                'name' => 'Super User Email',
                'email' => 'super-user-email-new@example.com',
                'phone' => '09111112222',
                'department' => 'iOne',
            ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHasErrors('current_password');
    }

    public function test_changing_email_with_current_password_succeeds(): void
    {
        $superUser = User::create([
            'name' => 'Super User Email 2',
            'email' => 'super-user-email2@example.com',
            'phone' => '09111113333',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->put(route('account.settings.update'), [
            'name' => 'Super User Email 2',
            'email' => 'super-user-email2-new@example.com',
            'phone' => '09111113333',
            'department' => 'iOne',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superUser->refresh();
        $this->assertSame('super-user-email2-new@example.com', $superUser->email);
    }
}
