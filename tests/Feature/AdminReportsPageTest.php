<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_super_user_can_open_reports_page_and_view_statistics(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Reports Super', 'reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Reports Client', 'reports-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Requester One',
            'contact_number' => '09180000001',
            'email' => 'requester-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Reports open ticket',
            'description' => 'Open ticket for reporting',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09180000002',
            'email' => 'requester-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Reports resolved ticket',
            'description' => 'Resolved ticket for reporting',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Reports');
        $response->assertSee('Total Tickets');
        $response->assertSee('Resolution Rate');
        $response->assertSee('Category Breakdown');
        $response->assertSee('Top Technical Users');
        $response->assertSee('Monthly Performance (Last 12 Months)');
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return ($overview['label'] ?? null) === 'All Time';
        });
        $response->assertDontSee('% Change Vs Previous Period');
        $response->assertDontSee('SLA Compliance Rate');
        $response->assertDontSee('Backlog (Period End)');
        $response->assertDontSee('Average resolution time');
        $response->assertDontSee('SLA compliance (selection)');
    }

    public function test_reports_page_can_return_partial_html_for_filter_refresh(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Partial Reports Super', 'partial-reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Partial Reports Client', 'partial-reports-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Partial Requester',
            'contact_number' => '09180000003',
            'email' => 'partial-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Reports partial ticket',
            'description' => 'Ticket for partial report rendering',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)
            ->getJson(route('admin.reports.index', [
                'month' => 'all',
                'partial' => 1,
            ]), [
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('data-admin-reports-shell', (string) $response->json('html'));
        $this->assertStringContainsString('SLA Overview', (string) $response->json('html'));
    }

    public function test_reports_page_builds_sla_metrics_from_ticket_timelines(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('SLA Super', 'sla-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('SLA Client', 'sla-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $underOneHourTicket = Ticket::create([
            'name' => 'Under One Hour',
            'contact_number' => '09180001001',
            'email' => 'under-one-hour@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Under one hour ticket',
            'description' => 'Should stay inside the acknowledgment window.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($underOneHourTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 8, 40, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 8, 40, 0),
        ]);
        $underOneHourTicket->refresh();
        TicketUserState::markAcknowledged($underOneHourTicket, $superUser->id, Carbon::create(2026, 2, 25, 8, 50, 0));

        $severityOneTicket = Ticket::create([
            'name' => 'Severity One',
            'contact_number' => '09180001002',
            'email' => 'severity-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Severity one ticket',
            'description' => 'Should resolve within ninety minutes.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 25, 7, 30, 0),
            'satisfaction_rating' => 5,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityOneTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 6, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 7, 30, 0),
        ]);
        $severityOneTicket->refresh();
        TicketUserState::markAcknowledged($severityOneTicket, $superUser->id, Carbon::create(2026, 2, 25, 6, 20, 0));
        $severityOneReply = TicketReply::create([
            'ticket_id' => $severityOneTicket->id,
            'user_id' => $superUser->id,
            'message' => 'We are checking this now.',
            'is_internal' => false,
        ]);
        TicketReply::query()->whereKey($severityOneReply->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 6, 30, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 6, 30, 0),
        ]);

        $severityTwoTicket = Ticket::create([
            'name' => 'Severity Two',
            'contact_number' => '09180001003',
            'email' => 'severity-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Severity two ticket',
            'description' => 'Open for five hours.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityTwoTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 4, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 4, 0, 0),
        ]);

        $severityThreeTicket = Ticket::create([
            'name' => 'Severity Three',
            'contact_number' => '09180001004',
            'email' => 'severity-three@example.com',
            'province' => 'NCR',
            'municipality' => 'Quezon City',
            'subject' => 'Severity three ticket',
            'description' => 'Closed after twenty six hours.',
            'priority' => 'high',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
            'resolved_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
            'satisfaction_rating' => 3,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($severityThreeTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 6, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 8, 0, 0),
        ]);
        $severityThreeTicket->refresh();
        TicketUserState::markAcknowledged($severityThreeTicket, $superUser->id, Carbon::create(2026, 2, 24, 8, 0, 0));
        $severityThreeReply = TicketReply::create([
            'ticket_id' => $severityThreeTicket->id,
            'user_id' => $superUser->id,
            'message' => 'This is being worked on.',
            'is_internal' => false,
        ]);
        TicketReply::query()->whereKey($severityThreeReply->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 9, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertSee('SLA Overview');
        $response->assertSee('First Response Time');
        $response->assertSee('Resolution Time');
        $response->assertSee('SLA Breach Rate');
        $response->assertSee('Acknowledgment Rate');
        $response->assertSee('Customer Satisfaction SLA');
        $response->assertSee('Severity Bands');
        $response->assertViewHas('slaReport', function (array $slaReport) {
            $bands = collect($slaReport['severity_bands'] ?? [])->pluck('count', 'label');

            return ($slaReport['label'] ?? null) === 'Feb 2026'
                && (int) ($slaReport['total_tickets'] ?? 0) === 4
                && (float) ($slaReport['first_response']['average_minutes'] ?? 0) === 105.0
                && (float) ($slaReport['resolution']['average_minutes'] ?? 0) === 825.0
                && (int) ($slaReport['breach_rate']['breached_count'] ?? 0) === 2
                && (float) ($slaReport['breach_rate']['rate'] ?? 0) === 50.0
                && (int) ($slaReport['acknowledgment_rate']['acknowledged_count'] ?? 0) === 2
                && (float) ($slaReport['acknowledgment_rate']['rate'] ?? 0) === 50.0
                && (int) ($slaReport['customer_satisfaction']['rated_count'] ?? 0) === 2
                && (float) ($slaReport['customer_satisfaction']['average_rating'] ?? 0) === 4.0
                && (float) ($slaReport['customer_satisfaction']['rate'] ?? 0) === 50.0
                && (int) ($bands['Under 1 Hour'] ?? 0) === 1
                && (int) ($bands['Severity 1'] ?? 0) === 1
                && (int) ($bands['Severity 2'] ?? 0) === 1
                && (int) ($bands['Severity 3'] ?? 0) === 1;
        });
    }

    public function test_reports_page_can_use_all_time_reporting_period(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('All Time Super', 'all-time-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('All Time Client', 'all-time-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $januaryTicket = Ticket::create([
            'name' => 'January Requester',
            'contact_number' => '09180001005',
            'email' => 'january-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'January ticket',
            'description' => 'Should be included in all-time scope.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($januaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'February Requester',
            'contact_number' => '09180001006',
            'email' => 'february-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'February ticket',
            'description' => 'Resolved ticket in all-time scope.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 20, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 14, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => 'all',
        ]));

        $response->assertOk();
        $response->assertSee('All Time');
        $response->assertDontSee('Download Monthly PDF');
        $response->assertViewHas('selectedMonthKey', 'all');
        $response->assertViewHas('selectedMonthIsAllTime', true);
        $response->assertViewHas('periodOverview', function (array $overview) {
            return ($overview['label'] ?? null) === 'All Time'
                && (int) ($overview['total_tickets'] ?? 0) === 2
                && (int) ($overview['resolved'] ?? 0) === 1;
        });
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return ($row['month_label'] ?? null) === 'All Time'
                && (int) ($row['received'] ?? 0) === 2
                && (int) ($row['resolved'] ?? 0) === 1
                && (float) ($row['resolution_rate'] ?? 0) === 50.0;
        });
        $response->assertViewHas('slaReport', function (array $slaReport) {
            return ($slaReport['label'] ?? null) === 'All Time'
                && (int) ($slaReport['total_tickets'] ?? 0) === 2;
        });
        $response->assertViewHas('monthlyPerformanceFocusMonthKey', '2026-02');
    }

    public function test_reports_page_shows_daily_received_in_progress_and_resolved_statistics_for_selected_date(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Reports Super', 'daily-reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Reports Client', 'daily-reports-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $inProgressTicket = Ticket::create([
            'name' => 'In Progress Requester',
            'contact_number' => '09180000011',
            'email' => 'in-progress-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'In progress ticket for daily stats',
            'description' => 'Counts as received and in progress.',
            'priority' => 'high',
            'status' => 'in_progress',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($inProgressTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 24, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 10, 0, 0),
        ]);

        $resolvedTicket = Ticket::create([
            'name' => 'Resolved Requester',
            'contact_number' => '09180000012',
            'email' => 'resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Resolved ticket for daily stats',
            'description' => 'Counts as resolved on target day.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 24, 15, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($resolvedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 20, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 24, 15, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'daily_date' => '2026-02-24',
        ]));

        $response->assertOk();
        $response->assertSee('Daily Ticket Statistics');
        $response->assertViewHas('dailySelectedDateValue', '2026-02-24');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return $dailyStats['date'] === '2026-02-24'
                && (int) $dailyStats['received'] === 1
                && (int) $dailyStats['in_progress'] === 1
                && (int) $dailyStats['resolved'] === 0
                && (int) ($dailyStats['closed'] ?? 0) === 0;
        });
    }

    public function test_daily_ticket_statistics_defaults_to_current_month_and_today_when_no_daily_filter_is_set(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Default Super', 'daily-default-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Default Client', 'daily-default-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $todayTicket = Ticket::create([
            'name' => 'Today Requester',
            'contact_number' => '09180000013',
            'email' => 'today-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket for today default',
            'description' => 'Should be counted in default daily view.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($todayTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 25, 11, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 25, 11, 0, 0),
        ]);

        $pastMonthTicket = Ticket::create([
            'name' => 'Past Month Requester',
            'contact_number' => '09180000014',
            'email' => 'past-month-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Ticket for january',
            'description' => 'Should not be counted in default daily view.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($pastMonthTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 10, 9, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
        ]));

        $response->assertOk();
        $response->assertViewHas('dailyMonthKey', '2026-02');
        $response->assertViewHas('dailySelectedDateValue', '2026-02-25');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return $dailyStats['date'] === '2026-02-25'
                && (int) $dailyStats['received'] === 1;
        });
    }

    public function test_daily_ticket_statistics_can_show_month_totals_when_all_days_is_selected(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily All Super', 'daily-all-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily All Client', 'daily-all-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $inProgressTicket = Ticket::create([
            'name' => 'All Days In Progress',
            'contact_number' => '09180000015',
            'email' => 'all-days-in-progress@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'All days in progress',
            'description' => 'Counted in month received and in progress.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($inProgressTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 5, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 5, 10, 0, 0),
        ]);

        $openTicket = Ticket::create([
            'name' => 'All Days Open',
            'contact_number' => '09180000016',
            'email' => 'all-days-open@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'All days open',
            'description' => 'Counted in month received.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($openTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 18, 11, 30, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 11, 30, 0),
        ]);

        $resolvedTicket = Ticket::create([
            'name' => 'All Days Resolved',
            'contact_number' => '09180000017',
            'email' => 'all-days-resolved@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Resolved in selected month',
            'description' => 'Counts in month resolved total.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 14, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($resolvedTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 30, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 14, 14, 0, 0),
        ]);

        $closedTicket = Ticket::create([
            'name' => 'All Days Closed',
            'contact_number' => '09180000018',
            'email' => 'all-days-closed@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed in selected month',
            'description' => 'Counts in month resolved total via closed_at.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => null,
            'closed_at' => Carbon::create(2026, 2, 20, 16, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 20, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 20, 16, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'daily_month' => '2026-02',
            'daily_date' => 'all',
        ]));

        $response->assertOk();
        $response->assertViewHas('dailySelectedDateValue', 'all');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return ($dailyStats['mode'] ?? null) === 'month'
                && $dailyStats['date'] === null
                && str_contains((string) ($dailyStats['label'] ?? ''), 'All days in Feb 2026')
                && (int) $dailyStats['received'] === 2
                && (int) $dailyStats['in_progress'] === 1
                && (int) $dailyStats['resolved'] === 0
                && (int) ($dailyStats['closed'] ?? 0) === 0;
        });
    }

    public function test_total_ticket_breakdown_shows_closed_separately_but_keeps_it_in_resolved_count(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Breakdown Super', 'breakdown-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Breakdown Client', 'breakdown-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $closedTicket = Ticket::create([
            'name' => 'Breakdown Closed Requester',
            'contact_number' => '09180000999',
            'email' => 'breakdown-closed@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed ticket in month',
            'description' => 'Should appear in resolved/closed report bucket.',
            'priority' => 'medium',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 18, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($closedTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 18, 14, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertSee('Resolved');
        $response->assertSee('Closed');
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return (int) ($overview['resolved'] ?? 0) === 1
                && (int) ($overview['closed'] ?? 0) === 1;
        });
        $response->assertViewHas('selectedMonthStatuses', function (array $statuses) {
            return (int) ($statuses['resolved'] ?? 0) === 1
                && (int) ($statuses['closed'] ?? 0) === 1;
        });
    }

    public function test_reports_page_right_side_detail_filter_can_target_specific_day(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Detail Scope Super', 'detail-scope-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Detail Scope Client', 'detail-scope-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $dayTicketInProgress = Ticket::create([
            'name' => 'Day In Progress',
            'contact_number' => '09180000021',
            'email' => 'day-in-progress@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Detail in progress',
            'description' => 'In progress ticket for selected day.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($dayTicketInProgress->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 8, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 8, 0, 0),
        ]);

        $dayTicketResolved = Ticket::create([
            'name' => 'Day Resolved',
            'contact_number' => '09180000022',
            'email' => 'day-resolved@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Detail resolved',
            'description' => 'Resolved ticket for selected day.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 10, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($dayTicketResolved->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 14, 0, 0),
        ]);

        $otherDayTicket = Ticket::create([
            'name' => 'Other Day Open',
            'contact_number' => '09180000023',
            'email' => 'other-day-open@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Other day ticket',
            'description' => 'Should not be counted in selected day details.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($otherDayTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 11, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 11, 10, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'detail_month' => '2026-02',
            'detail_date' => '2026-02-10',
            'apply_details_filter' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('detailOverview', function (array $detailOverview) {
            return $detailOverview['mode'] === 'day'
                && $detailOverview['start'] === '2026-02-10'
                && (int) $detailOverview['total_created'] === 2
                && (int) $detailOverview['in_progress'] === 1
                && (int) $detailOverview['resolved'] === 1;
        });
    }

    public function test_detail_day_filter_is_ignored_when_day_is_outside_selected_detail_month(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Detail Clamp Super', 'detail-clamp-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Detail Clamp Client', 'detail-clamp-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $januaryTicket = Ticket::create([
            'name' => 'January Ticket',
            'contact_number' => '09180000024',
            'email' => 'january-ticket@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'January scope',
            'description' => 'Should appear when using month scope.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($januaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 1, 8, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 8, 10, 0, 0),
        ]);

        $februaryTicket = Ticket::create([
            'name' => 'February Ticket',
            'contact_number' => '09180000025',
            'email' => 'february-ticket@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'February scope',
            'description' => 'Outside january detail scope.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($februaryTicket->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 10, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'detail_month' => '2026-01',
            'detail_date' => '2026-02-10',
            'apply_details_filter' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('detailDateValue', null);
        $response->assertViewHas('detailOverview', function (array $detailOverview) {
            return $detailOverview['mode'] === 'month'
                && $detailOverview['start'] === '2026-01-01'
                && (int) $detailOverview['total_created'] === 1;
        });
    }

    public function test_detail_filter_clear_link_resets_to_default_reports_view(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Detail Clear Super', 'detail-clear-super@example.com', User::ROLE_SUPER_USER);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
            'daily_month' => '2026-01',
            'daily_date' => 'all',
            'detail_month' => '2026-01',
            'detail_date' => '',
            'apply_details_filter' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('href="'.e(route('admin.reports.index')).'"', false);
        $response->assertViewMissing('detailClearParams');
    }

    public function test_details_filter_applies_scope_to_breakdown_daily_kpis_and_top_technical_users(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Scoped Super', 'scoped-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Scoped Client', 'scoped-client@example.com', User::ROLE_CLIENT, 'iOne');
        $technicalInScope = $this->createUser('Scoped Tech', 'scoped-tech@example.com', User::ROLE_TECHNICAL);
        $technicalOutOfScope = $this->createUser('Other Tech', 'other-tech@example.com', User::ROLE_TECHNICAL);
        $category = $this->createCategory();

        $inScopeOpen = Ticket::create([
            'name' => 'Scoped Open',
            'contact_number' => '09180000031',
            'email' => 'scoped-open@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Scoped open ticket',
            'description' => 'Counted in scoped day metrics.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technicalInScope->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($inScopeOpen->id)->update([
            'created_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 12, 9, 0, 0),
        ]);

        $inScopeResolved = Ticket::create([
            'name' => 'Scoped Resolved',
            'contact_number' => '09180000032',
            'email' => 'scoped-resolved@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Scoped resolved ticket',
            'description' => 'Resolved in selected day.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 12, 16, 0, 0),
            'user_id' => $client->id,
            'assigned_to' => $technicalInScope->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($inScopeResolved->id)->update([
            'created_at' => Carbon::create(2026, 2, 12, 11, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 12, 16, 0, 0),
        ]);

        $outOfScope = Ticket::create([
            'name' => 'Out Scope',
            'contact_number' => '09180000033',
            'email' => 'out-scope@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Out of scope ticket',
            'description' => 'Must not count in scoped metrics.',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technicalOutOfScope->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($outOfScope->id)->update([
            'created_at' => Carbon::create(2026, 2, 13, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 13, 10, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'detail_month' => '2026-02',
            'detail_date' => '2026-02-12',
            'apply_details_filter' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('detailFilterApplied', true);
        $response->assertViewHas('dailySelectedDateValue', '2026-02-12');
        $response->assertViewHas('dailySelectedStats', function (array $dailyStats) {
            return $dailyStats['date'] === '2026-02-12'
                && (int) $dailyStats['received'] === 2
                && (int) $dailyStats['resolved'] === 1
                && (int) ($dailyStats['closed'] ?? 0) === 0;
        });
        $response->assertViewHas('ticketsBreakdownOverview', function (array $overview) {
            return (int) $overview['total_created'] === 2
                && (int) $overview['resolved'] === 1
                && (int) $overview['closed'] === 0;
        });
        $response->assertViewHas('stats', function (array $stats) {
            return (int) $stats['total_tickets'] === 2
                && (int) $stats['open_tickets'] === 1
                && (int) $stats['urgent_open_tickets'] === 0;
        });
        $response->assertViewHas('topTechnicians', function ($rows) {
            return collect($rows)->pluck('name')->contains('Scoped Tech')
                && ! collect($rows)->pluck('name')->contains('Other Tech');
        });
    }

    public function test_details_filter_scopes_monthly_performance_chart_data(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Monthly Scope Super', 'monthly-scope-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Monthly Scope Client', 'monthly-scope-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $inScopeJanuary = Ticket::create([
            'name' => 'In Scope January',
            'contact_number' => '09180000034',
            'email' => 'in-scope-january@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'January ticket',
            'description' => 'Should count in monthly performance after details filter.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 1, 15, 14, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($inScopeJanuary->id)->update([
            'created_at' => Carbon::create(2026, 1, 15, 10, 0, 0),
            'updated_at' => Carbon::create(2026, 1, 15, 14, 0, 0),
        ]);

        $outOfScopeFebruary = Ticket::create([
            'name' => 'Out of Scope February',
            'contact_number' => '09180000035',
            'email' => 'out-of-scope-february@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'February ticket',
            'description' => 'Must not count when detail month is January.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => Carbon::create(2026, 2, 10, 13, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($outOfScopeFebruary->id)->update([
            'created_at' => Carbon::create(2026, 2, 10, 9, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 10, 13, 0, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
            'detail_month' => '2026-01',
            'detail_date' => '',
            'apply_details_filter' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('monthlyPerformanceFocusMonthKey', '2026-01');
        $response->assertViewHas('monthlyPerformanceGraphPoints', function ($points) {
            $rows = collect($points);
            $january = $rows->firstWhere('key', '2026-01');
            $february = $rows->firstWhere('key', '2026-02');

            return (int) ($january['received'] ?? 0) === 1
                && (int) ($january['resolved'] ?? 0) === 1
                && (int) ($february['received'] ?? 0) === 0
                && (int) ($february['resolved'] ?? 0) === 0;
        });
    }

    public function test_client_cannot_open_reports_page(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = $this->createUser('Restricted Client', 'restricted-client@example.com', User::ROLE_CLIENT, 'iOne');

        $response = $this->actingAs($client)->get(route('admin.reports.index'));

        $response->assertForbidden();
    }

    public function test_technical_user_cannot_open_reports_page(): void
    {
        config(['legal.require_acceptance' => false]);

        $technical = $this->createUser('Restricted Technical', 'restricted-technical@example.com', User::ROLE_TECHNICAL);

        $response = $this->actingAs($technical)->get(route('admin.reports.index'));

        $response->assertForbidden();
    }

    public function test_super_user_can_download_monthly_pdf_report(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('PDF Super', 'pdf-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('PDF Client', 'pdf-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'PDF Requester',
            'contact_number' => '09180000100',
            'email' => 'pdf-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Monthly PDF ticket',
            'description' => 'Generates data for PDF report',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.monthly.pdf', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('ticket-monthly-report-', (string) $response->headers->get('content-disposition'));
    }

    public function test_reports_top_technical_users_hides_shadow_account(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Reports Viewer', 'reports-viewer@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Reports Client', 'reports-shadow-client@example.com', User::ROLE_CLIENT, 'iOne');
        $shadow = $this->createUser('Shadow Hidden', 'shadow-hidden@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Visible Technical', 'visible-technical@example.com', User::ROLE_TECHNICAL);
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Shadow Assigned',
            'contact_number' => '09180000111',
            'email' => 'shadow-assigned@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Shadow assignee ticket',
            'description' => 'Should not expose shadow in reports.',
            'priority' => 'high',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'assigned_to' => $shadow->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Technical Assigned',
            'contact_number' => '09180000112',
            'email' => 'technical-assigned@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Visible technical ticket',
            'description' => 'Should remain visible in reports.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now(),
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertDontSee('Shadow Hidden');
        $response->assertSee('Visible Technical');
    }

    public function test_monthly_completed_and_open_counts_handle_closed_tickets_correctly(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Metrics Super', 'metrics-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Metrics Client', 'metrics-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09180000200',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Closed without resolve timestamp',
            'description' => 'Closed ticket should count as completed and not open.',
            'priority' => 'high',
            'status' => 'closed',
            'resolved_at' => null,
            'closed_at' => now()->subDay(),
            'user_id' => $client->id,
            'category_id' => $category->id,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => now()->format('Y-m'),
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) $row['received'] === 1
                && (int) $row['resolved'] === 1
                && (int) $row['open_end_of_month'] === 0
                && (float) $row['resolution_rate'] === 100.0;
        });
    }

    public function test_monthly_resolution_rate_counts_tickets_completed_after_the_creation_month(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Cohort Super', 'cohort-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Cohort Client', 'cohort-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'name' => 'Cross Month Requester',
            'contact_number' => '09180000201',
            'email' => 'cross-month-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Created in January, resolved in February',
            'description' => 'Should still count toward January completion rate.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
            'closed_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::query()->whereKey($ticket->id)->update([
            'created_at' => Carbon::create(2026, 1, 31, 16, 0, 0),
            'updated_at' => Carbon::create(2026, 2, 2, 14, 30, 0),
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-01',
        ]));

        $response->assertOk();
        $response->assertViewHas('selectedMonthRow', function (array $row) {
            return (int) $row['received'] === 1
                && (int) $row['resolved'] === 1
                && (int) ($row['completed_in_period'] ?? 0) === 0
                && (int) $row['open_end_of_month'] === 1
                && (float) $row['resolution_rate'] === 100.0;
        });
    }

    public function test_report_mix_drilldowns_default_to_all_time_scope_and_use_all_tickets_tab(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Mix Drilldown Super', 'mix-drilldown-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Mix Drilldown Client', 'mix-drilldown-client@example.com', User::ROLE_CLIENT, 'iOne');
        $category = $this->createCategory();

        Ticket::create([
            'name' => 'Mix Drilldown Requester',
            'contact_number' => '09180000202',
            'email' => 'mix-drilldown-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Priority and category drilldown',
            'description' => 'Ensures report links keep full created-period scope.',
            'priority' => 'high',
            'status' => 'closed',
            'closed_at' => Carbon::create(2026, 2, 8, 12, 0, 0),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.reports.index', [
            'month' => '2026-02',
        ]));

        $response->assertOk();
        $response->assertViewHas('ticketHistoryScope', []);
        $response->assertSee('tab=all&amp;category_bucket=', false);
        $response->assertSee('tab=all&amp;priority=high', false);
        $response->assertDontSee('created_from=', false);
        $response->assertDontSee('created_to=', false);
    }

    private function createUser(string $name, string $email, string $role, string $department = 'iOne'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09181230000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Reports Category',
            'description' => 'Reports test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }
}

