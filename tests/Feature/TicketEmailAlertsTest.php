<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketEmailAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 8, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_super_user_receives_email_when_client_creates_ticket(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Alerts', 'super-alerts@example.com', User::ROLE_SUPER_USER);
        $admin = $this->createUser('Admin Alerts', 'admin-alerts@example.com', User::ROLE_ADMIN);
        $shadow = $this->createUser('Shadow Alerts', 'shadow-alerts@example.com', User::ROLE_SHADOW);
        $client = $this->createUser('Client Alerts', 'client-alerts@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Client Alerts',
            'contact_number' => '09181234567',
            'email' => 'client-alerts@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Email alert on new ticket',
            'description' => 'Need confirmation that super users are alerted.',
            'category_id' => $category->id,
            'priority' => 'high',
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Email alert on new ticket')->firstOrFail();
        $this->assertNotNull($ticket->super_users_notified_new_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($superUser, $ticket) {
            return $mail->hasTo($superUser->email)
                && str_starts_with($mail->subjectLine, 'New Ticket Received:')
                && $mail->ticket->is($ticket);
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_starts_with($mail->subjectLine, 'New Ticket Received:');
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($shadow) {
            return $mail->hasTo($shadow->email)
                && str_starts_with($mail->subjectLine, 'New Ticket Received:');
        });
    }

    public function test_technical_user_receives_email_when_ticket_is_assigned(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Assigner', 'super-assigner@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Tech Alerts', 'tech-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Assign', 'client-assign@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $technical->id,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($technical->id, (int) $ticket->assigned_to);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->technical_user_notified_assignment_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_closed_ticket_assignment_does_not_send_email(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Closed Assigner', 'super-closed-assigner@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Tech Closed Alerts', 'tech-closed-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Closed Assign', 'client-closed-assign@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $technical->id,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($technical->id, (int) $ticket->assigned_to);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNull($ticket->technical_user_notified_assignment_at);

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:');
        });
    }

    public function test_multiple_technical_users_receive_assignment_email_when_ticket_is_assigned(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Multi Assigner', 'super-multi-assigner@example.com', User::ROLE_SUPER_USER);
        $primaryTechnical = $this->createUser('Primary Tech Alerts', 'primary-tech-alerts@example.com', User::ROLE_TECHNICAL);
        $secondaryTechnical = $this->createUser('Secondary Tech Alerts', 'secondary-tech-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Multi Assign', 'client-multi-assign@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => [$primaryTechnical->id, $secondaryTechnical->id],
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($primaryTechnical->id, (int) $ticket->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$primaryTechnical->id, $secondaryTechnical->id],
            $ticket->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
        $this->assertNotNull($ticket->technical_user_notified_assignment_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($primaryTechnical, $ticket) {
            return $mail->hasTo($primaryTechnical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($secondaryTechnical, $ticket) {
            return $mail->hasTo($secondaryTechnical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_command_sends_50_minute_unchecked_ticket_alert_to_super_users(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Reminder', 'super-reminder@example.com', User::ROLE_SUPER_USER);
        $admin = $this->createUser('Admin Reminder', 'admin-reminder@example.com', User::ROLE_ADMIN);
        $shadow = $this->createUser('Shadow Reminder', 'shadow-reminder@example.com', User::ROLE_SHADOW);
        $client = $this->createUser('Client Reminder', 'client-reminder@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category);
        $ticket->forceFill([
            'created_at' => now()->subMinutes(51),
            'updated_at' => now()->subMinutes(51),
        ])->save();

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->super_users_notified_unchecked_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($superUser, $ticket) {
            return $mail->hasTo($superUser->email)
                && str_starts_with($mail->subjectLine, 'Unchecked Ticket Alert (50 Minutes):')
                && $mail->ticket->is($ticket);
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_starts_with($mail->subjectLine, 'Unchecked Ticket Alert (50 Minutes):');
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($shadow) {
            return $mail->hasTo($shadow->email)
                && str_starts_with($mail->subjectLine, 'Unchecked Ticket Alert (50 Minutes):');
        });
    }

    public function test_command_sends_unassigned_sla_warning_after_super_user_view(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super SLA', 'super-sla@example.com', User::ROLE_SUPER_USER);
        $admin = $this->createUser('Admin SLA', 'admin-sla@example.com', User::ROLE_ADMIN);
        $shadow = $this->createUser('Shadow SLA', 'shadow-sla@example.com', User::ROLE_SHADOW);
        $client = $this->createUser('Client SLA', 'client-sla@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category);

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'last_seen_at' => now()->subHours(3)->subMinutes(31),
            'dismissed_at' => null,
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->super_users_notified_unassigned_sla_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($superUser, $ticket) {
            return $mail->hasTo($superUser->email)
                && str_starts_with($mail->subjectLine, '4-Hour SLA Reminder (Unassigned):')
                && $mail->ticket->is($ticket);
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_starts_with($mail->subjectLine, '4-Hour SLA Reminder (Unassigned):');
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($shadow) {
            return $mail->hasTo($shadow->email)
                && str_starts_with($mail->subjectLine, '4-Hour SLA Reminder (Unassigned):');
        });
    }

    public function test_command_sends_assigned_sla_warning_to_technical_user(): void
    {
        Mail::fake();

        $technical = $this->createUser('Tech SLA', 'tech-sla@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Tech SLA', 'client-tech-sla@example.com', User::ROLE_CLIENT, 'DICT');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subHours(3)->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->technical_user_notified_sla_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, '4-Hour SLA Reminder (Assigned):')
                && $mail->ticket->is($ticket);
        });
    }

    private function createUser(string $name, string $email, string $role, string $department = 'iOne'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09170000000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Email Alerts Category',
            'description' => 'Email alerts test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }

    private function createTicket(User $client, Category $category, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'name' => 'Ticket Requester',
            'contact_number' => '09185551234',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket email alert subject',
            'description' => 'Ticket email alert body',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ], $overrides));
    }
}
