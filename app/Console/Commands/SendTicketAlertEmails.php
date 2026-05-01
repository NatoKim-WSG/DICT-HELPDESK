<?php

namespace App\Console\Commands;

use App\Services\TicketEmailAlertService;
use Illuminate\Console\Command;

class SendTicketAlertEmails extends Command
{
    protected $signature = 'tickets:send-alert-emails';

    protected $description = 'Send scheduled technical ticket email alerts and SLA reminders.';

    public function handle(TicketEmailAlertService $alerts): int
    {
        $summary = $alerts->sendScheduledReminders();

        $this->info(sprintf(
            'Ticket alerts sent. technical_sla=%d',
            $summary['assigned_sla_for_technical_users'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
