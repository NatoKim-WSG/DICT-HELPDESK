<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        if ($activeTab === 'scheduled') {
            $activeTab = 'attention';
        }
        if (! in_array($activeTab, ['tickets', 'attention', 'history'], true)) {
            $activeTab = 'tickets';
        }
        $selectedStatus = trim($request->string('status')->toString());
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }
        if (! in_array($selectedStatus, array_merge(['all'], Ticket::STATUSES), true)) {
            $selectedStatus = 'all';
        }
        $includeClosed = $request->boolean('include_closed');

        $query = $this->scopedTicketQueryForCurrentUser()
            ->with(['user', 'category', 'assignedUser']);

        if ($activeTab === 'history') {
            $query->whereIn('status', Ticket::CLOSED_STATUSES);
        } elseif ($activeTab === 'attention') {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16));
        } elseif ($selectedStatus === 'all' && ! $includeClosed) {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES);
        }

        $query
            ->when($selectedStatus !== 'all', function ($builder) use ($selectedStatus) {
                $builder->where('status', $selectedStatus);
            })
            ->when($request->filled('priority') && $request->priority !== 'all', function ($builder) use ($request) {
                $builder->where('priority', $request->string('priority')->toString());
            })
            ->when($request->filled('category') && $request->category !== 'all', function ($builder) use ($request) {
                $builder->where('category_id', $request->integer('category'));
            });

        if ($request->filled('province') && $request->province !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'province', (string) $request->province);
        }

        if ($request->filled('municipality') && $request->municipality !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'municipality', (string) $request->municipality);
        }

        if ($request->filled('account_id') && $request->account_id !== 'all') {
            $query->where('user_id', $request->integer('account_id'));
        }

        if ($request->filled('assigned_to') && $request->assigned_to !== 'all') {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        $query->when($request->filled('search'), function ($builder) use ($request) {
            $search = mb_strtolower($request->string('search')->toString());
            $pattern = '%'.$search.'%';

            $builder->where(function ($q) use ($pattern) {
                $q->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$pattern])
                    ->orWhereHas('user', function ($userQuery) use ($pattern) {
                        $userQuery->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                    });
            });
        });

        $liveSnapshotToken = $this->buildTicketListSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $scopedTickets = $this->scopedTicketQueryForCurrentUser();
        $provinceOptions = $this->distinctTicketColumnOptions('province', clone $scopedTickets);
        $municipalityOptions = $this->distinctTicketColumnOptions('municipality', clone $scopedTickets);

        $currentUser = auth()->user();
        if ($currentUser && $currentUser->isTechnician()) {
            $accountOptionsQuery = User::where('role', User::ROLE_CLIENT)
                ->where('is_active', true);
            $visibleClientIds = (clone $scopedTickets)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct()
                ->pluck('user_id');

            $accountOptionsQuery->whereIn('id', $visibleClientIds);
            $accountOptions = $accountOptionsQuery
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values();
        } else {
            $accountOptions = Cache::remember('admin_ticket_account_options_active_clients_v1', now()->addSeconds(60), function () {
                return User::where('role', User::ROLE_CLIENT)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->values();
            });
        }

        $tickets = $query->latest()->paginate(15);
        $ticketSeenTimestamps = TicketUserState::where('user_id', auth()->id())
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get()
            ->mapWithKeys(function (TicketUserState $state) {
                return [
                    (int) $state->ticket_id => optional($state->last_seen_at)->timestamp,
                ];
            })
            ->all();

        $categories = Cache::remember('admin_ticket_active_categories_v1', now()->addSeconds(120), function () {
            return Category::active()
                ->orderBy('name')
                ->get(['id', 'name']);
        });
        $agents = $this->activeAssignableAgents();

        return view('admin.tickets.index', compact(
            'tickets',
            'categories',
            'agents',
            'provinceOptions',
            'municipalityOptions',
            'accountOptions',
            'activeTab',
            'liveSnapshotToken',
            'ticketSeenTimestamps'
        ));
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        TicketUserState::markSeen($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $ticket->load(['user', 'category', 'assignedUser', 'replies.user', 'replies.attachments', 'replies.replyTo', 'attachments']);
        $agents = $this->activeAssignableAgents();

        return view('admin.tickets.show', compact('ticket', 'agents'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        $replies = $ticket->replies()
            ->with(['user', 'attachments', 'replyTo'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TicketReply $reply) => $this->formatReplyForChat($reply))
            ->values();

        return response()->json([
            'replies' => $replies,
        ]);
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
        ]);

        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;

        if ($previousAssignedTo === $newAssignedTo) {
            return redirect()->back()->with('success', 'No changes were detected.');
        }

        $ticket->update([
            'assigned_to' => $newAssignedTo,
            ...$this->assignmentMetadataForChange($previousAssignedTo, $newAssignedTo),
        ]);
        $this->systemLogs->record(
            'ticket.assignment.updated',
            'Updated ticket assignment.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_assigned_to' => $previousAssignedTo,
                    'assigned_to' => $newAssignedTo,
                ],
                'request' => $request,
            ]
        );

        $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);

        if ($newAssignedTo !== null) {
            $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment($ticket->fresh(['assignedUser']));
        }

        return redirect()->back()->with('success', 'Ticket assignment updated successfully!');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'status' => 'required|in:'.implode(',', Ticket::STATUSES),
            'close_reason' => [
                Rule::requiredIf(fn () => $request->string('status')->toString() === 'closed'),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $previousStatus = $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $this->determineReviewerAssignee($nextStatus, $previousAssignedTo);

        if ($previousStatus === $nextStatus) {
            return redirect()->back()->with('success', 'No changes were detected.');
        }

        $updateData = ['status' => $nextStatus];
        if ($previousAssignedTo !== $newAssignedTo) {
            $updateData['assigned_to'] = $newAssignedTo;
            $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedTo, $newAssignedTo));
        }
        $this->applyLifecycleTimestamps($ticket, $updateData);

        $ticket->update($updateData);
        if ($previousAssignedTo !== $newAssignedTo) {
            $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);
        }
        $this->recordStatusClosureReason($ticket, $previousStatus, $nextStatus, $request->string('close_reason')->toString());
        $this->systemLogs->record(
            'ticket.status.updated',
            'Updated ticket status.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_status' => $previousStatus,
                    'new_status' => $nextStatus,
                ],
                'request' => $request,
            ]
        );

        return redirect()->back()->with('success', 'Ticket status updated successfully!');
    }

    public function updatePriority(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate(['priority' => 'required|in:'.implode(',', Ticket::PRIORITIES)]);

        if ($ticket->priority === $request->priority) {
            return redirect()->back()->with('success', 'No changes were detected.');
        }

        $previousPriority = $ticket->priority;
        $ticket->update(['priority' => $request->priority]);
        $this->systemLogs->record(
            'ticket.priority.updated',
            'Updated ticket priority.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_priority' => $previousPriority,
                    'new_priority' => $request->string('priority')->toString(),
                ],
                'request' => $request,
            ]
        );

        return redirect()->back()->with('success', 'Ticket priority updated successfully!');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'message' => 'nullable|string|required_without:attachments',
            'is_internal' => 'boolean',
            'reply_to_id' => 'nullable|integer|exists:ticket_replies,id',
            'attachments' => 'nullable|array|min:1|required_without:message',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx',
        ]);

        $replyToId = $request->integer('reply_to_id') ?: null;

        if (! $this->replyTargetExistsForTicket($ticket, $replyToId)) {
            return redirect()->back()->with('error', 'Invalid reply target.');
        }

        $replyTarget = null;
        if ($replyToId) {
            $replyTarget = $ticket->replies()
                ->select(['id', 'is_internal'])
                ->whereKey($replyToId)
                ->first();
        }

        $isInternal = $request->boolean('is_internal') || (bool) optional($replyTarget)->is_internal;

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

        if ($request->expectsJson()) {
            $reply->loadMissing(['user', 'attachments', 'replyTo']);

            return response()->json([
                'message' => 'Reply added successfully.',
                'reply' => $this->formatReplyForChat($reply),
            ]);
        }

        return redirect()->back()->with('success', 'Reply added successfully!');
    }

    public function updateReply(Request $request, Ticket $ticket, TicketReply $reply): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
        }

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
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
        $this->authorizeTicketAccess($ticket);

        if ($reply->ticket_id !== $ticket->id || $reply->user_id !== auth()->id()) {
            abort(403);
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

    public function setDueDate(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'due_date' => 'required|date|after:now',
        ]);

        $incomingDueDateLabel = \Illuminate\Support\Carbon::parse($request->due_date)->format('Y-m-d H:i');
        $existingDueDateLabel = optional($ticket->due_date)->format('Y-m-d H:i');
        if ($existingDueDateLabel === $incomingDueDateLabel) {
            return redirect()->back()->with('success', 'No changes were detected.');
        }

        $previousDueDate = optional($ticket->due_date)->toDateTimeString();
        $ticket->update(['due_date' => $request->due_date]);
        $this->systemLogs->record(
            'ticket.due_date.updated',
            'Updated ticket due date.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_due_date' => $previousDueDate,
                    'new_due_date' => optional($ticket->fresh()->due_date)->toDateTimeString(),
                ],
                'request' => $request,
            ]
        );

        return redirect()->back()->with('success', 'Due date set successfully!');
    }

    public function quickUpdate(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $request->validate([
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
            'status' => 'required|in:'.implode(',', Ticket::STATUSES),
            'priority' => 'required|in:'.implode(',', Ticket::PRIORITIES),
            'close_reason' => [
                Rule::requiredIf(fn () => $request->string('status')->toString() === 'closed'),
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $previousStatus = $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $nextPriority = $request->string('priority')->toString();
        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $requestedAssignedTo = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;
        $newAssignedTo = $this->determineReviewerAssignee($nextStatus, $requestedAssignedTo);
        $updateData = [
            'assigned_to' => $newAssignedTo,
            'status' => $nextStatus,
            'priority' => $nextPriority,
        ];
        $previousPriority = (string) $ticket->priority;

        if (
            $previousAssignedTo === $newAssignedTo
            && $previousStatus === $nextStatus
            && strtolower($previousPriority) === strtolower($nextPriority)
        ) {
            return redirect()->back()->with('success', 'No changes were detected.');
        }

        $this->applyLifecycleTimestamps($ticket, $updateData);
        $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedTo, $newAssignedTo));

        $ticket->update($updateData);
        $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);
        $this->recordStatusClosureReason($ticket, $previousStatus, $nextStatus, $request->string('close_reason')->toString());
        $this->systemLogs->record(
            'ticket.quick_update',
            'Applied quick ticket update.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_assigned_to' => $previousAssignedTo,
                    'assigned_to' => $newAssignedTo,
                    'previous_status' => $previousStatus,
                    'new_status' => $nextStatus,
                    'previous_priority' => $previousPriority,
                    'new_priority' => $nextPriority,
                ],
                'request' => $request,
            ]
        );

        if ($previousAssignedTo !== $newAssignedTo && $newAssignedTo !== null) {
            $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment($ticket->fresh(['assignedUser']));
        }

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        if (! $this->canDeleteTickets()) {
            abort(403, 'Only admins can delete tickets.');
        }

        $ticketNumber = $ticket->ticket_number;
        $ticketId = $ticket->id;

        DB::transaction(function () use ($ticket) {
            $ticket->attachments()->get()->each->delete();
            $ticket->replies()->with('attachments')->get()->each(function ($reply) {
                $reply->attachments()->get()->each->delete();
                $reply->delete();
            });
            $ticket->delete();
        });
        $this->systemLogs->record(
            'ticket.deleted',
            'Deleted a ticket.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticketId,
                'metadata' => [
                    'ticket_number' => $ticketNumber,
                ],
            ]
        );

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:delete,assign,status,priority,merge',
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|exists:tickets,id',
            'assigned_to' => [
                'nullable',
                $this->assignableAgentRule(),
            ],
            'status' => 'nullable|in:'.implode(',', Ticket::STATUSES),
            'priority' => 'nullable|in:'.implode(',', Ticket::PRIORITIES),
        ]);

        $selectedIds = collect($request->input('selected_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return redirect()->back()->with('error', 'No tickets selected.');
        }

        $action = $request->string('action')->toString();
        $tickets = $this->scopedTicketQueryForCurrentUser()
            ->whereIn('id', $selectedIds)
            ->get();

        if ($tickets->isEmpty()) {
            return redirect()->back()->with('error', 'Selected tickets were not found.');
        }

        if ($tickets->count() !== $selectedIds->count()) {
            return redirect()->back()->with('error', 'One or more selected tickets are not accessible to your account.');
        }

        if ($action === 'delete' && ! $this->canDeleteTickets()) {
            return redirect()->back()->with('error', 'Only admins can run delete actions.');
        }

        if ($action === 'merge' && ! $this->canRunDestructiveAction()) {
            return redirect()->back()->with('error', 'Only super users or admins can run merge actions.');
        }

        if ($action === 'delete') {
            $ticketNumbers = $tickets->pluck('ticket_number')->values()->all();
            DB::transaction(function () use ($tickets) {
                $tickets->each(function ($ticket) {
                    $ticket->attachments()->get()->each->delete();
                    $ticket->replies()->with('attachments')->get()->each(function ($reply) {
                        $reply->attachments()->get()->each->delete();
                        $reply->delete();
                    });
                    $ticket->delete();
                });
            });
            $this->systemLogs->record(
                'ticket.bulk.delete',
                'Deleted tickets in bulk.',
                [
                    'category' => 'ticket',
                    'metadata' => [
                        'ticket_ids' => $selectedIds->all(),
                        'ticket_numbers' => $ticketNumbers,
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Selected tickets deleted successfully.');
        }

        if ($action === 'assign') {
            if (! $request->filled('assigned_to')) {
                return redirect()->back()->with('error', 'Please choose a technical user.');
            }

            $newAssignedTo = $request->integer('assigned_to');
            Ticket::whereIn('id', $selectedIds)->get()->each(function (Ticket $ticket) use ($newAssignedTo) {
                $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
                $ticket->update([
                    'assigned_to' => $newAssignedTo,
                    ...$this->assignmentMetadataForChange($previousAssignedTo, $newAssignedTo),
                ]);
                $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);

                if ($newAssignedTo !== null) {
                    $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment($ticket->fresh(['assignedUser']));
                }
            });
            $this->systemLogs->record(
                'ticket.bulk.assign',
                'Assigned tickets in bulk.',
                [
                    'category' => 'ticket',
                    'metadata' => [
                        'ticket_ids' => $selectedIds->all(),
                        'assigned_to' => $newAssignedTo,
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Selected tickets assigned successfully.');
        }

        if ($action === 'status') {
            if (! $request->filled('status')) {
                return redirect()->back()->with('error', 'Please choose a status.');
            }

            $newStatus = $request->string('status')->toString();
            $closeReason = trim($request->string('close_reason')->toString());
            if ($newStatus === 'closed' && $closeReason === '') {
                return redirect()->back()->with('error', 'Please provide a reason before closing ticket(s).');
            }

            $tickets->each(function (Ticket $ticket) use ($newStatus, $closeReason) {
                $previousStatus = (string) $ticket->status;
                $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
                $newAssignedTo = $this->determineReviewerAssignee($newStatus, $previousAssignedTo);

                $updateData = ['status' => $newStatus];
                if ($previousAssignedTo !== $newAssignedTo) {
                    $updateData['assigned_to'] = $newAssignedTo;
                    $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedTo, $newAssignedTo));
                }
                $this->applyLifecycleTimestamps($ticket, $updateData);

                if ($previousStatus === $newStatus && $previousAssignedTo === $newAssignedTo) {
                    return;
                }

                $ticket->update($updateData);

                if ($previousAssignedTo !== $newAssignedTo) {
                    $this->recordAssignmentHandoff($ticket, $previousAssignedTo, $newAssignedTo);
                }

                if ($newStatus === 'closed') {
                    $this->recordStatusClosureReason($ticket, $previousStatus, 'closed', $closeReason);
                }
            });
            $this->systemLogs->record(
                'ticket.bulk.status',
                'Updated ticket statuses in bulk.',
                [
                    'category' => 'ticket',
                    'metadata' => [
                        'ticket_ids' => $selectedIds->all(),
                        'status' => $newStatus,
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Selected ticket statuses updated.');
        }

        if ($action === 'priority') {
            if (! $request->filled('priority')) {
                return redirect()->back()->with('error', 'Please choose a priority.');
            }

            Ticket::whereIn('id', $selectedIds)->update(['priority' => $request->string('priority')->toString()]);
            $this->systemLogs->record(
                'ticket.bulk.priority',
                'Updated ticket priorities in bulk.',
                [
                    'category' => 'ticket',
                    'metadata' => [
                        'ticket_ids' => $selectedIds->all(),
                        'priority' => $request->string('priority')->toString(),
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->back()->with('success', 'Selected ticket priorities updated.');
        }

        if ($action === 'merge') {
            if ($selectedIds->count() < 2) {
                return redirect()->back()->with('error', 'Select at least two tickets to merge.');
            }

            $orderedTickets = Ticket::whereIn('id', $selectedIds)->orderBy('created_at')->get();
            $primary = $orderedTickets->first();
            $others = $orderedTickets->slice(1);

            DB::transaction(function () use ($primary, $others) {
                foreach ($others as $ticket) {
                    TicketReply::create([
                        'ticket_id' => $primary->id,
                        'user_id' => auth()->id(),
                        'message' => "Merged ticket {$ticket->ticket_number}: {$ticket->subject}",
                        'is_internal' => true,
                    ]);

                    TicketReply::where('ticket_id', $ticket->id)->update(['ticket_id' => $primary->id]);
                    Attachment::where('attachable_type', Ticket::class)
                        ->where('attachable_id', $ticket->id)
                        ->update(['attachable_id' => $primary->id]);

                    $ticket->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                }
            });
            $this->systemLogs->record(
                'ticket.bulk.merge',
                'Merged tickets.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $primary->id,
                    'metadata' => [
                        'primary_ticket_id' => $primary->id,
                        'primary_ticket_number' => $primary->ticket_number,
                        'merged_ticket_ids' => $others->pluck('id')->values()->all(),
                        'merged_ticket_numbers' => $others->pluck('ticket_number')->values()->all(),
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->route('admin.tickets.show', $primary)->with('success', 'Tickets merged successfully.');
        }

        return redirect()->back()->with('error', 'Invalid bulk action.');
    }

    private function assignableAgentRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')->where(function ($query) {
            $query->whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->where('role', '!=', User::ROLE_SHADOW)
                ->where('is_active', true);
        });
    }

    private function applyLifecycleTimestamps(?Ticket $ticket, array &$updateData): void
    {
        $status = $updateData['status'] ?? null;

        if (in_array($status, ['open', 'in_progress', 'pending'], true)) {
            $updateData['resolved_at'] = null;
            $updateData['closed_at'] = null;
            $updateData['super_users_notified_unassigned_sla_at'] = null;
            $updateData['technical_user_notified_sla_at'] = null;

            return;
        }

        if ($status === 'resolved' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }

        if ($status === 'resolved') {
            $updateData['closed_at'] = null;
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->closed_at)) {
            $updateData['closed_at'] = now();
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }
    }

    private function canRunDestructiveAction(): bool
    {
        $user = auth()->user();

        return $user && $user->isAdminLevel();
    }

    private function canDeleteTickets(): bool
    {
        $user = auth()->user();

        return $user && $user->isSuperAdmin();
    }

    private function determineReviewerAssignee(string $nextStatus, ?int $requestedAssignedTo): ?int
    {
        if ($requestedAssignedTo !== null) {
            return $requestedAssignedTo;
        }

        if (! in_array($nextStatus, Ticket::CLOSED_STATUSES, true)) {
            return null;
        }

        $actor = auth()->user();
        if (! $actor || ! $actor->isAdminLevel() || $actor->isShadow()) {
            return null;
        }

        return (int) $actor->id;
    }

    private function assignmentMetadataForChange(?int $previousAssignedTo, ?int $newAssignedTo): array
    {
        if ($previousAssignedTo === $newAssignedTo) {
            return [];
        }

        return [
            'assigned_at' => $newAssignedTo !== null ? now() : null,
            'technical_user_notified_assignment_at' => null,
            'technical_user_notified_sla_at' => null,
            'super_users_notified_unassigned_sla_at' => null,
        ];
    }

    private function recordAssignmentHandoff(Ticket $ticket, ?int $previousAssignedTo, ?int $newAssignedTo): void
    {
        if ($previousAssignedTo === $newAssignedTo) {
            return;
        }

        $actorName = optional(auth()->user())->name ?? 'System';
        $previousAssigneeName = $previousAssignedTo ? optional(User::find($previousAssignedTo))->publicDisplayName() : null;
        $newAssigneeName = $newAssignedTo ? optional(User::find($newAssignedTo))->publicDisplayName() : null;

        $message = match (true) {
            $previousAssignedTo === null && $newAssigneeName !== null => "Ticket was assigned to {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssigneeName !== null => "Ticket handoff: {$previousAssigneeName} -> {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssignedTo === null => "Ticket was unassigned from {$previousAssigneeName} by {$actorName}.",
            default => null,
        };

        if (! $message) {
            return;
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $message.' Previous conversation remains available for continuity.',
            'is_internal' => true,
        ]);
    }

    private function recordStatusClosureReason(Ticket $ticket, string $previousStatus, string $nextStatus, string $closeReason): void
    {
        if ($nextStatus !== 'closed' || $previousStatus === 'closed') {
            return;
        }

        $reason = trim($closeReason);
        if ($reason === '') {
            return;
        }

        $actorName = optional(auth()->user())->name ?? 'System';

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => "Ticket was closed by {$actorName}.\nReason: {$reason}",
            'is_internal' => true,
        ]);
    }

    private function distinctTicketColumnOptions(string $column, ?Builder $scopedBaseQuery = null): \Illuminate\Support\Collection
    {
        $this->assertSupportedLocationColumn($column);

        $query = $scopedBaseQuery ? clone $scopedBaseQuery : Ticket::query();

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();
    }

    private function applyCaseInsensitiveExactMatch(Builder $query, string $column, string $value): void
    {
        $this->assertSupportedLocationColumn($column);

        $normalizedValue = strtolower(trim($value));
        $query->whereRaw("LOWER(COALESCE({$column}, '')) = ?", [$normalizedValue]);
    }

    private function assertSupportedLocationColumn(string $column): void
    {
        if (! in_array($column, ['province', 'municipality'], true)) {
            throw new \InvalidArgumentException('Unsupported ticket location column.');
        }
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $user = auth()->user();

        if ($user && $user->isTechnician() && (int) $ticket->assigned_to !== (int) $user->id) {
            abort(403, 'Technical users can only access tickets assigned to them.');
        }
    }

    private function scopedTicketQueryForCurrentUser(): Builder
    {
        $query = Ticket::query();
        $user = auth()->user();

        if ($user && $user->isTechnician()) {
            $query->where('assigned_to', $user->id);
        }

        return $query;
    }

    private function buildTicketListSnapshotToken(Builder $query): string
    {
        $latestUpdatedAt = (clone $query)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;
        $totalTickets = (clone $query)->count();

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total_tickets' => $totalTickets,
        ]));
    }

    private function activeAssignableAgents(): \Illuminate\Support\Collection
    {
        return Cache::remember('admin_ticket_active_agents_v2', now()->addSeconds(45), function () {
            return User::whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->visibleDirectory()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        });
    }
}
