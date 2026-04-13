<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TicketWorkflowService
{
    public function __construct(
        private TicketAssignmentService $ticketAssignments,
        private TicketStatusWorkflowService $ticketStatusWorkflow,
    ) {}

    public function normalizedAssigneeIdsFromRequest(Request $request): array
    {
        return $this->ticketAssignments->normalizedAssigneeIdsFromRequest($request);
    }

    public function trackTicketHandlingAction(Ticket $ticket): void
    {
        $this->ticketStatusWorkflow->trackTicketHandlingAction($ticket);
    }

    public function reopenClosedStatusGateErrorForTicket(Ticket $ticket, ?string $nextStatus): ?string
    {
        return $this->ticketStatusWorkflow->reopenClosedStatusGateErrorForTicket($ticket, $nextStatus);
    }

    public function assignTicket(Request $request, Ticket $ticket): bool
    {
        return $this->ticketAssignments->assignTicket($request, $ticket);
    }

    public function updateTicketStatus(Request $request, Ticket $ticket): bool
    {
        return $this->ticketStatusWorkflow->updateTicketStatus($request, $ticket);
    }

    public function quickUpdateTicket(Request $request, Ticket $ticket): bool
    {
        return $this->ticketStatusWorkflow->quickUpdateTicket($request, $ticket);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkAssignTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $this->ticketAssignments->bulkAssignTickets($request, $tickets, $selectedIds);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkStatusTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $this->ticketStatusWorkflow->bulkStatusTickets($request, $tickets, $selectedIds);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkPriorityTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $this->ticketStatusWorkflow->bulkPriorityTickets($request, $tickets, $selectedIds);
    }
}
