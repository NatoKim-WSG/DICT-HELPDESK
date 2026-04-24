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
            return $ticket->hasAssignedUser((int) $user->id)
                || ($ticket->isInternalRequesterTicketFor((int) $user->id) && $ticket->isClosed());
        }

        return $user->canAccessAdminTickets();
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        if ($user->isClient()) {
            return false;
        }

        if ($user->isTechnician()) {
            return $ticket->hasAssignedUser((int) $user->id);
        }

        return $user->canAccessAdminTickets();
    }
}
