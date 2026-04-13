<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardRolePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_dashboard_hides_needs_attention_quick_action(): void
    {
        $technician = User::create([
            'name' => 'Dashboard Technician',
            'email' => 'dashboard-technician@example.com',
            'phone' => '09117778888',
            'department' => 'iOne',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($technician)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('class="btn-danger">Currently Open Tickets</a>', false);
        $response->assertDontSee('class="btn-warning">Needs Attention</a>', false);
    }
}
