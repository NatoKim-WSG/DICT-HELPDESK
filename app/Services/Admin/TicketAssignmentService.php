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

class TicketAssignmentService
{
    /**
     * @var array<int, string|null>
     */
    private array $assigneeDisplayNameCache = [];

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
        $this->trackTicketAcknowledgment($ticket);
        $this->systemLogs->record(
            'ticket.assignment.updated',
            'Updated ticket assignment.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => $this->assignmentLogMetadata($ticket, $previousAssignedIds, $newAssignedIds),
                'request' => $request,
            ]
        );

        $this->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);

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
            $this->trackTicketAcknowledgment($ticket);
            $this->syncAssignmentState($ticket, $previousAssignedIds, $newAssignedIds, notifyNewAssignees: true);
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

    public function determineReviewerAssigneeIds(string $nextStatus, array $requestedAssignedIds): array
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

    public function assignmentMetadataForChange(array $previousAssignedIds, array $newAssignedIds): array
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

    public function assignmentLogMetadata(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'previous_assigned_to' => $this->primaryAssigneeId($previousAssignedIds),
            'assigned_to' => $this->primaryAssigneeId($newAssignedIds),
            'previous_assigned_user_ids' => $previousAssignedIds,
            'assigned_user_ids' => $newAssignedIds,
        ];
    }

    public function syncAssignmentState(
        Ticket $ticket,
        array $previousAssignedIds,
        array $newAssignedIds,
        bool $notifyNewAssignees = false
    ): void {
        if ($previousAssignedIds === $newAssignedIds) {
            return;
        }

        $ticket->assignedUsers()->sync($newAssignedIds);
        $this->recordAssignmentHandoff($ticket, $previousAssignedIds, $newAssignedIds);

        if ($notifyNewAssignees) {
            $this->notifyNewlyAssignedUsers($ticket, $previousAssignedIds, $newAssignedIds);
        }
    }

    public function primaryAssigneeId(array $assigneeIds): ?int
    {
        return $assigneeIds[0] ?? null;
    }

    private function trackTicketAcknowledgment(Ticket $ticket): void
    {
        $actor = auth()->user();
        $this->ticketAcknowledgments->trackHandlingAction($ticket, $actor);
    }

    private function notifyNewlyAssignedUsers(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): void
    {
        $newlyAssignedIds = array_values(array_diff($newAssignedIds, $previousAssignedIds));
        if ($newlyAssignedIds === []) {
            return;
        }

        $this->ticketEmailAlerts->notifyTechnicalAssigneeAboutAssignment(
            $ticket->fresh(['assignedUser', 'assignedUsers']),
            $newlyAssignedIds
        );
    }

    private function recordAssignmentHandoff(Ticket $ticket, array $previousAssignedIds, array $newAssignedIds): void
    {
        if ($previousAssignedIds === $newAssignedIds) {
            return;
        }

        $this->primeAssigneeDisplayNames(array_values(array_unique([
            ...$previousAssignedIds,
            ...$newAssignedIds,
        ])));

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

        $this->primeAssigneeDisplayNames($userIds);
        $displayNames = [];

        foreach ($userIds as $userId) {
            $displayName = $this->assigneeDisplayNameCache[$userId] ?? null;
            if ($displayName) {
                $displayNames[] = $displayName;
            }
        }

        $displayNames = array_values(array_unique($displayNames));

        return $displayNames !== [] ? implode(', ', $displayNames) : null;
    }

    private function primeAssigneeDisplayNames(array $userIds): void
    {
        $missingUserIds = array_values(array_filter(
            $userIds,
            fn (int $userId): bool => ! array_key_exists($userId, $this->assigneeDisplayNameCache)
        ));

        if ($missingUserIds === []) {
            return;
        }

        $usersById = User::query()
            ->whereIn('id', $missingUserIds)
            ->get()
            ->keyBy('id');

        foreach ($missingUserIds as $userId) {
            /** @var User|null $user */
            $user = $usersById->get($userId);
            $this->assigneeDisplayNameCache[$userId] = $user && ! $user->isShadow()
                ? $user->publicDisplayName()
                : null;
        }
    }
}
