<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Services\TicketEmailAlertService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class TicketWorkflowService
{
    private const CLOSED_REVERT_WINDOW_DAYS = 7;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private TicketEmailAlertService $ticketEmailAlerts,
        private SystemLogService $systemLogs,
    ) {}

    public function normalizedAssigneeIdsFromRequest(Request $request): array
    {
        return collect($request->input('assigned_to', []))
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function closeStatusGateErrorForCurrentActor(Ticket $ticket): ?string
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

    public function assignTicket(Request $request, Ticket $ticket): bool
    {
        $previousAssignedIds = $ticket->assigned_user_ids;
        $newAssignedIds = $this->normalizedAssigneeIdsFromRequest($request);
        $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

        if ($previousAssignedIds === $newAssignedIds) {
            return false;
        }

        $ticket->update([
            'assigned_to' => $newAssignedTo,
            ...$this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds),
        ]);
        $ticket->assignedUsers()->sync($newAssignedIds);
        $this->trackTicketAcknowledgment($ticket);
        $this->systemLogs->record(
            'ticket.assignment.updated',
            'Updated ticket assignment.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'previous_assigned_to' => $this->primaryAssigneeId($previousAssignedIds),
                    'assigned_to' => $newAssignedTo,
                    'previous_assigned_user_ids' => $previousAssignedIds,
                    'assigned_user_ids' => $newAssignedIds,
                ],
                'request' => $request,
            ]
        );

        $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);

        $newlyAssignedIds = array_values(array_diff($newAssignedIds, $previousAssignedIds));
        if ($newlyAssignedIds !== []) {
            $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment(
                $ticket->fresh(['assignedUser', 'assignedUsers']),
                $newlyAssignedIds
            );
        }

        return true;
    }

    public function updateTicketStatus(Request $request, Ticket $ticket): bool
    {
        $previousStatus = (string) $ticket->status;
        $nextStatus = $request->string('status')->toString();
        $previousAssignedIds = $ticket->assigned_user_ids;
        $newAssignedIds = $this->determineReviewerAssigneeIds($nextStatus, $previousAssignedIds);
        $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

        if ($previousStatus === $nextStatus) {
            return false;
        }

        $updateData = ['status' => $nextStatus];
        if ($previousAssignedIds !== $newAssignedIds) {
            $updateData['assigned_to'] = $newAssignedTo;
            $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
        }
        $this->applyLifecycleTimestamps($ticket, $updateData);

        $ticket->update($updateData);
        if ($previousAssignedIds !== $newAssignedIds) {
            $ticket->assignedUsers()->sync($newAssignedIds);
            $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);
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
        $requestedAssignedIds = $this->normalizedAssigneeIdsFromRequest($request);
        $newAssignedIds = $this->determineReviewerAssigneeIds($nextStatus, $requestedAssignedIds);
        $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);
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
        $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));

        $ticket->update($updateData);
        $ticket->assignedUsers()->sync($newAssignedIds);
        $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);
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
                    'ticket_number' => $ticket->ticket_number,
                    'previous_assigned_to' => $this->primaryAssigneeId($previousAssignedIds),
                    'assigned_to' => $newAssignedTo,
                    'previous_assigned_user_ids' => $previousAssignedIds,
                    'assigned_user_ids' => $newAssignedIds,
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

        $newlyAssignedIds = array_values(array_diff($newAssignedIds, $previousAssignedIds));
        if ($newlyAssignedIds !== []) {
            $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment(
                $ticket->fresh(['assignedUser', 'assignedUsers']),
                $newlyAssignedIds
            );
        }

        return true;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @param  array<int>  $selectedIds
     */
    public function bulkAssignTickets(Request $request, Collection $tickets, array $selectedIds): void
    {
        $newAssignedIds = $this->normalizedAssigneeIdsFromRequest($request);
        $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

        $tickets->each(function (Ticket $ticket) use ($newAssignedIds, $newAssignedTo): void {
            $previousAssignedIds = $ticket->assigned_user_ids;
            $ticket->update([
                'assigned_to' => $newAssignedTo,
                ...$this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds),
            ]);
            $ticket->assignedUsers()->sync($newAssignedIds);
            $this->trackTicketAcknowledgment($ticket);
            $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);

            $newlyAssignedIds = array_values(array_diff($newAssignedIds, $previousAssignedIds));
            if ($newlyAssignedIds !== []) {
                $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment(
                    $ticket->fresh(['assignedUser', 'assignedUsers']),
                    $newlyAssignedIds
                );
            }
        });

        $this->systemLogs->record(
            'ticket.bulk.assign',
            'Assigned tickets in bulk.',
            [
                'category' => 'ticket',
                'metadata' => [
                    'ticket_ids' => $selectedIds,
                    'assigned_to' => $newAssignedTo,
                    'assigned_user_ids' => $newAssignedIds,
                ],
                'request' => $request,
            ]
        );
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
            $newAssignedIds = $this->determineReviewerAssigneeIds($newStatus, $previousAssignedIds);
            $newAssignedTo = $this->primaryAssigneeId($newAssignedIds);

            $updateData = ['status' => $newStatus];
            if ($previousAssignedIds !== $newAssignedIds) {
                $updateData['assigned_to'] = $newAssignedTo;
                $updateData = array_merge($updateData, $this->assignmentMetadataForChange($previousAssignedIds, $newAssignedIds));
            }
            $this->applyLifecycleTimestamps($ticket, $updateData);

            if ($previousStatus === $newStatus && $previousAssignedIds === $newAssignedIds) {
                return;
            }

            $ticket->update($updateData);
            $this->trackTicketAcknowledgment($ticket);

            if ($previousAssignedIds !== $newAssignedIds) {
                $ticket->assignedUsers()->sync($newAssignedIds);
                $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);
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

    private function requiresCloseDelayForCurrentActor(): bool
    {
        $actor = auth()->user();
        if (! $actor) {
            return false;
        }

        return in_array($actor->normalizedRole(), [User::ROLE_TECHNICAL, User::ROLE_SUPER_USER], true);
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

    private function determineReviewerAssigneeIds(string $nextStatus, array $requestedAssignedIds): array
    {
        if ($requestedAssignedIds !== []) {
            return $requestedAssignedIds;
        }

        if (! in_array($nextStatus, Ticket::CLOSED_STATUSES, true)) {
            return [];
        }

        $actor = auth()->user();
        if (! $actor || ! $actor->isAdminLevel() || $actor->isShadow()) {
            return [];
        }

        return [(int) $actor->id];
    }

    private function assignmentMetadataForChange(array $previousAssignedIds, array $newAssignedIds): array
    {
        if ($previousAssignedIds === $newAssignedIds) {
            return [];
        }

        return [
            'assigned_at' => $newAssignedIds !== [] ? now() : null,
            'technical_user_notified_assignment_at' => null,
            'technical_user_notified_sla_at' => null,
            'super_users_notified_unassigned_sla_at' => null,
        ];
    }

    private function recordAssignmentHandoff(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): void
    {
        if ($previousAssignedIds === $newAssignedIds) {
            return;
        }

        $actorName = optional(auth()->user())->name ?? 'System';
        $previousAssigneeName = $this->assigneeDisplayNames($previousAssignedIds);
        $newAssigneeName = $this->assigneeDisplayNames($newAssignedIds);

        $message = match (true) {
            $previousAssignedIds === [] && $newAssigneeName !== null => "Ticket was assigned to {$newAssigneeName} by {$actorName}.",
            $previousAssigneeName !== null && $newAssigneeName !== null => "Ticket assignment updated by {$actorName}. Previous: {$previousAssigneeName}. New: {$newAssigneeName}.",
            $previousAssigneeName !== null && $newAssignedIds === [] => "Ticket was unassigned from {$previousAssigneeName} by {$actorName}.",
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

    private function assigneeDisplayNames(array $userIds): ?string
    {
        if ($userIds === []) {
            return null;
        }

        static $displayNameCache = [];
        $displayNames = [];

        foreach ($userIds as $userId) {
            if (! array_key_exists($userId, $displayNameCache)) {
                $user = User::find($userId);
                $displayNameCache[$userId] = $user && ! $user->isShadow()
                    ? $user->publicDisplayName()
                    : null;
            }

            $displayName = $displayNameCache[$userId];
            if ($displayName) {
                $displayNames[] = $displayName;
            }
        }

        $displayNames = array_values(array_unique($displayNames));

        return $displayNames !== [] ? implode(', ', $displayNames) : null;
    }

    private function primaryAssigneeId(array $assigneeIds): ?int
    {
        return $assigneeIds[0] ?? null;
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
