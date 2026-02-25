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
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=tickets&amp;include_closed=1"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=tickets"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=history"', false);
        $response->assertDontSee('Assigned Tickets');
    }

    public function test_super_admin_technical_profile_shows_assigned_tickets_link(): void
    {
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
            'href="'.e(route('admin.tickets.index')).'?assigned_to='.$technical->id.'&amp;tab=tickets&amp;include_closed=1"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?assigned_to='.$technical->id.'&amp;tab=tickets"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?assigned_to='.$technical->id.'&amp;tab=history"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?tab=tickets&amp;assigned_to='.$technical->id.'&amp;include_closed=1"',
            false
        );
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
