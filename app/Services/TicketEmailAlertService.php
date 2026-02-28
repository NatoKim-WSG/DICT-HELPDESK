<?php

namespace App\Services;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketEmailAlertService
{
    public function notifySuperUsersAboutNewTicket(Ticket $ticket): int
    {
        if ($ticket->super_users_notified_new_at) {
            return 0;
        }

        $sentCount = $this->sendAlertToUsers(
            $this->newTicketSuperUserRecipients(),
            $ticket,
            'New Ticket Received: '.$ticket->ticket_number,
            'A new support ticket has been received.',
            'Please review and route this ticket as soon as possible.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Priority' => ucfirst((string) $ticket->priority),
                'Requester' => (string) $ticket->name,
            ]
        );

        if ($sentCount > 0) {
            $ticket->forceFill([
                'super_users_notified_new_at' => now(),
            ])->save();
        }

        return $sentCount;
    }

    public function notifyTechnicalAssigneeAboutAssignment(Ticket $ticket): bool
    {
        $ticket->loadMissing('assignedUser');
        $assignee = $ticket->assignedUser;

        if (! $assignee || ! $assignee->isTechnician() || ! $assignee->is_active || empty($assignee->email)) {
            return false;
        }

        $sentCount = $this->sendAlertToUsers(
            collect([$assignee]),
            $ticket,
            'Ticket Assigned: '.$ticket->ticket_number,
            'A ticket was assigned to you.',
            'Please review and start progress before the 4-hour resolution target.',
            [
                'Ticket Number' => $ticket->ticket_number,
                'Subject' => $ticket->subject,
                'Priority' => ucfirst((string) $ticket->priority),
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
            'unchecked_for_super_users' => $this->sendUncheckedTicketRemindersToSuperUsers(),
            'unassigned_sla_for_super_users' => $this->sendUnassignedSlaRemindersToSuperUsers(),
            'assigned_sla_for_technical_users' => $this->sendAssignedTechnicalSlaReminders(),
        ];
    }

    private function sendUncheckedTicketRemindersToSuperUsers(): int
    {
        $superRecipients = $this->superUserRecipients();
        if ($superRecipients->isEmpty()) {
            return 0;
        }

        $cutoff = now()->subMinutes(50);

        $tickets = Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNull('super_users_notified_unchecked_at')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('userStates', function ($query) {
                $query->whereNotNull('last_seen_at')
                    ->whereHas('user', function ($userQuery) {
                        $userQuery->whereIn('role', $this->superUserRoles());
                    });
            })
            ->with('user')
            ->get();

        $notifiedTickets = 0;

        foreach ($tickets as $ticket) {
            $sentCount = $this->sendAlertToUsers(
                $superRecipients,
                $ticket,
                'Unchecked Ticket Alert (50 Minutes): '.$ticket->ticket_number,
                'A ticket has not been checked by a super user for 50 minutes.',
                'Please review this ticket now to avoid missing the 4-hour response target.',
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Priority' => ucfirst((string) $ticket->priority),
                    'Received At' => optional($ticket->created_at)->format('Y-m-d H:i:s'),
                ]
            );

            if ($sentCount > 0) {
                $ticket->forceFill([
                    'super_users_notified_unchecked_at' => now(),
                ])->save();
                $notifiedTickets++;
            }
        }

        return $notifiedTickets;
    }

    private function sendUnassignedSlaRemindersToSuperUsers(): int
    {
        $superRecipients = $this->superUserRecipients();
        if ($superRecipients->isEmpty()) {
            return 0;
        }

        $cutoff = now()->subHours(3)->subMinutes(30);

        $tickets = Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNull('assigned_to')
            ->whereNull('super_users_notified_unassigned_sla_at')
            ->with([
                'user',
                'userStates' => function ($query) {
                    $query->whereNotNull('last_seen_at')
                        ->whereHas('user', function ($userQuery) {
                            $userQuery->whereIn('role', $this->superUserRoles());
                        })
                        ->select(['id', 'ticket_id', 'user_id', 'last_seen_at']);
                },
            ])
            ->get();

        $notifiedTickets = 0;

        foreach ($tickets as $ticket) {
            $latestSuperViewTimestamp = $ticket->userStates
                ->map(fn (TicketUserState $state) => optional($state->last_seen_at)?->timestamp)
                ->filter()
                ->max();

            if (! $latestSuperViewTimestamp || $latestSuperViewTimestamp > $cutoff->timestamp) {
                continue;
            }

            $sentCount = $this->sendAlertToUsers(
                $superRecipients,
                $ticket,
                '4-Hour SLA Reminder (Unassigned): '.$ticket->ticket_number,
                'This unassigned ticket is nearing the 4-hour resolution target.',
                'The ticket was viewed by a super user 3 hours and 30 minutes ago but is still unresolved and unassigned.',
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Priority' => ucfirst((string) $ticket->priority),
                    'Last Super User View' => date('Y-m-d H:i:s', $latestSuperViewTimestamp),
                ]
            );

            if ($sentCount > 0) {
                $ticket->forceFill([
                    'super_users_notified_unassigned_sla_at' => now(),
                ])->save();
                $notifiedTickets++;
            }
        }

        return $notifiedTickets;
    }

    private function sendAssignedTechnicalSlaReminders(): int
    {
        $cutoff = now()->subHours(3)->subMinutes(30);

        $tickets = Ticket::query()
            ->whereIn('status', Ticket::OPEN_STATUSES)
            ->whereNotNull('assigned_to')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', $cutoff)
            ->whereNull('technical_user_notified_sla_at')
            ->with(['assignedUser'])
            ->get();

        $notifiedTickets = 0;

        foreach ($tickets as $ticket) {
            $assignee = $ticket->assignedUser;
            if (! $assignee || ! $assignee->isTechnician() || ! $assignee->is_active || empty($assignee->email)) {
                continue;
            }

            $sentCount = $this->sendAlertToUsers(
                collect([$assignee]),
                $ticket,
                '4-Hour SLA Reminder (Assigned): '.$ticket->ticket_number,
                'This assigned ticket is nearing the 4-hour resolution target.',
                '3 hours and 30 minutes have passed since assignment and the ticket is still unresolved.',
                [
                    'Ticket Number' => $ticket->ticket_number,
                    'Subject' => $ticket->subject,
                    'Priority' => ucfirst((string) $ticket->priority),
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

    private function newTicketSuperUserRecipients(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_SUPER_USER)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'role', 'is_active']);
    }

    private function superUserRecipients(): Collection
    {
        return User::query()
            ->whereIn('role', $this->superUserRoles())
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'role', 'is_active']);
    }

    private function superUserRoles(): array
    {
        return [
            User::ROLE_SHADOW,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_USER,
        ];
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

