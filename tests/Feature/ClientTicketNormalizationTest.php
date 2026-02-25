<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientTicketNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_ticket_creation_normalizes_leading_uppercase_fields(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = User::create([
            'name' => 'Normalization Client',
            'email' => 'normalization-client@example.com',
            'phone' => '09189990001',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Normalization',
            'description' => 'Normalization category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Normalization Client',
            'contact_number' => '09189990001',
            'email' => 'normalization-client@example.com',
            'province' => 'metro manila',
            'municipality' => 'pasig city',
            'subject' => 'network outage in office',
            'description' => 'Need immediate help.',
            'category_id' => $category->id,
            'priority' => 'high',
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        /** @var Ticket $ticket */
        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertSame('Metro manila', $ticket->province);
        $this->assertSame('Pasig city', $ticket->municipality);
        $this->assertSame('Network outage in office', $ticket->subject);
    }
}
