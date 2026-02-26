<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShadowAccountVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_assign_ticket_to_shadow_account(): void
    {
        config(['legal.require_acceptance' => false]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'assign-admin@example.com',
            'phone' => '09180001100',
            'department' => 'iOne',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'assign-shadow@example.com',
            'phone' => '09180001101',
            'department' => 'iOne',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client User',
            'email' => 'assign-client@example.com',
            'phone' => '09180001102',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Visibility Category',
            'description' => 'Visibility test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09180001103',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assignment target validation',
            'description' => 'Cannot assign this to shadow.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $shadow->id,
        ]);

        $response->assertSessionHasErrors('assigned_to');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
        ]);
    }
}

