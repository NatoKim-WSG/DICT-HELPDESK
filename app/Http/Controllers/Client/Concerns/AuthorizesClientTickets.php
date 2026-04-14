<?php

namespace App\Http\Controllers\Client\Concerns;

use App\Models\Ticket;

trait AuthorizesClientTickets
{
    protected function authorizeOwnedTicket(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }
}
