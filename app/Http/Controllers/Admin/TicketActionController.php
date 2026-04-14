<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminReturnToRedirects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\AssignTicketRequest;
use App\Http\Requests\Admin\Tickets\QuickUpdateTicketRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketSeverityRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketStatusRequest;
use App\Http\Requests\Admin\Tickets\UpdateTicketTypeRequest;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Services\Admin\TicketAssignmentService;
use App\Services\Admin\TicketMutationService;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketActionController extends Controller
{
    use HandlesAdminReturnToRedirects;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private SystemLogService $systemLogs,
        private TicketMutationService $ticketMutations,
        private TicketAssignmentService $ticketAssignments,
        private TicketStatusWorkflowService $ticketStatusWorkflow,
    ) {}

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

    public function assign(AssignTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        if (! $this->ticketAssignments->assignTicket($request, $ticket)) {
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

        $reopenGateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        $this->ticketStatusWorkflow->updateTicketStatus($request, $ticket);

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
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
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
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
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

    public function quickUpdate(QuickUpdateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        $nextStatus = $request->string('status')->toString();

        $reopenGateError = $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
        if ($reopenGateError !== null) {
            return $this->redirectBackOrReturnTo($request)->with('error', $reopenGateError);
        }

        if (! $this->ticketStatusWorkflow->quickUpdateTicket($request, $ticket)) {
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

    private function canDeleteTickets(): bool
    {
        $user = auth()->user();

        return $user && $user->isSuperAdmin();
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }
}
