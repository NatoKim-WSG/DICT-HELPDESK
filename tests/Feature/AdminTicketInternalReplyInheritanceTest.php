<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketInternalReplyInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_replying_to_internal_message_is_forced_internal(): void
    {
        [$client, $staff, $ticket] = $this->seedTicketWithUsers();

        $internalParent = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'message' => 'Internal parent message',
            'is_internal' => true,
        ]);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Reply to internal parent',
            'reply_to_id' => $internalParent->id,
            'is_internal' => false,
        ])->assertStatus(302);

        $reply = TicketReply::where('ticket_id', $ticket->id)
            ->where('message', 'Reply to internal parent')
            ->firstOrFail();

        $this->assertTrue((bool) $reply->is_internal);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    public function test_replying_to_public_message_remains_public_by_default(): void
    {
        [$client, $staff, $ticket] = $this->seedTicketWithUsers();

        $publicParent = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Public parent message',
            'is_internal' => false,
        ]);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Reply to public parent',
            'reply_to_id' => $publicParent->id,
            'is_internal' => false,
        ])->assertStatus(302);

        $reply = TicketReply::where('ticket_id', $ticket->id)
            ->where('message', 'Reply to public parent')
            ->firstOrFail();

        $this->assertFalse((bool) $reply->is_internal);

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
    }

    private function seedTicketWithUsers(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'client-internal-reply@example.com',
            'phone' => '09123450000',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $staff = User::create([
            'name' => 'Super User',
            'email' => 'super-user-internal-reply@example.com',
            'phone' => '09123451111',
            'department' => 'iOne',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General concerns',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester Name',
            'contact_number' => '09120000000',
            'email' => 'requester-internal-reply@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'subject' => 'Reply inheritance ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $staff, $ticket];
    }
}

