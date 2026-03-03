<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketFilterConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_filter_on_tickets_tab_can_select_resolved_and_closed_statuses(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Status Filter Client', 'status-filter-client@example.com');

        $resolvedTicket = Ticket::create([
            'name' => 'Resolved Requester',
            'contact_number' => '09110000101',
            'email' => 'resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved ticket',
            'description' => 'Resolved issue',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $closedTicket = Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09110000102',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed ticket',
            'description' => 'Closed issue',
            'priority' => 'high',
            'status' => 'closed',
            'resolved_at' => now()->subMinute(),
            'closed_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $openTicket = Ticket::create([
            'name' => 'Open Requester',
            'contact_number' => '09110000103',
            'email' => 'open-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Open ticket',
            'description' => 'Open issue',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $resolvedResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'status' => 'resolved',
        ]));

        $resolvedResponse->assertOk();
        $resolvedResponse->assertSee(route('admin.tickets.show', $resolvedTicket), false);
        $resolvedResponse->assertDontSee(route('admin.tickets.show', $closedTicket), false);
        $resolvedResponse->assertDontSee(route('admin.tickets.show', $openTicket), false);

        $closedResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'status' => 'closed',
        ]));

        $closedResponse->assertOk();
        $closedResponse->assertSee(route('admin.tickets.show', $closedTicket), false);
        $closedResponse->assertDontSee(route('admin.tickets.show', $resolvedTicket), false);
        $closedResponse->assertDontSee(route('admin.tickets.show', $openTicket), false);
    }

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

    public function test_admin_ticket_filter_by_created_date_range_matches_report_drill_down_scope(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Date Range Client', 'date-range-client@example.com');

        $matchingTicket = Ticket::create([
            'name' => 'Matching Requester',
            'contact_number' => '09110000111',
            'email' => 'matching-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Matching date scope',
            'description' => 'Ticket in requested report scope.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($matchingTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
        ]);

        $outsideTicket = Ticket::create([
            'name' => 'Outside Requester',
            'contact_number' => '09110000112',
            'email' => 'outside-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Outside date scope',
            'description' => 'Ticket out of requested report scope.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($outsideTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
            'status' => 'resolved',
            'created_from' => '2026-02-12',
            'created_to' => '2026-02-12',
            'report_scope' => 'Feb 12, 2026',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $matchingTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $outsideTicket), false);
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
