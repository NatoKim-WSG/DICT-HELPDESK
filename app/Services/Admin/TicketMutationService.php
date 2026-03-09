<?php

namespace App\Services\Admin;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Collection;

class TicketMutationService
{
    public function deleteTicketWithRelations(Ticket $ticket): void
    {
        $ticket->attachments()->get()->each->delete();

        /** @var \Illuminate\Database\Eloquent\Collection<int, TicketReply> $ticketReplies */
        $ticketReplies = $ticket->replies()->with('attachments')->get();
        $ticketReplies->each(function (TicketReply $reply): void {
            $reply->attachments()->get()->each->delete();
            $reply->delete();
        });

        $ticket->delete();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function deleteManyTicketsWithRelations(Collection $tickets): void
    {
        $tickets->each(function (Ticket $ticket): void {
            $this->deleteTicketWithRelations($ticket);
        });
    }

    /**
     * @param  Collection<int, Ticket>  $otherTickets
     */
    public function mergeTickets(Ticket $primary, Collection $otherTickets, ?int $actorId): void
    {
        foreach ($otherTickets as $ticket) {
            TicketReply::create([
                'ticket_id' => $primary->id,
                'user_id' => $actorId,
                'message' => "Merged ticket {$ticket->ticket_number}: {$ticket->subject}",
                'is_internal' => true,
            ]);

            TicketReply::where('ticket_id', $ticket->id)->update(['ticket_id' => $primary->id]);
            Attachment::where('attachable_type', Ticket::class)
                ->where('attachable_id', $ticket->id)
                ->update(['attachable_id' => $primary->id]);

            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        }
    }
}
