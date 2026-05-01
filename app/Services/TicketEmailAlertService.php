<?php

namespace App\Services;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketEmailAlertService
{
    public function notifyAssignedSupportUsersAboutAssignment(Ticket $ticket, ?array $assigneeIds = null): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        $assignees = $this->activeTechnicalRecipientsForTicket($ticket, $assigneeIds);

        if ($assignees->isEmpty()) {
            return false;
        }

        $sentCount = $this->sendAlertToUsers(
            $assignees,
            $ticket,
            'Ticket Assigned: '.$ticket->ticket_number,
            'A ticket was assigned to you.',
            'Please review and start progress before the 4-hour resolution target.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Severity' => $ticket->priority_label,
                'Assigned By' => optional(auth()->user())->name ?? 'System',
            ]
        );

        if ($sentCount > 0) {
            $ticket->forceFill([
                'technical_user_notified_assignment_at' => now(),
            ])->save();

            return true;
        }

        return false;
    }

    public function sendScheduledReminders(): array
    {
        return [
            'assigned_sla_for_technical_users' => $this->sendAssignedTechnicalSlaReminders(),
        ];
    }

    private function sendAssignedTechnicalSlaReminders(): int
    {
        $cutoff = now()->subHours(3)->subMinutes(30);

        $tickets = Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', $cutoff)
            ->whereNull('technical_user_notified_sla_at');
        Ticket::applyAssignedConstraint($tickets);

        $tickets = $tickets
            ->with('assignedUsers:id,name,email,role,is_active')
            ->get();

        $notifiedTickets = 0;

        foreach ($tickets as $ticket) {
            $assignees = $this->activeTechnicalRecipientsForTicket($ticket);

            if ($assignees->isEmpty()) {
                continue;
            }

            $sentCount = $this->sendAlertToUsers(
                $assignees,
                $ticket,
                '4-Hour SLA Reminder (Assigned): '.$ticket->ticket_number,
                'This assigned ticket is nearing the 4-hour resolution target.',
                '3 hours and 30 minutes have passed since assignment and the ticket is still unresolved.',
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Severity' => $ticket->priority_label,
                    'Assigned At' => optional($ticket->assigned_at)->format('Y-m-d H:i:s'),
                ]
            );

            if ($sentCount > 0) {
                $ticket->forceFill([
                    'technical_user_notified_sla_at' => now(),
                ])->save();
                $notifiedTickets++;
            }
        }

        return $notifiedTickets;
    }

    private function activeTechnicalRecipientsForTicket(Ticket $ticket, ?array $assigneeIds = null): Collection
    {
        $normalizedAssigneeIds = $assigneeIds !== null
            ? Ticket::normalizeAssignedUserIds($assigneeIds)
            : null;

        if ($ticket->relationLoaded('assignedUsers')) {
            $assignedUsers = $ticket->assignedUsers
                ->filter(function (User $user) use ($normalizedAssigneeIds): bool {
                    if ($user->normalizedRole() !== User::ROLE_TECHNICAL) {
                        return false;
                    }

                    if (! $user->is_active || ! is_string($user->email) || trim($user->email) === '') {
                        return false;
                    }

                    if ($normalizedAssigneeIds === null) {
                        return true;
                    }

                    return in_array((int) $user->id, $normalizedAssigneeIds, true);
                })
                ->values();

            if ($ticket->assigned_to) {
                $primaryAssignedUser = $assignedUsers->first(fn (User $user) => (int) $user->id === (int) $ticket->assigned_to);
                if ($primaryAssignedUser) {
                    return $assignedUsers
                        ->sortBy(fn (User $user) => (int) $user->id === (int) $primaryAssignedUser->id ? 0 : 1)
                        ->values();
                }
            }

            return $assignedUsers;
        }

        $assignedUserIds = collect($ticket->assigned_user_ids)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0);

        if ($normalizedAssigneeIds !== null) {
            $assignedUserIds = $assignedUserIds->intersect($normalizedAssigneeIds);
        }

        $assignedUserIds = $assignedUserIds->unique()->values();
        if ($assignedUserIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $assignedUserIds->all())
            ->where('role', User::ROLE_TECHNICAL)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'role', 'is_active'])
            ->values();
    }

    private function sendAlertToUsers(
        Collection $recipients,
        Ticket $ticket,
        string $subject,
        string $headline,
        string $messageLine,
        array $details = []
    ): int {
        $sentCount = 0;

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->queue(new TicketAlertMail(
                    ticket: $ticket,
                    subjectLine: $subject,
                    headline: $headline,
                    messageLine: $messageLine,
                    details: $details,
                    actionUrl: route('admin.tickets.show', $ticket),
                    actionLabel: 'Open Ticket'
                ));
                $sentCount++;
            } catch (\Throwable $exception) {
                Log::warning('Ticket alert email failed.', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'recipient_user_id' => $recipient->id ?? null,
                    'recipient_email' => $recipient->email ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sentCount;
    }
}
