<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketRepliesFeedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_replies_feed_excludes_internal_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-client@example.com', User::ROLE_SHADOW, 'iOne');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client update',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Public support reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow public reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->every(fn (array $reply) => ! $reply['is_internal']));
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['from_support'] === true));
        $this->assertFalse(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow public reply'));
    }

    public function test_super_user_replies_feed_includes_internal_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($superUser)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['is_internal'] === true));
    }

    public function test_non_shadow_admin_replies_feed_hides_shadow_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-admin@example.com', User::ROLE_SHADOW, 'iOne');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow private support note',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($superUser)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertFalse(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow private support note'));
    }

    public function test_shadow_replies_feed_includes_shadow_replies_for_shadow_viewer(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-shadow@example.com', User::ROLE_SHADOW, 'iOne');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow private support note',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($shadow)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow private support note'));
    }

    private function seedTicketWithUsers(): array
    {
        $client = $this->createUser('Client User', 'client-feed@example.com', User::ROLE_CLIENT, 'iOne');
        $superUser = $this->createUser('Super User', 'super-user-feed@example.com', User::ROLE_SUPER_USER, 'iOne');

        $category = Category::create([
            'name' => 'General',
            'description' => 'General concerns',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester Name',
            'contact_number' => '09120000000',
            'email' => 'requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $superUser, $ticket];
    }

    private function createUser(string $name, string $email, string $role, string $department): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09123450000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
