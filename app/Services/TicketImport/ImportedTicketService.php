<?php

namespace App\Services\TicketImport;

use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Support\Carbon;

class ImportedTicketService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function applyImportMetadata(array $attributes): array
    {
        $attributes['is_imported'] = true;

        return $attributes;
    }

    public function syncImportedReviewState(Ticket $ticket): void
    {
        $assignedAt = $ticket->assigned_at instanceof Carbon
            ? $ticket->assigned_at
            : ($ticket->assigned_at ? Carbon::parse($ticket->assigned_at) : null);

        if (! $assignedAt instanceof Carbon) {
            return;
        }

        $superUserId = User::query()
            ->where('role', User::ROLE_SUPER_USER)
            ->orderBy('id')
            ->value('id');

        if (! $superUserId) {
            return;
        }

        TicketUserState::markAcknowledged($ticket, (int) $superUserId, $assignedAt, true);
    }
}
