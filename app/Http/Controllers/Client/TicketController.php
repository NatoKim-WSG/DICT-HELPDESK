<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\RateTicketRequest;
use App\Http\Requests\Client\Tickets\ResolveTicketRequest;
use App\Http\Requests\Client\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Client\Tickets\StoreTicketRequest;
use App\Http\Requests\Client\Tickets\UpdateTicketReplyRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use App\Support\LeadingUppercaseNormalizer;
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
        private SystemLogService $systemLogs,
    ) {}

    public function index(Request $request)
    {
        $activeTab = $request->string('tab')->toString();
        if (! in_array($activeTab, ['tickets', 'history'], true)) {
            $activeTab = 'tickets';
        }

        $allowedStatuses = $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES)
            : array_merge(['all', 'open_group'], Ticket::OPEN_STATUSES);

        $selectedStatus = trim($request->string('status')->toString());
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }
        if (! in_array($selectedStatus, $allowedStatuses, true)) {
            $selectedStatus = 'all';
        }

        $query = auth()->user()->tickets()
            ->with(['category', 'assignedUser', 'assignedUsers']);

        if ($activeTab === 'history') {
            $query->whereIn('status', Ticket::CLOSED_STATUSES);
        } else {
            $query->whereIn('status', Ticket::OPEN_STATUSES);
        }

        $query
            ->when($selectedStatus !== 'all', function ($builder) use ($selectedStatus) {
                if (in_array($selectedStatus, ['open', 'open_group'], true)) {
                    $builder->whereIn('status', Ticket::OPEN_STATUSES);

                    return;
                }

                $builder->where('status', $selectedStatus);
            })
            ->when($request->filled('priority') && $request->priority !== 'all', function ($builder) use ($request) {
                $priority = $request->string('priority')->toString();

                if ($priority === 'unassigned') {
                    $builder->whereNull('priority');

                    return;
                }

                $normalizedPriority = Ticket::normalizePriorityValue($priority);
                if ($normalizedPriority === null) {
                    return;
                }

                $builder->where('priority', $normalizedPriority);
            })
            ->when($request->filled('search'), function ($builder) use ($request) {
                $search = mb_strtolower($request->string('search')->toString());
                $pattern = '%'.$search.'%';

                $builder->where(function ($q) use ($pattern) {
                    $q->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$pattern]);
                });
            });

        $liveSnapshotToken = $this->buildTicketListSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $tickets = $query->latest()->paginate(10)->withQueryString();

        return view('client.tickets.index', compact('tickets', 'liveSnapshotToken', 'activeTab', 'selectedStatus'));
    }

    public function create()
    {
        $categories = Category::active()->get();

        return view('client.tickets.create', compact('categories'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticketData = [
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'province' => LeadingUppercaseNormalizer::normalize($request->string('province')->toString()),
            'municipality' => LeadingUppercaseNormalizer::normalize($request->string('municipality')->toString()),
            'subject' => LeadingUppercaseNormalizer::normalize($request->string('subject')->toString()),
            'description' => $request->description,
            'category_id' => $request->category_id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'priority' => null,
            'user_id' => auth()->id(),
            'consent_accepted_at' => now(),
            'consent_version' => (string) config('legal.ticket_consent_version'),
            'consent_ip_address' => $request->ip(),
            'consent_user_agent' => $request->userAgent(),
        ];

        $ticket = Ticket::create($ticketData);

        $this->persistAttachmentsFromRequest($request, $ticket);
        $this->ticketEmailAlerts->notifySuperUsersAboutNewTicket($ticket);
        $this->systemLogs->record(
            'ticket.created',
            'Created a support ticket.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'category_id' => (int) $ticket->category_id,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');
    }

    public function show(Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket, includeInternal: false);
        $replyFeedCursor = $this->replyFeedCursorForReplies($ticket->replies);

        return view('client.tickets.show', compact('ticket', 'replyFeedCursor'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->assertTicketOwner($ticket);

        return $this->replyFeedResponseForTicket(request(), $ticket, includeInternal: false);
    }

    public function reply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        if ($errorResponse = $this->invalidReplyTargetResponse($request, $ticket)) {
            return $errorResponse;
        }

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
        $this->assertTicketOwner($ticket);

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
        $this->assertTicketOwner($ticket);

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

    public function resolve(ResolveTicketRequest $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        if ($ticket->status === 'closed') {
            return redirect()->back()->with('error', 'Closed tickets cannot be marked as resolved.');
        }

        if ($ticket->status !== 'resolved') {
            $previousStatus = $ticket->status;
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
                'satisfaction_rating' => $request->integer('rating'),
                'satisfaction_comment' => $request->string('comment')->trim()->toString(),
            ]);
            $this->systemLogs->record(
                'ticket.resolved_by_client',
                'Client marked a ticket as resolved.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'previous_status' => $previousStatus,
                        'new_status' => 'resolved',
                        'rating' => $request->integer('rating'),
                    ],
                    'request' => request(),
                ]
            );
            $this->recordSatisfactionLog(
                $ticket,
                $request->integer('rating'),
                $request->string('comment')->trim()->toString(),
                $request
            );
        }

        return redirect()->back()->with('success', 'Ticket marked as resolved and your rating has been submitted.');
    }

    public function rate(RateTicketRequest $request, Ticket $ticket)
    {
        $this->assertTicketOwner($ticket);

        if ($ticket->status !== 'resolved' || $ticket->satisfaction_rating !== null) {
            return redirect()->back()->with('error', 'Only resolved tickets awaiting feedback can be rated.');
        }

        $ticket->update([
            'satisfaction_rating' => $request->integer('rating'),
            'satisfaction_comment' => $request->string('comment')->trim()->toString(),
        ]);
        $this->recordSatisfactionLog(
            $ticket,
            $request->integer('rating'),
            $request->string('comment')->trim()->toString(),
            $request
        );

        return redirect()->back()->with('success', 'Rating submitted successfully!');
    }

    private function assertTicketOwner(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
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

    private function buildTicketListSnapshotToken(Builder|HasMany $query): string
    {
        /** @var Builder<Ticket> $ticketQuery */
        $ticketQuery = $query instanceof HasMany ? $query->getQuery() : $query;
        $latestUpdatedAt = (clone $ticketQuery)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'open_tickets' => (clone $ticketQuery)->open()->count(),
            'total_tickets' => (clone $ticketQuery)->count(),
        ]));
    }

    private function recordSatisfactionLog(Ticket $ticket, int $rating, string $comment, Request $request): void
    {
        $this->systemLogs->record(
            'ticket.rating.submitted',
            'Submitted ticket satisfaction rating.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'rating' => $rating,
                    'has_comment' => $comment !== '',
                ],
                'request' => $request,
            ]
        );
    }
}
