<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsDepartmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_account_settings_route(): void
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

        $response = $this->actingAs($client)->get(route('account.settings'));
        $response->assertForbidden();
    }

    public function test_super_user_cannot_change_username_department_or_email_from_account_settings(): void
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
            'name' => 'Super User Updated',
            'email' => 'super-user-updated@example.com',
            'phone' => '09111112222',
            'department' => 'DAR',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superUser->refresh();
        $this->assertSame('Super User A', $superUser->name);
        $this->assertSame('super-user-a@example.com', $superUser->email);
        $this->assertSame('iOne', $superUser->department);
        $this->assertSame('09111112222', $superUser->phone);
    }

    public function test_technical_can_access_account_settings_and_only_change_phone_and_password(): void
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

        $settingsResponse = $this->actingAs($technical)->get(route('account.settings'));
        $settingsResponse->assertOk();

        $response = $this->actingAs($technical)->put(route('account.settings.update'), [
            'name' => 'Technical Updated',
            'email' => 'technical-updated@example.com',
            'phone' => '09122224444',
            'department' => 'DICT',
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('account.settings'));

        $technical->refresh();
        $this->assertSame('Technical A', $technical->name);
        $this->assertSame('technical-a@example.com', $technical->email);
        $this->assertSame('iOne', $technical->department);
        $this->assertSame('09122224444', $technical->phone);
        $this->assertTrue(Hash::check('newpassword123', $technical->password));
    }

    public function test_super_admin_can_change_department_from_account_settings(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin A',
            'email' => 'super-admin-a@example.com',
            'phone' => '09100001111',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('account.settings.update'), [
            'name' => 'Super Admin A',
            'email' => 'super-admin-a@example.com',
            'phone' => '09100001111',
            'department' => 'DAR',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superAdmin->refresh();
        $this->assertSame('DAR', $superAdmin->department);
    }

    public function test_super_admin_and_non_super_admin_have_expected_department_input_rendering(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin View',
            'email' => 'super-admin-view@example.com',
            'phone' => '09100002222',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superUser = User::create([
            'name' => 'Super User View',
            'email' => 'super-user-view@example.com',
            'phone' => '09100003333',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superAdminResponse = $this->actingAs($superAdmin)->get(route('account.settings'));
        $superAdminResponse->assertOk();
        $this->assertStringContainsString('<select', $superAdminResponse->getContent());
        $this->assertStringContainsString('name="department"', $superAdminResponse->getContent());

        auth()->logout();

        $superUserResponse = $this->actingAs($superUser)->get(route('account.settings'));
        $superUserResponse->assertOk();
        $this->assertStringContainsString('name="department"', $superUserResponse->getContent());
        $this->assertStringContainsString('readonly aria-readonly=true', $superUserResponse->getContent());
        $this->assertStringContainsString('Only admins can change account email addresses.', $superUserResponse->getContent());
        $this->assertStringNotContainsString('Username updates are disabled for your account role.', $superUserResponse->getContent());
        $this->assertStringContainsString('Required when changing your password.', $superUserResponse->getContent());

        $technical = User::create([
            'name' => 'Technical View',
            'email' => 'technical-view@example.com',
            'phone' => '09100004444',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        auth()->logout();

        $technicalResponse = $this->actingAs($technical)->get(route('account.settings'));
        $technicalResponse->assertOk();
        $this->assertStringNotContainsString('Username updates are disabled for your account role.', $technicalResponse->getContent());
    }

    public function test_changing_email_requires_current_password_for_super_admin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Email',
            'email' => 'super-admin-email@example.com',
            'phone' => '09111112222',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->from(route('account.settings'))
            ->put(route('account.settings.update'), [
                'name' => 'Super Admin Email',
                'email' => 'super-admin-email-new@example.com',
                'phone' => '09111112222',
                'department' => 'iOne',
            ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHasErrors('current_password');
    }

    public function test_changing_email_with_current_password_succeeds_for_super_admin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Email 2',
            'email' => 'super-admin-email2@example.com',
            'phone' => '09111113333',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('account.settings.update'), [
            'name' => 'Super Admin Email 2',
            'email' => 'super-admin-email2-new@example.com',
            'phone' => '09111113333',
            'department' => 'iOne',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superAdmin->refresh();
        $this->assertSame('super-admin-email2-new@example.com', $superAdmin->email);
    }
}
