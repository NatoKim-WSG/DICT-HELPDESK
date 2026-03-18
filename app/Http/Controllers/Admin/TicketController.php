<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\AssignTicketRequest;
use App\Http\Requests\Admin\Tickets\BulkTicketActionRequest;
use App\Http\Requests\Admin\Tickets\QuickUpdateTicketRequest;
use App\Http\Requests\Admin\Tickets\SetTicketDueDateRequest;
use App\Http\Requests\Admin\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketPriorityRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketStatusRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use App\Services\Admin\TicketIndexService;
use App\Services\Admin\TicketMutationService;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    private const CLOSED_REVERT_WINDOW_DAYS = 7;

    public function __construct(
        private TicketEmailAlertService $ticketEmailAlerts,
        private SystemLogService $systemLogs,
        private TicketMutationService $ticketMutations,
        private TicketIndexService $ticketIndex,
    ) {}

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString());
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus($request->string('status')->toString(), $activeTab);
        $createdDateRange = $this->ticketIndex->resolveCreatedDateRange($request);

        $query = $this->ticketIndex->scopedTicketQueryFor($currentUser)
            ->with(['user', 'category', 'assignedUser']);
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFilters($query, $request, $selectedStatus, $createdDateRange);

        $liveSnapshotToken = $this->ticketIndex->buildTicketListSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $scopedTickets = $this->ticketIndex->scopedTicketQueryFor($currentUser);
        $provinceOptions = $this->ticketIndex->distinctTicketColumnOptions('province', clone $scopedTickets);
        $municipalityOptions = $this->ticketIndex->distinctTicketColumnOptions('municipality', clone $scopedTickets);

        $accountOptions = $this->ticketIndex->accountOptionsFor($currentUser, clone $scopedTickets);

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
        $assignees = $this->ticketIndex->activeAssignableAgents();

        return view('admin.tickets.index', compact(
            'tickets',
            'categories',
            'assignees',
            'provinceOptions',
            'municipalityOptions',
            'accountOptions',
            'activeTab',
            'liveSnapshotToken',
            'ticketSeenTimestamps',
            'createdDateRange'
        ));
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $ticket->load(['user', 'category', 'assignedUser', 'replies.user', 'replies.attachments', 'replies.replyTo', 'attachments']);
        $assignees = $this->ticketIndex->activeAssignableAgents();

        return view('admin.tickets.show', compact('ticket', 'assignees'));
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        /** @var \Illuminate\Database\Eloquent\Collection<int, TicketReply> $ticketReplies */
        $ticketReplies = $ticket->replies()
            ->with(['user', 'attachments', 'replyTo'])
            ->orderBy('created_at')
            ->get();

        $replies = $ticketReplies
            ->map(fn (TicketReply $reply) => $this->formatReplyForChat($reply))
            ->values();

        return response()->json([
            'replies' => $replies,
        ]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $request->filled('assigned_to') ? $request->integer('assigned_to') : null;

        if ($previousAssignedTo === $newAssignedTo) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
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

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket assignment updated successfully!');
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $previousStatus = $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $newAssignedTo = $this->determineReviewerAssignee($nextStatus, $previousAssignedTo);

        if ($previousStatus === $nextStatus) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $reopenGateError = $this->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        if ($nextStatus === 'closed' && $previousStatus !== 'closed') {
            $closeGateError = $this->closeStatusGateErrorForTicket($ticket);
            if ($closeGateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $closeGateError);
            }
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

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket status updated successfully!');
    }

    public function updatePriority(UpdateTicketPriorityRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        if ($ticket->priority === $request->priority) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
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

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket priority updated successfully!');
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

    public function setDueDate(SetTicketDueDateRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $incomingDueDateLabel = \Illuminate\Support\Carbon::parse($request->due_date)->format('Y-m-d H:i');
        $existingDueDateLabel = optional($ticket->due_date)->format('Y-m-d H:i');
        if ($existingDueDateLabel === $incomingDueDateLabel) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
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

        return $this->redirectBackOrReturnTo($request)->with('success', 'Due date set successfully!');
    }

    public function quickUpdate(QuickUpdateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

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
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $reopenGateError = $this->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        if ($nextStatus === 'closed' && $previousStatus !== 'closed') {
            $closeGateError = $this->closeStatusGateErrorForTicket($ticket);
            if ($closeGateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $closeGateError);
            }
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

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket updated successfully.');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        if (! $this->canDeleteTickets()) {
            abort(403, 'Only admins can delete tickets.');
        }

        $ticketNumber = $ticket->ticket_number;
        $ticketId = $ticket->id;

        DB::transaction(function () use ($ticket) {
            $this->ticketMutations->deleteTicketWithRelations($ticket);
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

        $returnPath = $this->returnPathFromRequest($request);
        if ($returnPath !== null) {
            return redirect()->to($returnPath)->with('success', 'Ticket deleted successfully.');
        }

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket deleted successfully.');
    }

    public function bulkAction(BulkTicketActionRequest $request)
    {
        $selectedIds = collect($request->input('selected_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'No tickets selected.');
        }

        $action = $request->string('action')->toString();
        /** @var \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets */
        $tickets = $this->ticketIndex->scopedTicketQueryFor(auth()->user())
            ->whereIn('id', $selectedIds)
            ->get();

        if ($tickets->isEmpty()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Selected tickets were not found.');
        }

        if ($tickets->count() !== $selectedIds->count()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'One or more selected tickets are not accessible to your account.');
        }

        if ($action === 'delete' && ! $this->canDeleteTickets()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Only admins can run delete actions.');
        }

        if ($action === 'merge' && ! $this->canRunDestructiveAction()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Only super users or admins can run merge actions.');
        }

        if ($action === 'delete') {
            $ticketNumbers = $tickets->pluck('ticket_number')->values()->all();
            DB::transaction(function () use ($tickets) {
                $this->ticketMutations->deleteManyTicketsWithRelations($tickets);
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

            return $this->redirectBackOrReturnTo($request)->with('success', 'Selected tickets deleted successfully.');
        }

        if ($action === 'assign') {
            if (! $request->filled('assigned_to')) {
                return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a technical user.');
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

            return $this->redirectBackOrReturnTo($request)->with('success', 'Selected tickets assigned successfully.');
        }

        if ($action === 'status') {
            if (! $request->filled('status')) {
                return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a status.');
            }

            $newStatus = $request->string('status')->toString();
            $closeReason = trim($request->string('close_reason')->toString());
            if ($newStatus === 'closed' && $closeReason === '') {
                return $this->redirectBackOrReturnTo($request)->with('error', 'Please provide a reason before closing ticket(s).');
            }
            if ($newStatus === 'closed') {
                foreach ($tickets as $candidateTicket) {
                    /** @var Ticket $candidateTicket */
                    $closeGateError = $this->closeStatusGateErrorForTicket($candidateTicket);
                    if ($closeGateError !== null) {
                        return $this->redirectBackOrReturnTo($request)->with('error', $closeGateError);
                    }
                }
            } else {
                foreach ($tickets as $candidateTicket) {
                    /** @var Ticket $candidateTicket */
                    $reopenGateError = $this->reopenClosedStatusGateErrorForTicket($candidateTicket, $newStatus);
                    if ($reopenGateError !== null) {
                        return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
                    }
                }
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

            return $this->redirectBackOrReturnTo($request)->with('success', 'Selected ticket statuses updated.');
        }

        if ($action === 'priority') {
            if (! $request->filled('priority')) {
                return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a priority.');
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

            return $this->redirectBackOrReturnTo($request)->with('success', 'Selected ticket priorities updated.');
        }

        if ($action === 'merge') {
            if ($selectedIds->count() < 2) {
                return $this->redirectBackOrReturnTo($request)->with('error', 'Select at least two tickets to merge.');
            }

            $orderedTickets = Ticket::whereIn('id', $selectedIds)->orderBy('created_at')->get();
            $primary = $orderedTickets->first();
            $others = $orderedTickets->slice(1);

            DB::transaction(function () use ($primary, $others) {
                $this->ticketMutations->mergeTickets($primary, $others, auth()->id());
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

        return $this->redirectBackOrReturnTo($request)->with('error', 'Invalid bulk action.');
    }

    private function closeStatusGateErrorForTicket(Ticket $ticket): ?string
    {
        if (! $this->requiresCloseDelayForCurrentActor()) {
            return null;
        }

        if (! $ticket->resolved_at) {
            return "Ticket {$ticket->ticket_number} must be resolved first. Super users and technical users can close tickets only after 24 hours from resolution.";
        }

        $closeAvailableAt = $ticket->resolved_at->copy()->addDay();
        if (now()->lt($closeAvailableAt)) {
            return "Ticket {$ticket->ticket_number} can be closed on ".$closeAvailableAt->format('M j, Y \\a\\t g:i A').'.';
        }

        return null;
    }

    private function reopenClosedStatusGateErrorForTicket(Ticket $ticket, ?string $nextStatus): ?string
    {
        if ($ticket->status !== 'closed' || $nextStatus === null || $nextStatus === 'closed') {
            return null;
        }

        if (! $ticket->closed_at) {
            return null;
        }

        $reopenDeadline = $ticket->closed_at->copy()->addDays(self::CLOSED_REVERT_WINDOW_DAYS);
        if (now()->gt($reopenDeadline)) {
            return "Ticket {$ticket->ticket_number} can no longer be reverted because it was closed more than "
                .self::CLOSED_REVERT_WINDOW_DAYS
                .' days ago.';
        }

        return null;
    }

    private function requiresCloseDelayForCurrentActor(): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        return in_array($actor->normalizedRole(), [User::ROLE_TECHNICAL, User::ROLE_SUPER_USER], true);
    }

    private function returnPathFromRequest(Request $request): ?string
    {
        $returnTo = trim($request->string('return_to')->toString());
        if ($returnTo === '' || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return null;
        }

        return $returnTo;
    }

    private function redirectBackOrReturnTo(Request $request)
    {
        $returnPath = $this->returnPathFromRequest($request);
        if ($returnPath !== null) {
            return redirect()->to($returnPath);
        }

        return redirect()->back();
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
        $previousAssigneeName = $this->assigneeDisplayName($previousAssignedTo);
        $newAssigneeName = $this->assigneeDisplayName($newAssignedTo);

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

    private function assigneeDisplayName(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        static $displayNameCache = [];
        if (array_key_exists($userId, $displayNameCache)) {
            return $displayNameCache[$userId];
        }

        $displayNameCache[$userId] = optional(User::find($userId))->publicDisplayName();

        return $displayNameCache[$userId];
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

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }
}
