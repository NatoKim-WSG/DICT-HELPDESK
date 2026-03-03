<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientTicketHistoryTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_history_tab_shows_only_resolved_and_closed_tickets(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Open ticket item',
            'status' => 'open',
            'ticket_number' => 'TK-HISOPEN1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Resolved ticket item',
            'status' => 'resolved',
            'ticket_number' => 'TK-HISRES1',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'Closed ticket item',
            'status' => 'closed',
            'ticket_number' => 'TK-HISCLO1',
        ]));

        $response = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('History');
        $response->assertSee('Resolved ticket item');
        $response->assertSee('Closed ticket item');
        $response->assertDontSee('Open ticket item');
    }

    public function test_client_history_tab_status_filter_handles_invalid_status_safely(): void
    {
        [$client, $category] = $this->createClientAndCategory();

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History resolved ticket',
            'status' => 'resolved',
            'ticket_number' => 'TK-HISRES2',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History closed ticket',
            'status' => 'closed',
            'ticket_number' => 'TK-HISCLO2',
        ]));

        Ticket::create($this->ticketPayload($client, $category, [
            'subject' => 'History open ticket',
            'status' => 'open',
            'ticket_number' => 'TK-HISOPEN2',
        ]));

        $response = $this->actingAs($client)->get(route('client.tickets.index', [
            'tab' => 'history',
            'status' => 'open',
        ]));

        $response->assertOk();
        $response->assertSee('History resolved ticket');
        $response->assertSee('History closed ticket');
        $response->assertDontSee('History open ticket');
    }

    private function createClientAndCategory(): array
    {
        config(['legal.require_acceptance' => false]);

        $client = User::create([
            'name' => 'History Client',
            'email' => 'history-client@example.com',
            'phone' => '09189990003',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'History Category',
            'description' => 'History category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$client, $category];
    }

    private function ticketPayload(User $client, Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => $client->name,
            'contact_number' => '09189990003',
            'email' => $client->email,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'History ticket',
            'description' => 'History ticket description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ], $overrides);
    }
}
