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
        [$client, $admin, $ticket] = $this->seedTicketWithUsers();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client update',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Internal admin note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Public admin reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->every(fn (array $reply) => !$reply['is_internal']));
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['from_support'] === true));
    }

    public function test_admin_replies_feed_includes_internal_replies(): void
    {
        [$client, $admin, $ticket] = $this->seedTicketWithUsers();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Internal admin note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['is_internal'] === true));
    }

    private function seedTicketWithUsers(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'client-feed@example.com',
            'phone' => '09123450000',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-feed@example.com',
            'phone' => '09123451111',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

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

        return [$client, $admin, $ticket];
    }
}
