<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketBulkActionSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_can_bulk_assign_tickets(): void
    {
        [$superUser, , $technical, $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'assign',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'assigned_to' => $technical->id,
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketOne->id,
            'assigned_to' => $technical->id,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketTwo->id,
            'assigned_to' => $technical->id,
        ]);
    }

    public function test_super_user_can_bulk_close_tickets_with_reason(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'status',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'status' => 'closed',
                'close_reason' => 'Bulk closure validation reason.',
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('closed', $ticketOne->status);
        $this->assertSame('closed', $ticketTwo->status);
        $this->assertNotNull($ticketOne->closed_at);
        $this->assertNotNull($ticketTwo->closed_at);
        $this->assertNotNull($ticketOne->resolved_at);
        $this->assertNotNull($ticketTwo->resolved_at);
    }

    public function test_super_user_can_bulk_update_ticket_priority(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'priority',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'priority' => 'urgent',
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketOne->id,
            'priority' => 'urgent',
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketTwo->id,
            'priority' => 'urgent',
        ]);
    }

    public function test_super_user_can_bulk_merge_tickets(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $ticketOne->created_at = Carbon::now()->subMinutes(20);
        $ticketOne->save();
        $ticketTwo->created_at = Carbon::now()->subMinutes(10);
        $ticketTwo->save();

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'merge',
                'selected_ids' => [$ticketTwo->id, $ticketOne->id],
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticketOne));

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('open', $ticketOne->status);
        $this->assertSame('closed', $ticketTwo->status);
        $this->assertNotNull($ticketTwo->closed_at);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticketOne->id,
            'message' => "Merged ticket {$ticketTwo->ticket_number}: {$ticketTwo->subject}",
            'is_internal' => true,
        ]);
    }

    public function test_super_admin_can_bulk_delete_tickets(): void
    {
        [, $superAdmin, , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superAdmin)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'delete',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['id' => $ticketOne->id]);
        $this->assertDatabaseMissing('tickets', ['id' => $ticketTwo->id]);
    }

    private function seedBulkActionContext(): array
    {
        $superUser = User::create([
            'name' => 'Bulk Super User',
            'email' => 'bulk-super-user@example.com',
            'phone' => '09130000001',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superAdmin = User::create([
            'name' => 'Bulk Super Admin',
            'email' => 'bulk-super-admin@example.com',
            'phone' => '09130000002',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Bulk Technical',
            'email' => 'bulk-technical@example.com',
            'phone' => '09130000003',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Bulk Client',
            'email' => 'bulk-client@example.com',
            'phone' => '09130000004',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Bulk Category',
            'description' => 'Bulk action test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticketOne = Ticket::create([
            'name' => 'Bulk Requester One',
            'contact_number' => '09130000011',
            'email' => 'bulk-requester-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Bulk ticket one',
            'description' => 'Bulk ticket one description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $ticketTwo = Ticket::create([
            'name' => 'Bulk Requester Two',
            'contact_number' => '09130000012',
            'email' => 'bulk-requester-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Bulk ticket two',
            'description' => 'Bulk ticket two description',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$superUser, $superAdmin, $technical, $ticketOne, $ticketTwo];
    }
}
