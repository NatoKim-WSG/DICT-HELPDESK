<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminReturnToRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\BulkTicketActionRequest;
use App\Models\Ticket;
use App\Services\Admin\TicketAssignmentService;
use App\Services\Admin\TicketIndexService;
use App\Services\Admin\TicketMutationService;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\SystemLogService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TicketBulkActionController extends Controller
{
    use HandlesAdminReturnToRedirects;

    public function __construct(
        private SystemLogService $systemLogs,
        private TicketIndexService $ticketIndex,
        private TicketMutationService $ticketMutations,
        private TicketAssignmentService $ticketAssignments,
        private TicketStatusWorkflowService $ticketStatusWorkflow,
    ) {}

    public function __invoke(BulkTicketActionRequest $request)
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
        $newAssignedIds = $this->ticketAssignments->normalizedAssigneeIdsFromRequest($request);
        if ($newAssignedIds === []) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a technical user.');
        }

        $this->ticketAssignments->bulkAssignTickets($request, $tickets, $selectedIds->all());

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
            $gateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($candidateTicket, $newStatus);

            if ($gateError !== null) {
                return $this->redirectBackOrReturnTo($request)->with('error', $gateError);
            }
        }

        $this->ticketStatusWorkflow->bulkStatusTickets($request, $tickets, $selectedIds->all());

        return $this->redirectBackOrReturnTo($request)->with('success', 'Selected ticket statuses updated.');
    }

    private function handleBulkPriority(BulkTicketActionRequest $request, EloquentCollection $tickets, Collection $selectedIds)
    {
        if (! $request->filled('priority')) {
            return $this->redirectBackOrReturnTo($request)->with('error', 'Please choose a severity.');
        }

        $this->ticketStatusWorkflow->bulkPriorityTickets($request, $tickets, $selectedIds->all());

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
