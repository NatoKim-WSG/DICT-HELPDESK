<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $viewer = $this->currentReplyViewer();
        $fromSupport = in_array(optional($reply->user)->normalizedRole(), User::TICKET_CONSOLE_ROLES, true);
        $createdAt = $reply->created_at;
        $replyTo = $reply->replyTo && $reply->replyTo->isVisibleTo($viewer)
            ? $reply->replyTo
            : null;

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
            'reply_to_message' => $replyTo ? Str::limit($replyTo->message, 120) : null,
            'reply_to_excerpt' => $replyTo ? Str::limit($replyTo->message, 120) : null,
            'attachments' => $reply->attachments->map(function (Attachment $attachment) {
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

    protected function replyTargetExistsForTicket(Ticket $ticket, ?int $replyToId, bool $includeInternal = true): bool
    {
        if (! $replyToId) {
            return true;
        }

        return $this->visibleRepliesQueryForTicket($ticket, $includeInternal)
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

    protected function currentReplyViewer(): ?User
    {
        return auth()->user();
    }

    protected function visibleRepliesQueryForTicket(Ticket $ticket, bool $includeInternal = true): HasMany|Builder
    {
        return $ticket->replies()
            ->when(! $includeInternal, fn (Builder $query) => $query->where('is_internal', false))
            ->visibleToViewer($this->currentReplyViewer());
    }

    protected function visibleRepliesRelationForTicket(Ticket $ticket, bool $includeInternal = true): Collection
    {
        return $this->visibleRepliesQueryForTicket($ticket, $includeInternal)
            ->with([
                'user',
                'attachments',
                'replyTo' => fn ($query) => $query
                    ->visibleToViewer($this->currentReplyViewer())
                    ->with('user'),
            ])
            ->orderBy('created_at')
            ->get();
    }

    protected function loadTicketWithVisibleReplies(Ticket $ticket, bool $includeInternal = true): void
    {
        $ticket->load(['user', 'category', 'assignedUser', 'assignedUsers', 'closedBy', 'attachments']);
        $ticket->setRelation('replies', $this->visibleRepliesRelationForTicket($ticket, $includeInternal));
    }
}
