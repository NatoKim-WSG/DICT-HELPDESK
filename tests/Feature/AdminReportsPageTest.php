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
        $response->assertSee('Monthly Statistics Detail');
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
