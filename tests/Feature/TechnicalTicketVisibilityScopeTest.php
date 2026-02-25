<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TechnicalTicketVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_ticket_index_only_lists_assigned_tickets(): void
    {
        [$technical, $client, $category] = $this->seedActorData();

        $assignedTicket = Ticket::create([
            'name' => 'Assigned Requester',
            'contact_number' => '09130000001',
            'email' => 'assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned ticket',
            'description' => 'Visible to technical',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        $unassignedTicket = Ticket::create([
            'name' => 'Unassigned Requester',
            'contact_number' => '09130000002',
            'email' => 'unassigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Unassigned ticket',
            'description' => 'Should be hidden',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($technical)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $assignedTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $unassignedTicket), false);
    }

    public function test_technical_user_cannot_open_or_update_unassigned_ticket(): void
    {
        [$technical, $client, $category] = $this->seedActorData();

        $unassignedTicket = Ticket::create([
            'name' => 'Unassigned Requester',
            'contact_number' => '09130000003',
            'email' => 'unassigned-route-check@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Unassigned route check',
            'description' => 'Should be forbidden',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $showResponse = $this->actingAs($technical)->get(route('admin.tickets.show', $unassignedTicket));
        $showResponse->assertForbidden();

        $statusResponse = $this->actingAs($technical)->post(route('admin.tickets.status', $unassignedTicket), [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertForbidden();
    }

    private function seedActorData(): array
    {
        $technical = User::create([
            'name' => 'Scoped Tech',
            'email' => 'scoped-tech@example.com',
            'phone' => '09130000000',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Scoped Client',
            'email' => 'scoped-client@example.com',
            'phone' => '09131112222',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Scoped Category',
            'description' => 'Scoped test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$technical, $client, $category];
    }
}
