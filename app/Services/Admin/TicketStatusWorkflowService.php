<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TicketStatusWorkflowService
{
    private const CLOSED_REVERT_WINDOW_DAYS = 7;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private TicketAssignmentService $ticketAssignments,
        private SystemLogService $systemLogs,
    ) {}

    public function trackTicketHandlingAction(Ticket $ticket): void
    {
        $this->trackTicketAcknowledgment($ticket);
    }

    public function reopenClosedStatusGateErrorForTicket(Ticket $ticket, ?string $nextStatus): ?string
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

    public function updateTicketStatus(Request $request, Ticket $ticket): bool
    {
        $previousStatus = (string) $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $previousAssignedIds = $ticket->assigned_user_ids;
        $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($nextStatus, $previousAssignedIds);
        $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);

        if ($previousStatus === $nextStatus) {
            return false;
        }

        $updateData = ['status' => $nextStatus];
        if ($previousAssignedIds !== $newAssignedIds) {
            $updateData['assigned_to'] = $newAssignedTo;
            $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
        }
        $this->applyLifecycleTimestamps($ticket, $updateData);

        $ticket->update($updateData);
        if ($previousAssignedIds !== $newAssignedIds) {
            $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds);
        }
        $this->trackTicketAcknowledgment($ticket);
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

        return true;
    }

    public function quickUpdateTicket(Request $request, Ticket $ticket): bool
    {
        $previousStatus = (string) $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $nextPriority = $request->filled('priority')
            ? Ticket::normalizePriorityValue($request->string('priority')->toString())
            : null;
        $nextTicketType = $request->filled('ticket_type')
            ? Ticket::normalizeTicketTypeValue($request->string('ticket_type')->toString())
            : $ticket->ticket_type;
        $previousAssignedIds = $ticket->assigned_user_ids;
        $requestedAssignedIds = $this->ticketAssignments->normalizedAssigneeIdsFromRequest($request);
        $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($nextStatus, $requestedAssignedIds);
        $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);
        $updateData = [
            'assigned_to' => $newAssignedTo,
            'status' => $nextStatus,
            'priority' => $nextPriority,
            'ticket_type' => $nextTicketType,
        ];
        $previousPriority = $ticket->priority;
        $previousTicketType = $ticket->ticket_type;

        if (
            $previousAssignedIds === $newAssignedIds
            && $previousStatus === $nextStatus
            && $previousPriority === $nextPriority
            && $previousTicketType === $nextTicketType
        ) {
            return false;
        }

        $this->applyLifecycleTimestamps($ticket, $updateData);
        $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));

        $ticket->update($updateData);
        $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);
        $this->trackTicketAcknowledgment($ticket);
        $this->recordStatusClosureReason($ticket, $previousStatus, $nextStatus, $request->string('close_reason')->toString());
        $this->systemLogs->record(
            'ticket.quick_update',
            'Applied quick ticket update.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    ...$this->ticketAssignments->assignmentLogMetadata($ticket, $previousAssignedIds, $newAssignedIds),
                    'previous_status' => $previousStatus,
                    'new_status' => $nextStatus,
                    'previous_priority' => $previousPriority,
                    'new_priority' => $nextPriority,
                    'previous_ticket_type' => $previousTicketType,
                    'new_ticket_type' => $nextTicketType,
                ],
                'request' => $request,
            ]
        );

        return true;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkStatusTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $newStatus = $request->string('status')->toString();
        $closeReason = trim($request->string('close_reason')->toString());

        $tickets->each(function (Ticket $ticket) use ($newStatus, $closeReason): void {
            $previousStatus = (string) $ticket->status;
            $previousAssignedIds = $ticket->assigned_user_ids;
            $newAssignedIds = $this->ticketAssignments->determineReviewerAssigneeIds($newStatus, $previousAssignedIds);
            $newAssignedTo = $this->ticketAssignments->primaryAssigneeId($newAssignedIds);

            $updateData = ['status' => $newStatus];
            if ($previousAssignedIds !== $newAssignedIds) {
                $updateData['assigned_to'] = $newAssignedTo;
                $updateData = array_merge($updateData, $this->ticketAssignments->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
            }
            $this->applyLifecycleTimestamps($ticket, $updateData);

            if ($previousStatus === $newStatus && $previousAssignedIds === $newAssignedIds) {
                return;
            }

            $ticket->update($updateData);
            $this->trackTicketAcknowledgment($ticket);

            if ($previousAssignedIds !== $newAssignedIds) {
                $this->ticketAssignments->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds);
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
                    'ticket_ids' => $selectedIds,
                    'status' => $newStatus,
                ],
                'request' => $request,
            ]
        );
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkPriorityTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $priority = Ticket::normalizePriorityValue($request->string('priority')->toString());

        Ticket::whereIn('id', $selectedIds)->update(['priority' => $priority]);
        $tickets->each(fn (Ticket $ticket) => $this->trackTicketAcknowledgment($ticket));

        $this->systemLogs->record(
            'ticket.bulk.priority',
            'Updated ticket priorities in bulk.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'ticket_ids' => $selectedIds,
                    'priority' => $priority,
                ],
                'request' => $request,
            ]
        );
    }

    private function trackTicketAcknowledgment(Ticket $ticket): void
    {
        $actor = auth()->user();
        $this->ticketAcknowledgments->trackHandlingAction($ticket, $actor);
    }

    private function applyLifecycleTimestamps(?Ticket $ticket, array &$updateData): void
    {
        $status = $updateData['status'] ?? null;

        if (in_array($status, ['open', 'in_progress', 'pending'], true)) {
            $updateData = array_merge($updateData, Ticket::reopenedLifecycleResetAttributes());

            return;
        }

        if ($status === 'resolved' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }

        if ($status === 'resolved') {
            $updateData['closed_at'] = null;
            $updateData['closed_by'] = null;
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->closed_at)) {
            $updateData['closed_at'] = now();
            $updateData['closed_by'] = auth()->id();
        }

        if ($status === 'closed' && (! $ticket || ! $ticket->resolved_at)) {
            $updateData['resolved_at'] = now();
        }
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
}
