<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait InteractsWithTicketReplies
{
    protected function persistAttachmentsFromRequest(Request $request, Ticket|TicketReply $attachable): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $path = $file->store('attachments', (string) config('helpdesk.attachments_disk', 'local'));

            $attachable->attachments()->create([
                'filename' => basename($path),
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    protected function formatReplyForChat(TicketReply $reply): array
    {
        $fromSupport = in_array(optional($reply->user)->normalizedRole(), User::TICKET_CONSOLE_ROLES, true);
        $createdAt = $reply->created_at;

        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'is_internal' => (bool) $reply->is_internal,
            'created_at_iso' => optional($createdAt)->toIso8601String(),
            'created_at_human' => optional($createdAt)->diffForHumans(),
            'created_at_label' => $createdAt && $createdAt->greaterThan(now()->subDay())
                ? $createdAt->format('g:i A')
                : optional($createdAt)->format('M j, Y'),
            'from_support' => $fromSupport,
            'avatar_logo' => $this->departmentLogoForUser($reply->user, $fromSupport),
            'can_manage' => (bool) ($reply->user_id === auth()->id()),
            'edited' => (bool) $reply->edited_at,
            'deleted' => (bool) $reply->deleted_at,
            'reply_to_id' => $reply->reply_to_id,
            'reply_to_message' => $reply->replyTo ? Str::limit($reply->replyTo->message, 120) : null,
            'reply_to_excerpt' => $reply->replyTo ? Str::limit($reply->replyTo->message, 120) : null,
            'attachments' => $reply->attachments->map(function ($attachment) {
                $previewUrl = $attachment->preview_url;

                return [
                    'download_url' => $attachment->download_url,
                    'preview_url' => $previewUrl,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
                    'can_preview' => (bool) $previewUrl,
                ];
            })->values(),
        ];
    }

    protected function replyTargetExistsForTicket(Ticket $ticket, ?int $replyToId): bool
    {
        if (! $replyToId) {
            return true;
        }

        return TicketReply::where('ticket_id', $ticket->id)
            ->whereKey($replyToId)
            ->exists();
    }

    protected function departmentLogoForUser(?User $user, bool $fromSupport): string
    {
        if ($fromSupport) {
            return User::supportLogoUrl();
        }

        return User::departmentBrandAssets(optional($user)->department, optional($user)->role)['logo_url'];
    }
}
