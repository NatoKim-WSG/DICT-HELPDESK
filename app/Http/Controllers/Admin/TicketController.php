<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\AssignTicketRequest;
use App\Http\Requests\Admin\Tickets\BulkTicketActionRequest;
use App\Http\Requests\Admin\Tickets\QuickUpdateTicketRequest;
use App\Http\Requests\Admin\Tickets\StoreTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\StoreTicketRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketReplyRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketSeverityRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketStatusRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketTypeRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use App\Services\Admin\TicketIndexService;
use App\Services\Admin\TicketMutationService;
use App\Services\Admin\TicketWorkflowService;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Support\LeadingUppercaseNormalizer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private SystemLogService $systemLogs,
        private TicketMutationService $ticketMutations,
        private TicketIndexService $ticketIndex,
        private TicketWorkflowService $ticketWorkflow,
    ) {}

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString());
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus($request->string('status')->toString(), $activeTab);
        $createdDateRange = $this->ticketIndex->resolveCreatedDateRange($request);

        $query = $this->ticketIndex->scopedTicketQueryFor($currentUser)
            ->with(['user', 'category', 'assignedUser', 'assignedUsers', 'closedBy']);
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFilters($query, $request, $selectedStatus, $createdDateRange);

        $liveSnapshotToken = $this->ticketIndex->buildTicketListSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
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

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.tickets.partials.results', compact(
                    'tickets',
                    'ticketSeenTimestamps',
                    'createdDateRange',
                    'activeTab'
                ))->render(),
                'token' => $liveSnapshotToken,
            ]);
        }

        $scopedTickets = $this->ticketIndex->scopedTicketQueryFor($currentUser);
        $provinceOptions = $this->ticketIndex->distinctTicketColumnOptions('province', clone $scopedTickets);
        $municipalityOptions = $this->ticketIndex->distinctTicketColumnOptions('municipality', clone $scopedTickets);

        $accountOptions = $this->ticketIndex->accountOptionsFor($currentUser, clone $scopedTickets);
        $monthOptions = $this->ticketIndex->monthOptionsFor(clone $scopedTickets);

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
            'monthOptions',
            'activeTab',
            'liveSnapshotToken',
            'ticketSeenTimestamps',
            'createdDateRange'
        ));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canCreateClientTickets(), 403);

        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name']);
        $clientAccounts = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'department']);

        return view('admin.tickets.create', compact('categories', 'clientAccounts'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = Ticket::create([
            'name' => $request->string('name')->toString(),
            'contact_number' => $request->string('contact_number')->toString(),
            'email' => $request->string('email')->toString(),
            'province' => LeadingUppercaseNormalizer::normalize($request->string('province')->toString()),
            'municipality' => LeadingUppercaseNormalizer::normalize($request->string('municipality')->toString()),
            'subject' => LeadingUppercaseNormalizer::normalize($request->string('subject')->toString()),
            'description' => $request->string('description')->toString(),
            'category_id' => $request->integer('category_id'),
            'ticket_type' => $request->string('ticket_type')->toString(),
            'priority' => null,
            'status' => 'open',
            'user_id' => $request->integer('user_id'),
        ]);

        $this->persistAttachmentsFromRequest($request, $ticket);
        $this->ticketAcknowledgments->acknowledge($ticket, auth()->user());
        $this->systemLogs->record(
            'ticket.created_by_super_user',
            'Created a ticket on behalf of a client.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_type' => $ticket->ticket_type,
                    'client_user_id' => (int) $ticket->user_id,
                    'category_id' => (int) $ticket->category_id,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket);
        $assignees = $this->ticketIndex->activeAssignableAgents();
        $currentUserState = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', (int) auth()->id())
            ->first();

        return view('admin.tickets.show', compact('ticket', 'assignees', 'currentUserState'));
    }

    public function acknowledge(Request $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $actor = auth()->user();
        if (! $this->ticketAcknowledgments->canAcknowledge($actor)) {
            abort(403);
        }

        $existingState = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', (int) auth()->id())
            ->first();

        if (! optional($existingState)->acknowledged_at) {
            $state = $this->ticketAcknowledgments->acknowledge($ticket, $actor);
            $this->systemLogs->record(
                'ticket.acknowledged',
                'Acknowledged a ticket for SLA tracking.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'acknowledged_at' => optional($state)->acknowledged_at?->toIso8601String(),
                    ],
                    'request' => $request,
                ]
            );
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket acknowledged.');
    }

    public function replies(Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        /** @var EloquentCollection<int, TicketReply> $ticketReplies */
        $ticketReplies = $this->visibleRepliesRelationForTicket($ticket);

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

        if (! $this->ticketWorkflow->assignTicket($request, $ticket)) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket assignment updated successfully!');
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $nextStatus = $request->string('status')->toString();
        if ((string) $ticket->status === $nextStatus) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $reopenGateError = $this->ticketWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        if ($nextStatus === 'closed' && (string) $ticket->status !== 'closed') {
            $closeGateError = $this->ticketWorkflow->closeStatusGateErrorForCurrentActor($ticket);
            if ($closeGateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $closeGateError);
            }
        }

        $this->ticketWorkflow->updateTicketStatus($request, $ticket);

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket status updated successfully!');
    }

    public function updateSeverity(UpdateTicketSeverityRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        if ($ticket->priority === $request->severity) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousSeverity = $ticket->priority;
        $ticket->update(['priority' => $request->severity]);
        $this->ticketWorkflow->trackTicketHandlingAction($ticket);
        $this->systemLogs->record(
            'ticket.severity.updated',
            'Updated ticket severity.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_severity' => $previousSeverity,
                    'new_severity' => $request->string('severity')->toString(),
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket severity updated successfully!');
    }

    public function updateType(UpdateTicketTypeRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $nextType = $request->string('ticket_type')->toString();
        if ($ticket->ticket_type === $nextType) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
        }

        $previousType = $ticket->ticket_type;
        $ticket->update([
            'ticket_type' => $nextType,
        ]);
        $this->ticketWorkflow->trackTicketHandlingAction($ticket);
        $this->systemLogs->record(
            'ticket.type.updated',
            'Updated ticket type.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_ticket_type' => $previousType,
                    'new_ticket_type' => $nextType,
                ],
                'request' => $request,
            ]
        );

        return $this->redirectBackOrReturnTo($request)->with('success', 'Ticket type updated successfully!');
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
        $this->ticketWorkflow->trackTicketHandlingAction($ticket);

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

    public function quickUpdate(QuickUpdateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $nextStatus = $request->string('status')->toString();

        $reopenGateError = $this->ticketWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        if ($nextStatus === 'closed' && (string) $ticket->status !== 'closed') {
            $closeGateError = $this->ticketWorkflow->closeStatusGateErrorForCurrentActor($ticket);
            if ($closeGateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $closeGateError);
            }
        }

        if (! $this->ticketWorkflow->quickUpdateTicket($request, $ticket)) {
            return $this->redirectBackOrReturnTo($request)->with('success', 'No changes were detected.');
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
        $selectedIds = $this->selectedTicketIdsFromRequest($request);

        if ($selectedIds->isEmpty()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'No tickets selected.');
        }

        $action = $request->string('action')->toString();
        /** @var EloquentCollection<int, Ticket> $tickets */
        $tickets = $this->ticketIndex->scopedTicketQueryFor(auth()->user())
            ->whereIn('id', $selectedIds)
            ->get();

        $accessValidationError = $this->validateBulkTicketAccess($selectedIds, $tickets);
        if ($accessValidationError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $accessValidationError);
        }

        return match ($action) {
            'delete' => $this->handleBulkDelete($request, $tickets, $selectedIds),
            'assign' => $this->handleBulkAssign($request, $tickets, $selectedIds),
            'status' => $this->handleBulkStatus($request, $tickets, $selectedIds),
            'priority' => $this->handleBulkPriority($request, $tickets, $selectedIds),
            'merge' => $this->handleBulkMerge($request, $tickets, $selectedIds),
            default => $this->redirectBackOrReturnTo($request)->with('error', 'Invalid bulk action.'),
        };
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

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }

    private function selectedTicketIdsFromRequest(BulkTicketActionRequest $request): Collection
    {
        return collect($request->input('selected_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function validateBulkTicketAccess(Collection $selectedIds, EloquentCollection $tickets): ?string
    {
        if ($tickets->isEmpty()) {
            return 'Selected tickets were not found.';
        }

        if ($tickets->count() !== $selectedIds->count()) {
            return 'One or more selected tickets are not accessible to your account.';
        }

        return null;
    }

    private function handleBulkDelete(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        if (! $this->canDeleteTickets()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Only admins can run delete actions.');
        }

        $ticketNumbers = $tickets->pluck('ticket_number')->values()->all();
        /** @var array<int, Ticket> $ticketsForDeletion */
        $ticketsForDeletion = $tickets->all();
        DB::transaction(function () use ($ticketsForDeletion) {
            $this->ticketMutations->deleteManyTicketsWithRelations($ticketsForDeletion);
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

    private function handleBulkAssign(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        $newAssignedIds = $this->ticketWorkflow->normalizedAssigneeIdsFromRequest($request);
        if ($newAssignedIds === []) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a technical user.');
        }

        $this->ticketWorkflow->bulkAssignTickets($request, $tickets, $selectedIds->all());

        return $this->redirectBackOrReturnTo($request)->with('success', 'Selected tickets assigned successfully.');
    }

    private function handleBulkStatus(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        if (! $request->filled('status')) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a status.');
        }

        $newStatus = $request->string('status')->toString();
        $closeReason = trim($request->string('close_reason')->toString());
        if ($newStatus === 'closed' && $closeReason === '') {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please provide a reason before closing ticket(s).');
        }

        foreach ($tickets as $candidateTicket) {
            /** @var Ticket $candidateTicket */
            $gateError = $newStatus === 'closed'
                ? $this->ticketWorkflow->closeStatusGateErrorForCurrentActor($candidateTicket)
                : $this->ticketWorkflow->reopenClosedStatusGateErrorForTicket($candidateTicket, $newStatus);

            if ($gateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $gateError);
            }
        }

        $this->ticketWorkflow->bulkStatusTickets($request, $tickets, $selectedIds->all());

        return $this->redirectBackOrReturnTo($request)->with('success', 'Selected ticket statuses updated.');
    }

    private function handleBulkPriority(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        if (! $request->filled('priority')) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a severity.');
        }

        $this->ticketWorkflow->bulkPriorityTickets($request, $tickets, $selectedIds->all());

        return $this->redirectBackOrReturnTo($request)->with('success', 'Selected ticket severities updated.');
    }

    private function handleBulkMerge(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        if (! $this->canRunDestructiveAction()) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Only super users or admins can run merge actions.');
        }

        if ($selectedIds->count() < 2) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Select at least two tickets to merge.');
        }

        /** @var EloquentCollection<int, Ticket> $orderedTickets */
        $orderedTickets = $tickets->sortBy('created_at')->values();
        /** @var Ticket $primary */
        $primary = $orderedTickets->first();
        /** @var EloquentCollection<int, Ticket> $others */
        $others = $orderedTickets->slice(1)->values();

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
}
