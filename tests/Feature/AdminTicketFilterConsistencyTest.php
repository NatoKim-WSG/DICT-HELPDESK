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

    public function test_admin_tickets_tab_hides_resolved_and_closed_statuses_and_results(): void
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

        $ticketsResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $ticketsResponse->assertOk();
        $ticketsResponse->assertSee(route('admin.tickets.show', $openTicket), false);
        $ticketsResponse->assertDontSee(route('admin.tickets.show', $resolvedTicket), false);
        $ticketsResponse->assertDontSee(route('admin.tickets.show', $closedTicket), false);

        $invalidStatusResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'status' => 'resolved',
        ]));

        $invalidStatusResponse->assertOk();
        $invalidStatusResponse->assertSee(route('admin.tickets.show', $openTicket), false);
        $invalidStatusResponse->assertDontSee(route('admin.tickets.show', $resolvedTicket), false);
        $invalidStatusResponse->assertDontSee(route('admin.tickets.show', $closedTicket), false);

        $historyResponse = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $historyResponse->assertOk();
        $historyResponse->assertSee(route('admin.tickets.show', $resolvedTicket), false);
        $historyResponse->assertSee(route('admin.tickets.show', $closedTicket), false);
        $historyResponse->assertDontSee(route('admin.tickets.show', $openTicket), false);
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

    public function test_admin_ticket_filter_by_month_scopes_to_created_month(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Month Filter Client', 'month-filter-client@example.com');

        $marchTicket = Ticket::create([
            'name' => 'March Requester',
            'contact_number' => '09110000121',
            'email' => 'march-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'March-scoped ticket',
            'description' => 'Created in March.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($marchTicket->id)->update([
            'created_at' => Carbon::create(2026, 3, 5, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 3, 5, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'February Requester',
            'contact_number' => '09110000122',
            'email' => 'february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'February-scoped ticket',
            'description' => 'Created in February.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'month' => '2026-03',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $marchTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $februaryTicket), false);
    }

    public function test_admin_ticket_filter_by_assigned_user_matches_ticket_assignment(): void
    {
        $supportUser = $this->createSupportUser();
        $assignedUser = $this->createAssignedSupportUser('Assigned Support One', 'assigned-support-one@example.com');
        $otherAssignedUser = $this->createAssignedSupportUser('Assigned Support Two', 'assigned-support-two@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Assigned Filter Client', 'assigned-filter-client@example.com');

        $matchingTicket = Ticket::create([
            'name' => 'Assigned Requester',
            'contact_number' => '09110000131',
            'email' => 'assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned to matching user',
            'description' => 'Assigned to matching support user.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $assignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $otherTicket = Ticket::create([
            'name' => 'Other Assigned Requester',
            'contact_number' => '09110000132',
            'email' => 'other-assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Assigned to a different user',
            'description' => 'Assigned to other support user.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $otherAssignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'assigned_to' => $assignedUser->id,
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $matchingTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $otherTicket), false);
    }

    public function test_unassigned_filter_does_not_treat_primary_assignee_without_pivot_row_as_unassigned(): void
    {
        $supportUser = $this->createSupportUser();
        $assignedUser = $this->createAssignedSupportUser('Stale Primary Support', 'stale-primary-support@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Stale Assignment Client', 'stale-assignment-client@example.com');

        $staleAssignedTicket = Ticket::create([
            'name' => 'Stale Assigned Requester',
            'contact_number' => '09110000133',
            'email' => 'stale-assigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Primary assignee only ticket',
            'description' => 'Should still count as assigned.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $assignedUser->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        $staleAssignedTicket->assignedUsers()->sync([]);

        $trulyUnassignedTicket = Ticket::create([
            'name' => 'Truly Unassigned Requester',
            'contact_number' => '09110000134',
            'email' => 'truly-unassigned-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Actually unassigned ticket',
            'description' => 'Should be the only result in the unassigned filter.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'assigned_to' => '0',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $trulyUnassignedTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $staleAssignedTicket), false);
    }

    public function test_admin_ticket_partial_filter_response_returns_rendered_results_html(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Partial Filter Client', 'partial-filter-client@example.com');

        $marchTicket = Ticket::create([
            'name' => 'Partial March Requester',
            'contact_number' => '09110000141',
            'email' => 'partial-march-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Partial March ticket',
            'description' => 'Included in partial response.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($marchTicket->id)->update([
            'created_at' => Carbon::create(2026, 3, 7, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 3, 7, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'Partial February Requester',
            'contact_number' => '09110000142',
            'email' => 'partial-february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Partial February ticket',
            'description' => 'Excluded from partial response.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 7, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 7, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'month' => '2026-03',
            'partial' => '1',
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'token', 'page_token']);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertStringContainsString(route('admin.tickets.show', $marchTicket, false), $payload['html']);
        $this->assertStringNotContainsString(route('admin.tickets.show', $februaryTicket, false), $payload['html']);
        $this->assertStringContainsString('data-admin-tickets-results', $payload['html']);
    }

    public function test_history_filter_options_only_show_applicable_ticket_entries(): void
    {
        $supportUser = $this->createSupportUser();
        $historyCategory = Category::create([
            'name' => 'History Only Category',
            'description' => 'Closed history category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
        $openCategory = Category::create([
            'name' => 'Open Only Category',
            'description' => 'Open ticket category',
            'color' => '#f97316',
            'is_active' => true,
        ]);
        $historyClient = $this->createClient('History Client', 'history-client@example.com');
        $openClient = $this->createClient('Open Client', 'open-client@example.com');

        $closedTicket = Ticket::create([
            'name' => 'History Requester',
            'contact_number' => '09110000151',
            'email' => 'history-requester@example.com',
            'province' => 'History Province',
            'municipality' => 'History Town',
            'subject' => 'Closed history ticket',
            'description' => 'Visible in history.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
            'user_id' => $historyClient->id,
            'category_id' => $historyCategory->id,
        ]);
        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
        ]);

        $openTicket = Ticket::create([
            'name' => 'Open Requester',
            'contact_number' => '09110000152',
            'email' => 'open-requester@example.com',
            'province' => 'Open Province',
            'municipality' => 'Open Town',
            'subject' => 'Open active ticket',
            'description' => 'Not visible in history.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $openClient->id,
            'category_id' => $openCategory->id,
        ]);
        Ticket::query()->whereKey($openTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 12, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 12, 9, 0, 0),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'history',
        ]));

        $response->assertOk();
        $response->assertSee('<option value="'.$historyCategory->id.'"', false);
        $response->assertDontSee('<option value="'.$openCategory->id.'"', false);
        $response->assertSee('<option value="'.$historyClient->id.'"', false);
        $response->assertDontSee('<option value="'.$openClient->id.'"', false);
        $response->assertSee('<option value="History Province"', false);
        $response->assertDontSee('<option value="Open Province"', false);
        $response->assertSee('<option value="History Town"', false);
        $response->assertDontSee('<option value="Open Town"', false);
        $response->assertSee('<option value="2026-02"', false);
        $response->assertDontSee('<option value="2026-01"', false);
    }

    public function test_admin_ticket_heartbeat_keeps_same_page_token_for_off_page_changes(): void
    {
        $supportUser = $this->createSupportUser();
        $category = $this->createCategory();
        $client = $this->createClient('Heartbeat Client', 'heartbeat-client@example.com');
        $oldestTicket = null;

        for ($index = 1; $index <= 16; $index++) {
            $ticket = Ticket::create([
                'name' => "Heartbeat Requester {$index}",
                'contact_number' => '09110000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'email' => "heartbeat-requester-{$index}@example.com",
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => "Heartbeat ticket {$index}",
                'description' => 'Heartbeat ticket description.',
                'priority' => 'medium',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
            Ticket::query()->whereKey($ticket->id)->update([
                'created_at' => Carbon::create(2026, 3, 1, 8, 0, 0)->addMinutes($index),
                'updated_at' => Carbon::create(2026, 3, 1, 8, 0, 0)->addMinutes($index),
            ]);

            if ($index === 1) {
                $oldestTicket = $ticket;
            }
        }

        $initialResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'partial' => '1',
        ]));
        $initialResponse->assertOk();

        $initialToken = (string) $initialResponse->json('token');
        $initialPageToken = (string) $initialResponse->json('page_token');

        $this->assertNotSame('', $initialToken);
        $this->assertNotSame('', $initialPageToken);

        Ticket::query()->whereKey($oldestTicket?->id)->update([
            'subject' => 'Updated off-page heartbeat ticket',
            'updated_at' => Carbon::create(2026, 3, 2, 8, 0, 0),
        ]);

        $heartbeatResponse = $this->actingAs($supportUser)->getJson(route('admin.tickets.index', [
            'tab' => 'tickets',
            'heartbeat' => '1',
        ]));
        $heartbeatResponse->assertOk();

        $heartbeatResponse->assertJsonPath('page_token', $initialPageToken);
        $this->assertNotSame($initialToken, (string) $heartbeatResponse->json('token'));
    }

    public function test_technician_does_not_see_needs_attention_tab_even_when_requested(): void
    {
        $technician = $this->createAssignedSupportUser('Scoped Technician', 'scoped-technician@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Technician Client', 'technician-client@example.com');

        $ticket = Ticket::create([
            'name' => 'Technician Requester',
            'contact_number' => '09110000161',
            'email' => 'technician-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned open ticket',
            'description' => 'Visible to the assigned technician.',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $technician->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($technician)->get(route('admin.tickets.index', [
            'tab' => 'attention',
        ]));

        $response->assertOk();
        $response->assertDontSee('Needs Attention');
        $response->assertSee(route('admin.tickets.show', $ticket), false);
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
            'department' => 'iOne',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createAssignedSupportUser(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09113334444',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
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
