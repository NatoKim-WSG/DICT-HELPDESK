<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminReturnToRedirects;
use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketReplyRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Admin\TicketStatusWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketReplyController extends Controller
{
    use HandlesAdminReturnToRedirects;
    use InteractsWithTicketReplies;

    public function __construct(
        private TicketStatusWorkflowService $ticketStatusWorkflow,
    ) {}

    public function replies(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        return $this->replyFeedResponseForTicket($request, $ticket);
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $replyToId = $request->integer('reply_to_id') ?: null;

        if (! $this->replyTargetExistsForTicket($ticket, $replyToId)) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Invalid reply target.');
        }

        $replyTarget = null;
        if ($replyToId) {
            $replyTarget = $this->visibleRepliesQueryForTicket($ticket)
                ->select(['id', 'is_internal'])
                ->whereKey($replyToId)
                ->first();
        }

        $isInternal = $request->boolean('is_internal') || (bool) optional($replyTarget)->is_internal;

        $reply = $this->withAttachmentWriteGuard(function () use ($request, $ticket, $replyToId, $isInternal) {
            return DB::transaction(function () use ($request, $ticket, $replyToId, $isInternal) {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'reply_to_id' => $replyToId,
                    'message' => trim($request->string('message')->toString()),
                    'is_internal' => $isInternal,
                ]);

                $this->persistAttachmentsFromRequest($request, $reply);

                if ($ticket->status === 'open' && ! $isInternal) {
                    $ticket->update(['status' => 'in_progress']);
                }
                $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);

                return $reply;
            });
        });

        if ($request->expectsJson()) {
            $reply->loadMissing(['user', 'attachments', 'replyTo']);

            return response()->json([
                'message' => 'Reply added successfully.',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Reply added successfully!');
    }

    public function updateReply(UpdateTicketReplyRequest $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        if ($reply->ticket_id !== $ticket->id) {
            abort(403);
        }
        $this->authorize('update', $reply);

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
        }

        $reply->update([
            'message' => $request->string('message')->toString(),
            'edited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message edited',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo'])),
        ]);
    }

    public function deleteReply(Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        if ($reply->ticket_id !== $ticket->id) {
            abort(403);
        }
        $this->authorize('delete', $reply);

        if (! $reply->deleted_at) {
            $reply->update([
                'message' => 'This message was deleted.',
                'deleted_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Message deleted',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo'])),
        ]);
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }
}
