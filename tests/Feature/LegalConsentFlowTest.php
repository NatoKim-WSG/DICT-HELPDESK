<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegalConsentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_pages_are_accessible(): void
    {
        $this->get(route('legal.terms'))->assertOk();
        $this->get(route('legal.privacy'))->assertOk();
        $this->get(route('legal.ticket-consent'))->assertOk();
    }

    public function test_user_without_current_consent_is_redirected_to_acceptance_gate(): void
    {
        config([
            'legal.require_acceptance' => true,
            'legal.terms_version' => '2026-02-25',
            'legal.privacy_version' => '2026-02-25',
            'legal.platform_consent_version' => '2026-02-25',
        ]);

        $user = $this->createUser(User::ROLE_SUPER_USER);

        $response = $this->actingAs($user)->get(route('account.settings'));

        $response->assertRedirect(route('legal.acceptance.show'));
    }

    public function test_acceptance_is_recorded_and_user_can_continue_to_intended_page(): void
    {
        config([
            'legal.require_acceptance' => true,
            'legal.terms_version' => 'v-terms-test',
            'legal.privacy_version' => 'v-privacy-test',
            'legal.platform_consent_version' => 'v-platform-test',
        ]);

        $user = $this->createUser(User::ROLE_SUPER_USER);

        $firstResponse = $this->actingAs($user)->get(route('account.settings'));
        $firstResponse->assertRedirect(route('legal.acceptance.show'));

        $acceptResponse = $this
            ->actingAs($user)
            ->post(route('legal.acceptance.store'), [
                'accept_terms' => '1',
                'accept_privacy' => '1',
                'accept_platform_consent' => '1',
            ]);

        $acceptResponse->assertRedirect(route('account.settings'));

        $this->assertDatabaseHas('user_legal_consents', [
            'user_id' => $user->id,
            'terms_version' => 'v-terms-test',
            'privacy_version' => 'v-privacy-test',
            'platform_consent_version' => 'v-platform-test',
        ]);

        $this->actingAs($user)->get(route('account.settings'))->assertOk();
    }

    public function test_ticket_submission_requires_ticket_consent_checkbox(): void
    {
        $client = $this->createUser(User::ROLE_CLIENT, 'client-consent@example.com', 'DICT');
        $category = Category::create([
            'name' => 'Consent Validation',
            'description' => 'Consent validation category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Client Consent',
            'contact_number' => '09181230000',
            'email' => 'client-consent@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Consent required test',
            'description' => 'Testing required ticket consent.',
            'category_id' => $category->id,
            'priority' => 'high',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertSessionHasErrors('ticket_consent');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_submission_stores_ticket_consent_evidence(): void
    {
        config([
            'legal.ticket_consent_version' => 'ticket-v-test',
        ]);

        $client = $this->createUser(User::ROLE_CLIENT, 'client-consent2@example.com', 'DICT');
        $category = Category::create([
            'name' => 'Consent Capture',
            'description' => 'Consent capture category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Client Consent',
            'contact_number' => '09181230001',
            'email' => 'client-consent2@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Consent capture test',
            'description' => 'Testing consent metadata capture.',
            'category_id' => $category->id,
            'priority' => 'high',
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Consent capture test')->firstOrFail();
        $this->assertNotNull($ticket->consent_accepted_at);
        $this->assertSame('ticket-v-test', $ticket->consent_version);
        $this->assertNotNull($ticket->consent_user_agent);
    }

    private function createUser(string $role, string $email = 'consent-user@example.com', string $department = 'iOne'): User
    {
        return User::create([
            'name' => 'Consent User',
            'email' => $email,
            'phone' => '09170000000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
