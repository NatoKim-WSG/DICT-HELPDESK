<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
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
        $client = $this->createUser('Reports Client', 'reports-client@example.com', User::ROLE_CLIENT, 'DICT');
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
    }

    public function test_reports_page_shows_daily_received_in_progress_and_resolved_statistics_for_selected_date(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Reports Super', 'daily-reports-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Reports Client', 'daily-reports-client@example.com', User::ROLE_CLIENT, 'DICT');
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
                && (int) $dailyStats['resolved'] === 1;
        });
    }

    public function test_daily_ticket_statistics_defaults_to_current_month_and_today_when_no_daily_filter_is_set(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Daily Default Super', 'daily-default-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Daily Default Client', 'daily-default-client@example.com', User::ROLE_CLIENT, 'DICT');
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

    public function test_reports_page_right_side_detail_filter_can_target_specific_day(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Detail Scope Super', 'detail-scope-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Detail Scope Client', 'detail-scope-client@example.com', User::ROLE_CLIENT, 'DICT');
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
        $client = $this->createUser('Detail Clamp Client', 'detail-clamp-client@example.com', User::ROLE_CLIENT, 'DICT');
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

    public function test_details_filter_applies_scope_to_breakdown_daily_kpis_and_top_technical_users(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Scoped Super', 'scoped-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Scoped Client', 'scoped-client@example.com', User::ROLE_CLIENT, 'DICT');
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
                && (int) $dailyStats['resolved'] === 1;
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

    public function test_client_cannot_open_reports_page(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = $this->createUser('Restricted Client', 'restricted-client@example.com', User::ROLE_CLIENT, 'DICT');

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
        $client = $this->createUser('PDF Client', 'pdf-client@example.com', User::ROLE_CLIENT, 'DICT');
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
        $client = $this->createUser('Reports Client', 'reports-shadow-client@example.com', User::ROLE_CLIENT, 'DICT');
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

    public function test_monthly_completed_and_open_counts_include_closed_tickets_correctly(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Metrics Super', 'metrics-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Metrics Client', 'metrics-client@example.com', User::ROLE_CLIENT, 'DICT');
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
