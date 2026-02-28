<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_download_internal_reply_attachment_even_for_owned_ticket(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketContext();
        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($internalReply, 'internal-note.txt', 'internal note');

        $response = $this->actingAs($client)
            ->get(route('attachments.download', $attachment));

        $response->assertForbidden();
    }

    public function test_super_user_can_download_internal_reply_attachment(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($internalReply, 'internal-visible.txt', 'internal visible');

        $response = $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
    }

    private function seedTicketContext(): array
    {
        Storage::fake('local');
        Storage::fake('public');
        config()->set('helpdesk.attachments_disk', 'local');

        $client = User::create([
            'name' => 'Attachment Client',
            'email' => 'attachment-client@example.com',
            'phone' => '09123450001',
            'department' => 'DICT',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superUser = User::create([
            'name' => 'Attachment Super User',
            'email' => 'attachment-super-user@example.com',
            'phone' => '09123450002',
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
            'name' => 'Attachment Requester',
            'contact_number' => '09120000001',
            'email' => 'attachment-requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'subject' => 'Attachment visibility test',
            'description' => 'Attachment visibility test ticket.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $superUser, $ticket];
    }

    private function createReplyAttachment(TicketReply $reply, string $filename, string $contents): Attachment
    {
        $filePath = 'attachments/'.$filename;
        Storage::disk('local')->put($filePath, $contents);

        return Attachment::create([
            'filename' => $filename,
            'original_filename' => $filename,
            'file_path' => $filePath,
            'mime_type' => 'text/plain',
            'file_size' => strlen($contents),
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);
    }
}
