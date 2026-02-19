<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = auth()->user()->tickets()
            ->with(['category', 'assignedUser']);

        $query
            ->when($request->filled('status') && $request->status !== 'all', function ($builder) use ($request) {
                $builder->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('priority') && $request->priority !== 'all', function ($builder) use ($request) {
                $builder->where('priority', $request->string('priority')->toString());
            })
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = $request->string('search')->toString();
                $builder->where(function ($q) use ($search) {
                    $q->where('subject', 'like', '%' . $search . '%')
                        ->orWhere('ticket_number', 'like', '%' . $search . '%');
                });
            });

        $tickets = $query->latest()->paginate(10);

        return view('client.tickets.index', compact('tickets'));
    }

    public function create()
    {
        $categories = Category::active()->get();
        return view('client.tickets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:30',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'priority' => 'required|in:' . implode(',', Ticket::PRIORITIES),
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
        ]);

        $ticketData = [
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'subject' => $request->subject,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'priority' => $request->priority,
            'user_id' => auth()->id(),
        ];

        $ticket = Ticket::create($ticketData);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                $ticket->attachments()->create([
                    'filename' => basename($path),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');
    }

    public function show(Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        $ticket->load(['category', 'assignedUser', 'replies.user', 'replies.attachments', 'replies.replyTo', 'attachments']);

        return view('client.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        $request->validate([
            'message' => 'required|string',
            'reply_to_id' => 'nullable|integer|exists:ticket_replies,id',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
        ]);

        if ($errorResponse = $this->invalidReplyTargetResponse($request, $ticket)) {
            return $errorResponse;
        }

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'reply_to_id' => $request->integer('reply_to_id') ?: null,
            'message' => $request->message,
            'is_internal' => false,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments', 'public');

                $reply->attachments()->create([
                    'filename' => basename($path),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        if (in_array($ticket->status, Ticket::CLOSED_STATUSES, true)) {
            $ticket->update(['status' => 'open']);
        }

        if ($request->expectsJson()) {
            $reply->load(['attachments', 'replyTo']);

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

    public function updateReply(Request $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->assertTicketOwner($ticket);

        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
        }

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
        }

        if ($reply->created_at && $reply->created_at->lt(now()->subHours(3))) {
            return response()->json(['message' => 'Messages can only be edited within 3 hours.'], 422);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $reply->update([
            'message' => $request->string('message')->toString(),
            'edited_at' => now(),
        ]);

        return response()->json([
            'message' => 'Message edited',
            'reply' => $this->formatReplyForChat($reply->fresh(['attachments', 'replyTo'])),
        ]);
    }

    public function deleteReply(Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->assertTicketOwner($ticket);

        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
        }

        if ($reply->created_at && $reply->created_at->lt(now()->subHours(3))) {
            return response()->json(['message' => 'Messages can only be deleted within 3 hours.'], 422);
        }

        if (!$reply->deleted_at) {
            $reply->update([
                'message' => 'This message was deleted.',
                'deleted_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Message deleted',
            'reply' => $this->formatReplyForChat($reply->fresh(['attachments', 'replyTo'])),
        ]);
    }

    public function close(Request $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        $request->validate([
            'close_reason' => 'required|string|max:1000',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => "Client closed the ticket as unresolved.\nReason: " . $request->close_reason,
            'is_internal' => false,
        ]);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Ticket closed successfully with your reason.');
    }

    public function resolve(Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        if ($ticket->status === 'closed') {
            return redirect()->back()->with('error', 'Closed tickets cannot be marked as resolved.');
        }

        if ($ticket->status !== 'resolved') {
            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'message' => 'Client marked this ticket as resolved.',
                'is_internal' => false,
            ]);

            $ticket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Ticket marked as resolved.');
    }

    public function rate(Request $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $ticket->update([
            'satisfaction_rating' => $request->rating,
            'satisfaction_comment' => $request->comment,
        ]);

        return redirect()->back()->with('success', 'Rating submitted successfully!');
    }

    private function formatReplyForChat(TicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'created_at_iso' => optional($reply->created_at)->toIso8601String(),
            'created_at_human' => optional($reply->created_at)->diffForHumans(),
            'created_at_label' => optional($reply->created_at)?->greaterThan(now()->subDay())
                ? optional($reply->created_at)?->format('g:i A')
                : optional($reply->created_at)?->format('M j, Y'),
            'from_support' => false,
            'can_manage' => (bool) ($reply->user_id === auth()->id()),
            'edited' => (bool) $reply->edited_at,
            'deleted' => (bool) $reply->deleted_at,
            'reply_to_id' => $reply->reply_to_id,
            'reply_to_message' => $reply->replyTo?->message,
            'reply_to_excerpt' => $reply->replyTo?->message,
            'attachments' => $reply->attachments->map(function ($attachment) {
                return [
                    'download_url' => $attachment->download_url,
                    'preview_url' => $attachment->preview_url,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                ];
            })->values(),
        ];
    }

    private function assertTicketOwner(Ticket $ticket): void
    {
        if ($ticket->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function invalidReplyTargetResponse(Request $request, Ticket $ticket): ?JsonResponse
    {
        if (!$request->filled('reply_to_id')) {
            return null;
        }

        $replyTo = TicketReply::where('ticket_id', $ticket->id)->find($request->integer('reply_to_id'));

        if ($replyTo) {
            return null;
        }

        return response()->json([
            'message' => 'Invalid reply target.',
        ], 422);
    }
}
