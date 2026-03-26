<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
            return (int) $ticket->user_id === (int) $user->id;
        }

        if ($user->isTechnician()) {
            return $ticket->hasAssignedUser((int) $user->id);
        }

        return $user->canAccessAdminTickets();
    }
}
