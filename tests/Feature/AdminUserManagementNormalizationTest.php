<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_cannot_create_technical_account(): void
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

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'technical-user@example.com',
        ]);
    }

    public function test_super_admin_can_still_create_technical_account(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '09100000001',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
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

    public function test_super_user_cannot_edit_technical_account(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-edit@example.com',
            'phone' => '09100000002',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Technical User',
            'email' => 'technical-edit@example.com',
            'phone' => '09100000003',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $editResponse = $this->actingAs($superUser)->get(route('admin.users.edit', $technical));
        $editResponse->assertRedirect(route('admin.users.index'));
        $editResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $updateResponse = $this->actingAs($superUser)->put(route('admin.users.update', $technical), [
            'name' => 'Technical Updated',
            'email' => 'technical-edit@example.com',
            'phone' => '09100000003',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('admin.users.index'));
        $updateResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $technical->refresh();
        $this->assertSame('Technical User', $technical->name);
    }

    public function test_super_user_user_index_shows_clients_segment(): void
    {
        $superUser = User::create([
            'name' => 'Super User Segment',
            'email' => 'super-user-segment@example.com',
            'phone' => '09100000004',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Client Segment',
            'email' => 'client-segment@example.com',
            'phone' => '09100000005',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Client Accounts');
        $response->assertDontSee('Staff Accounts');
    }

    public function test_super_user_cannot_change_client_password(): void
    {
        $superUser = User::create([
            'name' => 'Super User Password Guard',
            'email' => 'super-user-password-guard@example.com',
            'phone' => '09100000006',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Guard',
            'email' => 'client-password-guard@example.com',
            'phone' => '09100000007',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('oldpassword123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.users.edit', $client))
            ->put(route('admin.users.update', $client), [
                'name' => 'Client Password Guard',
                'email' => 'client-password-guard@example.com',
                'phone' => '09100000007',
                'department' => 'DICT',
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect(route('admin.users.edit', $client));
        $response->assertSessionHasErrors(['password', 'password_confirmation']);

        $client->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $client->password));
        $this->assertFalse(Hash::check('newpassword123', $client->password));
    }

    public function test_super_admin_can_still_change_client_password(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Password',
            'email' => 'super-admin-password@example.com',
            'phone' => '09100000008',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Update',
            'email' => 'client-password-update@example.com',
            'phone' => '09100000009',
            'department' => 'DAR',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('oldpassword123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'Client Password Update',
            'email' => 'client-password-update@example.com',
            'phone' => '09100000009',
            'department' => 'DAR',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $client->refresh();
        $this->assertTrue(Hash::check('newpassword123', $client->password));
    }

    public function test_super_user_edit_client_form_hides_password_fields(): void
    {
        $superUser = User::create([
            'name' => 'Super User Form Check',
            'email' => 'super-user-form-check@example.com',
            'phone' => '09100000010',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Form Check',
            'email' => 'client-form-check@example.com',
            'phone' => '09100000011',
            'department' => 'DEPED',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.users.edit', $client));

        $response->assertOk();
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('name="password_confirmation"', false);
        $response->assertSee('Password changes for client accounts are restricted to admins.', false);
    }

    public function test_staff_index_keeps_role_hierarchy_and_sorts_names_alphabetically_within_role_group(): void
    {
        $superAdmin = User::create([
            'name' => 'Zulu Super Admin',
            'email' => 'zulu-super-admin@example.com',
            'phone' => '09100000012',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Bravo Super Admin',
            'email' => 'bravo-super-admin@example.com',
            'phone' => '09100000013',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Alpha Super User',
            'email' => 'alpha-super-user@example.com',
            'phone' => '09100000014',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Beta Legacy Admin',
            'email' => 'beta-legacy-admin@example.com',
            'phone' => '09100000015',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Aaron Technical',
            'email' => 'aaron-technical@example.com',
            'phone' => '09100000016',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.index'));
        $response->assertOk();

        $content = $response->getContent();
        $tbodyStart = strpos($content, '<tbody');
        $tbodyEnd = strpos($content, '</tbody>');
        $this->assertNotFalse($tbodyStart);
        $this->assertNotFalse($tbodyEnd);

        $tbodyContent = substr($content, (int) $tbodyStart, ((int) $tbodyEnd - (int) $tbodyStart) + 8);

        $expectedOrder = [
            'Bravo Super Admin',
            'Zulu Super Admin',
            'Alpha Super User',
            'Beta Legacy Admin',
            'Aaron Technical',
        ];

        $previousPosition = -1;
        foreach ($expectedOrder as $expectedName) {
            $position = strpos($tbodyContent, $expectedName);
            $this->assertNotFalse($position, "Missing expected name in users table: {$expectedName}");
            $this->assertGreaterThan($previousPosition, $position, "Unexpected order in users table around: {$expectedName}");
            $previousPosition = $position;
        }
    }

    public function test_shadow_user_can_view_managed_password_access_for_non_shadow_user(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Access',
            'email' => 'shadow-password-access@example.com',
            'phone' => '09100000017',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Access',
            'email' => 'client-password-access@example.com',
            'phone' => '09100000018',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make(DefaultPasswordResolver::user()),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('Password Access');
        $response->assertSee('Current Login Password');
        $response->assertSee(DefaultPasswordResolver::user());
        $response->assertSee('managed default password');
    }

    public function test_shadow_user_can_reset_managed_user_password_to_default(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Reset',
            'email' => 'shadow-password-reset@example.com',
            'phone' => '09100000019',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Reset Target',
            'email' => 'client-reset-target@example.com',
            'phone' => '09100000020',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->post(route('admin.users.password.reset-default', $client));

        $response->assertRedirect(route('admin.users.show', $client));
        $response->assertSessionHas('success', 'Password access is now available for this account.');

        $client->refresh();
        $this->assertTrue(Hash::check(DefaultPasswordResolver::user(), $client->password));
    }

    public function test_shadow_user_can_view_custom_password_when_reveal_value_exists(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Custom Reveal',
            'email' => 'shadow-custom-reveal@example.com',
            'phone' => '09100000023',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Custom Reveal',
            'email' => 'client-custom-reveal@example.com',
            'phone' => '09100000024',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'password_reveal' => 'custompass123',
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('custompass123');
        $response->assertSee('custom password and it is available for Shadow review');
    }

    public function test_non_shadow_user_cannot_reset_managed_user_password(): void
    {
        $admin = User::create([
            'name' => 'Admin Cannot Reset',
            'email' => 'admin-cannot-reset@example.com',
            'phone' => '09100000021',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('adminpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Protected',
            'email' => 'client-protected@example.com',
            'phone' => '09100000022',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.password.reset-default', $client));
        $response->assertForbidden();

        $client->refresh();
        $this->assertTrue(Hash::check('custompass123', $client->password));
    }
}
