<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemLogAccessAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_must_unlock_system_logs_with_password(): void
    {
        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'shadow-log-access@example.com',
            'phone' => '09123450001',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $blockedResponse = $this->actingAs($shadow)->get(route('admin.system-logs.index'));
        $blockedResponse->assertRedirect(route('admin.system-logs.unlock.show'));

        $failedUnlock = $this->actingAs($shadow)
            ->from(route('admin.system-logs.unlock.show'))
            ->post(route('admin.system-logs.unlock.store'), [
                'password' => 'wrong-password',
            ]);
        $failedUnlock->assertRedirect(route('admin.system-logs.unlock.show'));
        $failedUnlock->assertSessionHasErrors('password');

        $unlockResponse = $this->actingAs($shadow)->post(route('admin.system-logs.unlock.store'), [
            'password' => 'ShadowPass123!',
        ]);
        $unlockResponse->assertRedirect(route('admin.system-logs.index'));

        $openResponse = $this->actingAs($shadow)->get(route('admin.system-logs.index'));
        $openResponse->assertOk();
    }

    public function test_non_shadow_users_cannot_access_system_logs(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-no-log-access@example.com',
            'phone' => '09123450002',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('AdminPass123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system-logs.unlock.show'));
        $response->assertForbidden();
    }

    public function test_account_settings_update_writes_system_log_without_password_details(): void
    {
        $shadow = User::create([
            'name' => 'Settings Shadow',
            'email' => 'settings-shadow@example.com',
            'phone' => '09123450003',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->put(route('account.settings.update'), [
            'name' => 'Settings Shadow Updated',
            'email' => 'settings-shadow@example.com',
            'phone' => '09124440003',
            'department' => 'DICT',
            'current_password' => 'ShadowPass123!',
            'password' => 'NewShadowPass123!',
            'password_confirmation' => 'NewShadowPass123!',
        ]);
        $response->assertRedirect(route('account.settings'));

        $log = SystemLog::query()
            ->where('event_type', 'account.settings.updated')
            ->where('target_id', $shadow->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('account', $log->category);
        $encodedMetadata = json_encode($log->metadata);
        $this->assertIsString($encodedMetadata);
        $this->assertStringNotContainsString('NewShadowPass123!', $encodedMetadata);
        $this->assertStringNotContainsString('password', strtolower($encodedMetadata));
    }

    public function test_ticket_assignment_writes_system_log_entry(): void
    {
        $shadow = User::create([
            'name' => 'Ticket Shadow',
            'email' => 'ticket-shadow@example.com',
            'phone' => '09123450004',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Ticket Technical',
            'email' => 'ticket-technical@example.com',
            'phone' => '09123450005',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('TechPass123!'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Ticket Client',
            'email' => 'ticket-client@example.com',
            'phone' => '09123450006',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('ClientPass123!'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'System Log Category',
            'description' => 'Category for system log assignment test',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09123456789',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assignment system log check',
            'description' => 'Checking assignment logging.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($shadow)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.assign', $ticket), [
                'assigned_to' => $technical->id,
            ]);
        $response->assertRedirect(route('admin.tickets.show', $ticket));

        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'ticket.assignment.updated',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
        ]);
    }
}
