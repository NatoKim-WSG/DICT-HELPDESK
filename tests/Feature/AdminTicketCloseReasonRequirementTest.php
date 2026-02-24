<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketCloseReasonRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_status_update_requires_close_reason_when_closing(): void
    {
        [$admin, $ticket] = $this->seedAdminAndTicket();

        $response = $this->actingAs($admin)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'closed',
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHasErrors('close_reason');

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    public function test_admin_status_update_closes_ticket_when_reason_is_provided(): void
    {
        [$admin, $ticket] = $this->seedAdminAndTicket();

        $response = $this->actingAs($admin)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'closed',
                'close_reason' => 'Client stopped responding after resolution.',
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_internal' => true,
        ]);

        $this->assertTrue(
            TicketReply::where('ticket_id', $ticket->id)
                ->where('user_id', $admin->id)
                ->where('is_internal', true)
                ->where('message', 'like', '%Reason: Client stopped responding after resolution.%')
                ->exists()
        );
    }

    public function test_admin_quick_update_requires_close_reason_when_closing(): void
    {
        [$admin, $ticket] = $this->seedAdminAndTicket();

        $response = $this->actingAs($admin)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.quick-update', $ticket), [
                'assigned_to' => '',
                'status' => 'closed',
                'priority' => 'medium',
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHasErrors('close_reason');

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    /**
     * @return array{0: User, 1: Ticket}
     */
    private function seedAdminAndTicket(): array
    {
        $admin = User::create([
            'name' => 'Super User',
            'email' => 'super-user-close-reason@example.com',
            'phone' => '09130000001',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Owner',
            'email' => 'client-close-reason@example.com',
            'phone' => '09130000002',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09134444444',
            'email' => 'requester-close-reason@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Close reason test ticket',
            'description' => 'Testing close reason requirement.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$admin, $ticket];
    }
}
