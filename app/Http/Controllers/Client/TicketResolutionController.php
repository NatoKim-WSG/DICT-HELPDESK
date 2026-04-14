<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesClientTickets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\RateTicketRequest;
use App\Http\Requests\Client\Tickets\ResolveTicketRequest;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\SystemLogService;
use Illuminate\Http\Request;

class TicketResolutionController extends Controller
{
    use AuthorizesClientTickets;

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function resolve(ResolveTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        if ($ticket->status === 'closed') {
            return redirect()->back()->with('error', 'Closed tickets cannot be marked as resolved.');
        }

        if ($ticket->status !== 'resolved') {
            $previousStatus = $ticket->status;
            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth()->id(),
                'message' => 'Client marked this ticket as resolved.',
                'is_internal' => false,
            ]);

            $ticket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'closed_at' => null,
                'satisfaction_rating' => $request->integer('rating'),
                'satisfaction_comment' => $request->string('comment')->trim()->toString(),
            ]);
            $this->systemLogs->record(
                'ticket.resolved_by_client',
                'Client marked a ticket as resolved.',
                [
                    'category' => 'ticket',
                    'target_type' => Ticket::class,
                    'target_id' => $ticket->id,
                    'metadata' => [
                        'ticket_number' => $ticket->ticket_number,
                        'previous_status' => $previousStatus,
                        'new_status' => 'resolved',
                        'rating' => $request->integer('rating'),
                    ],
                    'request' => request(),
                ]
            );
            $this->recordSatisfactionLog(
                $ticket,
                $request->integer('rating'),
                $request->string('comment')->trim()->toString(),
                $request
            );
        }

        return redirect()->back()->with('success', 'Ticket marked as resolved and your rating has been submitted.');
    }

    public function rate(RateTicketRequest $request, Ticket $ticket)
    {
        $this->authorizeOwnedTicket($ticket);

        if ($ticket->status !== 'resolved' || $ticket->satisfaction_rating !== null) {
            return redirect()->back()->with('error', 'Only resolved tickets awaiting feedback can be rated.');
        }

        $ticket->update([
            'satisfaction_rating' => $request->integer('rating'),
            'satisfaction_comment' => $request->string('comment')->trim()->toString(),
        ]);
        $this->recordSatisfactionLog(
            $ticket,
            $request->integer('rating'),
            $request->string('comment')->trim()->toString(),
            $request
        );

        return redirect()->back()->with('success', 'Rating submitted successfully!');
    }

    private function recordSatisfactionLog(Ticket $ticket, int $rating, string $comment, Request $request): void
    {
        $this->systemLogs->record(
            'ticket.rating.submitted',
            'Submitted ticket satisfaction rating.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'rating' => $rating,
                    'has_comment' => $comment !== '',
                ],
                'request' => $request,
            ]
        );
    }
}
