<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Ticket) {
            if ($user->isClient()) {
                return (int) $attachable->user_id === (int) $user->id;
            }

            if ($user->isTechnician()) {
                return $attachable->hasAssignedUser((int) $user->id);
            }

            return $user->canAccessAdminTickets();
        }

        if ($attachable instanceof TicketReply) {
            $ticket = $attachable->ticket;
            if (! $ticket) {
                return false;
            }

            if ($user->isClient()) {
                if ((int) $ticket->user_id !== (int) $user->id) {
                    return false;
                }

                return ! (bool) $attachable->is_internal;
            }

            if ($user->isTechnician()) {
                return $ticket->hasAssignedUser((int) $user->id);
            }

            return $user->canAccessAdminTickets();
        }

        return false;
    }
}
