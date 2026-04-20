<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesClientTickets;
use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Client\Tickets\UpdateTicketReplyRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketConversationController extends Controller
{
    use AuthorizesClientTickets;
    use InteractsWithTicketReplies;

    public function show(Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket, includeInternal: false);
        $replyFeedCursor = $this->replyFeedCursorForReplies($ticket->replies);

        return view('client.tickets.show', compact('ticket', 'replyFeedCursor'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->authorizeOwnedTicket($ticket);

        return $this->replyFeedResponseForTicket(request(), $ticket, includeInternal: false);
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        if ($errorResponse = $this->invalidReplyTargetResponse($request, $ticket)) {
            return $errorResponse;
        }

        $reply = $this->withAttachmentWriteGuard(function () use ($request, $ticket) {
            return DB::transaction(function () use ($request, $ticket) {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'reply_to_id' => $request->integer('reply_to_id') ?: null,
                    'message' => trim($request->string('message')->toString()),
                    'is_internal' => false,
                ]);

                $this->persistAttachmentsFromRequest($request, $reply);

                if (in_array($ticket->status, Ticket::CLOSED_STATUSES, true)) {
                    $ticket->update([
                        'status' => 'open',
                        ...Ticket::reopenedLifecycleResetAttributes(),
                    ]);
                }

                return $reply;
            });
        });

        if ($request->expectsJson()) {
            $reply->load(['user', 'attachments', 'replyTo']);

            return response()->json([
                'message' => 'Reply sent',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return redirect()->back()->with([
            'chat_success' => 'Reply sent',
            'suppress_success_banner' => true,
        ]);
    }

    public function updateReply(UpdateTicketReplyRequest $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeOwnedTicket($ticket);

        if ($reply->ticket_id !== $ticket->id) {
            abort(403);
        }
        $this->authorize('update', $reply);

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
        }

        if ($reply->created_at && $reply->created_at->lt(now()->subHours(3))) {
            return response()->json(['message' => 'Messages can only be edited within 3 hours.'], 422);
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
        $this->authorizeOwnedTicket($ticket);

        if ($reply->ticket_id !== $ticket->id) {
            abort(403);
        }
        $this->authorize('delete', $reply);

        if ($reply->created_at && $reply->created_at->lt(now()->subHours(3))) {
            return response()->json(['message' => 'Messages can only be deleted within 3 hours.'], 422);
        }

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

    private function invalidReplyTargetResponse(Request $request, Ticket $ticket): JsonResponse|RedirectResponse|null
    {
        if (! $request->filled('reply_to_id')) {
            return null;
        }

        if ($this->replyTargetExistsForTicket($ticket, $request->integer('reply_to_id'), includeInternal: false)) {
            return null;
        }

        if (! $request->expectsJson()) {
            return redirect()->back()->with('error', 'Invalid reply target.');
        }

        return response()->json([
            'message' => 'Invalid reply target.',
        ], 422);
    }
}
