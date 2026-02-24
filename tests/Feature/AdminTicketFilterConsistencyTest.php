<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketFilterConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_filter_by_province_uses_ticket_location_fields(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $rizalClient = $this->createClient('Rizal Client', 'rizal-client@example.com');
        $cebuClient = $this->createClient('Cebu Client', 'cebu-client@example.com');

        $rizalTicket = Ticket::create([
            'name' => 'Rizal Requester',
            'contact_number' => '09110000001',
            'email' => 'rizal-requester@example.com',
            'province' => 'Rizal',
            'municipality' => 'Antipolo',
            'subject' => 'Rizal-only ticket',
            'description' => 'Rizal issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $rizalClient->id,
            'category_id' => $category->id,
        ]);

        $cebuTicket = Ticket::create([
            'name' => 'Cebu Requester',
            'contact_number' => '09110000002',
            'email' => 'cebu-requester@example.com',
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'subject' => 'Cebu-only ticket',
            'description' => 'Cebu issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $cebuClient->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'province' => 'Rizal',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $rizalTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $cebuTicket), false);
    }

    public function test_admin_ticket_filter_by_account_id_uses_exact_user_id(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $firstClient = $this->createClient('Same Name', 'same-name-1@example.com');
        $secondClient = $this->createClient('Same Name', 'same-name-2@example.com');

        $firstTicket = Ticket::create([
            'name' => 'Requester One',
            'contact_number' => '09110000003',
            'email' => 'requester-one@example.com',
            'province' => 'Laguna',
            'municipality' => 'Calamba',
            'subject' => 'Ticket for first duplicate name',
            'description' => 'First account ticket',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $firstClient->id,
            'category_id' => $category->id,
        ]);

        $secondTicket = Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09110000004',
            'email' => 'requester-two@example.com',
            'province' => 'Laguna',
            'municipality' => 'Calamba',
            'subject' => 'Ticket for second duplicate name',
            'description' => 'Second account ticket',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $secondClient->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'account_id' => $secondClient->id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $secondTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $firstTicket), false);
    }

    public function test_admin_ticket_filter_by_municipality_uses_ticket_location_fields(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();

        $client = $this->createClient('Municipality Client', 'municipality-client@example.com');

        $antipoloTicket = Ticket::create([
            'name' => 'Antipolo Requester',
            'contact_number' => '09110000005',
            'email' => 'antipolo-requester@example.com',
            'province' => 'Rizal',
            'municipality' => 'Antipolo',
            'subject' => 'Antipolo ticket',
            'description' => 'Antipolo issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $marikinaTicket = Ticket::create([
            'name' => 'Marikina Requester',
            'contact_number' => '09110000006',
            'email' => 'marikina-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Marikina',
            'subject' => 'Marikina ticket',
            'description' => 'Marikina issue',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'municipality' => 'Antipolo',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $antipoloTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $marikinaTicket), false);
    }

    private function createSupportUser(): User
    {
        return User::create([
            'name' => 'Support User',
            'email' => 'support-filters@example.com',
            'phone' => '09110000000',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createClient(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09112223333',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Filter Category',
            'description' => 'Filter checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }
}
