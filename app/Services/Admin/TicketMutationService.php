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
        $primary->loadMissing('assignedUsers');
        $primaryAssignedIds = $primary->assigned_user_ids;
        $mergedAssignedIds = $otherTickets
            ->flatMap(fn (Ticket $ticket) => $ticket->assigned_user_ids)
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn (int $userId) => $userId > 0)
            ->unique()
            ->values()
            ->all();
        $combinedAssignedIds = array_values(array_unique([
            ...$primaryAssignedIds,
            ...$mergedAssignedIds,
        ]));

        if ($combinedAssignedIds !== $primaryAssignedIds) {
            $updateData = [];
            $nextPrimaryAssignee = $primary->assigned_to ? (int) $primary->assigned_to : ($combinedAssignedIds[0] ?? null);

            if ((int) ($primary->assigned_to ?? 0) !== (int) ($nextPrimaryAssignee ?? 0)) {
                $updateData['assigned_to'] = $nextPrimaryAssignee;
            }

            if ($primary->assigned_at === null && $combinedAssignedIds !== []) {
                $updateData['assigned_at'] = now();
            }

            if ($updateData !== []) {
                $primary->update($updateData);
            }

            $primary->assignedUsers()->sync($combinedAssignedIds);
            $primary->refresh();
        }

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
                'resolved_at' => $ticket->resolved_at ?? now(),
                'closed_at' => now(),
                'closed_by' => $actorId,
            ]);
        }
    }
}
