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
        $response->assertSee('Monthly Report (Last 12 Months)');
        $response->assertSee('Monthly Statistics Detail');
    }

    public function test_client_cannot_open_reports_page(): void
    {
        $client = $this->createUser('Restricted Client', 'restricted-client@example.com', User::ROLE_CLIENT, 'DICT');

        $response = $this->actingAs($client)->get(route('admin.reports.index'));

        $response->assertForbidden();
    }

    public function test_technical_user_cannot_open_reports_page(): void
    {
        $technical = $this->createUser('Restricted Technical', 'restricted-technical@example.com', User::ROLE_TECHNICAL);

        $response = $this->actingAs($technical)->get(route('admin.reports.index'));

        $response->assertForbidden();
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
