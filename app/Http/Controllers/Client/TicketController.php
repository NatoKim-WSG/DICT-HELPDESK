<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Services\TicketEmailAlertService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    public function __construct(
        private TicketEmailAlertService $ticketEmailAlerts,
    ) {}

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
                    $q->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('ticket_number', 'like', '%'.$search.'%');
                });
            });

        $liveSnapshotToken = $this->buildTicketListSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $tickets = $query->latest()->paginate(10);

        return view('client.tickets.index', compact('tickets', 'liveSnapshotToken'));
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
            'email' => 'required|email|max:255',
            'province' => 'required|string|max:120',
            'municipality' => 'required|string|max:120',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'priority' => 'required|in:'.implode(',', Ticket::PRIORITIES),
            'ticket_consent' => 'accepted',
            'attachments' => 'required|array|min:1',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt',
        ]);

        $ticketData = [
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'province' => $this->normalizeLeadingUppercase($request->string('province')->toString()),
            'municipality' => $this->normalizeLeadingUppercase($request->string('municipality')->toString()),
            'subject' => $this->normalizeLeadingUppercase($request->string('subject')->toString()),
            'description' => $request->description,
            'category_id' => $request->category_id,
            'priority' => $request->priority,
            'user_id' => auth()->id(),
            'consent_accepted_at' => now(),
            'consent_version' => (string) config('legal.ticket_consent_version'),
            'consent_ip_address' => $request->ip(),
            'consent_user_agent' => $request->userAgent(),
        ];

        $ticket = Ticket::create($ticketData);

        $this->persistAttachmentsFromRequest($request, $ticket);
        $this->ticketEmailAlerts->notifySuperUsersAboutNewTicket($ticket);

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');
    }

    public function show(Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        TicketUserState::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
            ],
            [
                'last_seen_at' => $ticket->updated_at ?? now(),
                'dismissed_at' => null,
            ]
        );

        $ticket->load(['category', 'assignedUser', 'replies.user', 'replies.attachments', 'replies.replyTo', 'attachments']);

        return view('client.tickets.show', compact('ticket'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->assertTicketOwner($ticket);

        $replies = $ticket->replies()
            ->where('is_internal', false)
            ->with(['user', 'attachments', 'replyTo'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketReply $reply) => $this->formatReplyForChat($reply))
            ->values();

        return response()->json([
            'replies' => $replies,
        ]);
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

        $this->persistAttachmentsFromRequest($request, $reply);

        if (in_array($ticket->status, Ticket::CLOSED_STATUSES, true)) {
            $ticket->update([
                'status' => 'open',
                'resolved_at' => null,
                'closed_at' => null,
                'super_users_notified_unassigned_sla_at' => null,
                'technical_user_notified_sla_at' => null,
            ]);
        }

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
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo'])),
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

    public function close(Request $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        $request->validate([
            'close_reason' => 'required|string|max:1000',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => "Client closed the ticket as unresolved.\nReason: ".$request->close_reason,
            'is_internal' => false,
        ]);

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => null,
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
                'closed_at' => null,
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

    private function assertTicketOwner(Ticket $ticket): void
    {
        if ($ticket->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function invalidReplyTargetResponse(Request $request, Ticket $ticket): JsonResponse|RedirectResponse|null
    {
        if (! $request->filled('reply_to_id')) {
            return null;
        }

        if ($this->replyTargetExistsForTicket($ticket, $request->integer('reply_to_id'))) {
            return null;
        }

        if (! $request->expectsJson()) {
            return redirect()->back()->with('error', 'Invalid reply target.');
        }

        return response()->json([
            'message' => 'Invalid reply target.',
        ], 422);
    }

    private function buildTicketListSnapshotToken(Builder|HasMany $query): string
    {
        $latestUpdatedAt = (clone $query)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'open_tickets' => (clone $query)->open()->count(),
            'total_tickets' => (clone $query)->count(),
        ]));
    }

    private function normalizeLeadingUppercase(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        return mb_strtoupper(mb_substr($trimmed, 0, 1)).mb_substr($trimmed, 1);
    }
}
