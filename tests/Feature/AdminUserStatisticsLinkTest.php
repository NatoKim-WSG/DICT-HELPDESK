<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserStatisticsLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_client_profile_statistics_have_clickable_ticket_links(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser(
            'Stats Super User',
            'stats-super-user@example.com',
            User::ROLE_SUPER_USER,
            'iOne'
        );
        $client = $this->createUser(
            'Stats Client',
            'stats-client@example.com',
            User::ROLE_CLIENT,
            'DICT'
        );

        $response = $this->actingAs($superUser)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('User Statistics');
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=all"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=tickets"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=history"', false);
        $response->assertDontSee('Assigned Tickets');
    }

    public function test_super_admin_technical_profile_shows_assigned_tickets_link(): void
    {
        config(['legal.require_acceptance' => false]);

        $superAdmin = $this->createUser(
            'Stats Super Admin',
            'stats-super-admin@example.com',
            User::ROLE_SUPER_ADMIN,
            'iOne'
        );
        $technical = $this->createUser(
            'Stats Technical',
            'stats-technical@example.com',
            User::ROLE_TECHNICAL,
            'iOne'
        );

        $response = $this->actingAs($superAdmin)->get(route('admin.users.show', $technical));

        $response->assertOk();
        $response->assertSee('Assigned Tickets');
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=all"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=tickets"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=history"',
            false
        );
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?tab=tickets&amp;assigned_to='.$technical->id.'"', false);
    }

    public function test_admin_dashboard_total_ticket_actions_link_to_all_tab(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser(
            'Dashboard Stats User',
            'dashboard-stats-user@example.com',
            User::ROLE_SUPER_USER,
            'iOne'
        );

        $response = $this->actingAs($superUser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.e(route('admin.tickets.index', ['tab' => 'all'])).'"', false);
    }

    private function createUser(string $name, string $email, string $role, string $department): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09179990000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
