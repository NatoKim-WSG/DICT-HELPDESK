<?php

namespace App\Console\Commands;

use App\Services\TicketEmailAlertService;
use Illuminate\Console\Command;

class SendTicketAlertEmails extends Command
{
    protected $signature = 'tickets:send-alert-emails';

    protected $description = 'Send scheduled ticket email alerts and SLA reminders.';

    public function handle(TicketEmailAlertService $alerts): int
    {
        $summary = $alerts->sendScheduledReminders();

        $this->info(sprintf(
            'Ticket alerts sent. unchecked=%d, super_sla=%d, technical_sla=%d',
            $summary['unchecked_for_super_users'] ?? 0,
            $summary['unassigned_sla_for_super_users'] ?? 0,
            $summary['assigned_sla_for_technical_users'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
