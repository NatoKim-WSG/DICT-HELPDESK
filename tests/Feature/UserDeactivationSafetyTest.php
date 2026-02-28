<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDeactivationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_user_route_cannot_delete_locked_user(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-locked-delete@example.com',
            'phone' => '09110001111',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $lockedClient = User::create([
            'name' => 'Locked Client',
            'email' => 'locked-client@example.com',
            'phone' => '09110002222',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_profile_locked' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->delete(route('admin.users.destroy', $lockedClient));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error', 'Locked users cannot be deleted. Unlock the profile first.');

        $this->assertDatabaseHas('users', [
            'id' => $lockedClient->id,
            'email' => 'locked-client@example.com',
        ]);
    }

    public function test_destroy_user_route_deletes_user_and_preserves_ticket_history(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-preserve@example.com',
            'phone' => '09110001111',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Preserve',
            'email' => 'client-preserve@example.com',
            'phone' => '09110002222',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Account',
            'description' => 'Account issue',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Client Preserve',
            'contact_number' => '09110002222',
            'email' => 'client-preserve@example.com',
            'province' => 'NCR',
            'municipality' => 'Quezon City',
            'subject' => 'Need account help',
            'description' => 'Issue details',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Initial client update',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($superUser)
            ->delete(route('admin.users.destroy', $client));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $client->id,
        ]);

        $archivedClient = User::where('email', 'deleted.client@system.local')->firstOrFail();

        $this->assertFalse((bool) $archivedClient->is_active);
        $this->assertSame(User::ROLE_CLIENT, $archivedClient->role);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'user_id' => $archivedClient->id,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'id' => $reply->id,
            'ticket_id' => $ticket->id,
            'user_id' => $archivedClient->id,
        ]);
    }
}
