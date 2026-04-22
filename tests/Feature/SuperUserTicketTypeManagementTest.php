<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperUserTicketTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_client_created_tickets_default_to_external(): void
    {
        $client = $this->createUser('Client Default', 'client-default@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Client Default',
            'contact_number' => '09170000001',
            'email' => 'client-default@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Client default external type',
            'description' => 'Created by the client account.',
            'category_id' => $category->id,
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertSame(Ticket::TYPE_EXTERNAL, $ticket->ticket_type);
    }

    public function test_super_user_can_open_admin_ticket_create_screen_and_create_internal_ticket(): void
    {
        $superUser = $this->createUser('Super Creator', 'super-creator@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Ticket Client', 'ticket-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $createResponse = $this->actingAs($superUser)->get(route('admin.tickets.create'));
        $createResponse->assertOk();
        $createResponse->assertSeeText('Create Ticket for Client');
        $createResponse->assertSee('name="ticket_type"', false);

        $storeResponse = $this->actingAs($superUser)->post(route('admin.tickets.store'), [
            'user_id' => $client->id,
            'name' => 'Ticket Client',
            'contact_number' => '09170000002',
            'email' => 'ticket-client@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Superuser logged direct contact',
            'description' => 'Client contacted the technician directly.',
            'category_id' => $category->id,
            'ticket_type' => Ticket::TYPE_INTERNAL,
        ]);

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $storeResponse->assertRedirect(route('admin.tickets.show', $ticket));
        $this->assertSame(Ticket::TYPE_INTERNAL, $ticket->ticket_type);
        $this->assertSame($client->id, (int) $ticket->user_id);
    }

    public function test_super_user_can_create_ticket_without_contact_number_and_email(): void
    {
        $superUser = $this->createUser('Super Optional', 'super-optional@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Optional Client', 'optional-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $response = $this->actingAs($superUser)->post(route('admin.tickets.store'), [
            'user_id' => $client->id,
            'name' => 'Optional Client',
            'contact_number' => '',
            'email' => '',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Optional contact details',
            'description' => 'Ticket created without contact and email fields.',
            'category_id' => $category->id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ]);

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $this->assertNull($ticket->contact_number);
        $this->assertNull($ticket->email);
    }

    public function test_technical_user_can_open_admin_ticket_create_screen_and_create_ticket(): void
    {
        $technical = $this->createUser('Technical Creator', 'technical-creator@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Technical Client', 'technical-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $createResponse = $this->actingAs($technical)->get(route('admin.tickets.create'));
        $createResponse->assertOk();
        $createResponse->assertSeeText('Create Ticket for Client');
        $createResponse->assertSee('name="ticket_type"', false);

        $storeResponse = $this->actingAs($technical)->post(route('admin.tickets.store'), [
            'user_id' => $client->id,
            'name' => 'Technical Client',
            'contact_number' => '09170000012',
            'email' => 'technical-client@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Technical logged direct contact',
            'description' => 'Client contacted technical support directly.',
            'category_id' => $category->id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ]);

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $storeResponse->assertRedirect(route('admin.tickets.show', $ticket));
        $this->assertSame(Ticket::TYPE_EXTERNAL, $ticket->ticket_type);
        $this->assertSame($client->id, (int) $ticket->user_id);
    }

    public function test_admin_cannot_access_super_user_ticket_create_flow(): void
    {
        $admin = $this->createUser('Admin Viewer', 'admin-viewer@example.com', User::ROLE_ADMIN);
        $client = $this->createUser('Blocked Client', 'blocked-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $this->actingAs($admin)
            ->get(route('admin.tickets.create'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.tickets.store'), [
                'user_id' => $client->id,
                'name' => 'Blocked Client',
                'contact_number' => '09170000003',
                'email' => 'blocked-client@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Blocked create',
                'description' => 'This should not be allowed.',
                'category_id' => $category->id,
                'ticket_type' => Ticket::TYPE_INTERNAL,
            ])
            ->assertForbidden();
    }

    public function test_super_user_can_update_ticket_type_from_ticket_actions(): void
    {
        $superUser = $this->createUser('Super Updater', 'super-updater@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Type Client', 'type-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ]);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.type', $ticket), [
            'ticket_type' => Ticket::TYPE_INTERNAL,
        ]);

        $response->assertRedirect();
        $this->assertSame(Ticket::TYPE_INTERNAL, $ticket->fresh()->ticket_type);
    }

    public function test_admin_quick_update_cannot_change_ticket_type(): void
    {
        $admin = $this->createUser('Admin Editor', 'admin-editor@example.com', User::ROLE_ADMIN);
        $client = $this->createUser('Quick Update Client', 'quick-update-client@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ]);

        $response = $this->actingAs($admin)->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.quick-update', $ticket), [
                'status' => 'open',
                'priority' => '',
                'ticket_type' => Ticket::TYPE_INTERNAL,
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHasErrors('ticket_type');
        $this->assertSame(Ticket::TYPE_EXTERNAL, $ticket->fresh()->ticket_type);
    }

    private function createUser(string $name, string $email, string $role, string $department = 'iOne'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09179999999',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Ticket Type Category',
            'description' => 'Ticket type testing category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }

    private function createTicket(User $client, Category $category, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'name' => 'Ticket Requester',
            'contact_number' => '09185551234',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket type subject',
            'description' => 'Ticket type body',
            'priority' => null,
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
        ], $overrides));
    }
}
