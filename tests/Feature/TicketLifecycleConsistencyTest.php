<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketLifecycleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reopening_ticket_clears_resolution_and_closure_timestamps(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'open',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
    }

    public function test_client_reply_reopens_closed_or_resolved_ticket_and_clears_timestamps(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => null,
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Issue returned after initial fix.',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
    }

    private function seedUsersAndTicket(): array
    {
        $superUser = User::create([
            'name' => 'Super User Lifecycle',
            'email' => 'super-user-lifecycle@example.com',
            'phone' => '09119990001',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Lifecycle',
            'email' => 'client-lifecycle@example.com',
            'phone' => '09119990002',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Lifecycle',
            'description' => 'Lifecycle checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Lifecycle Requester',
            'contact_number' => '09119990003',
            'email' => 'lifecycle-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Lifecycle consistency',
            'description' => 'Ticket lifecycle behavior.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$superUser, $client, $ticket];
    }
}
