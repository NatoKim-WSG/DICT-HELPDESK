<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Tickets\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Services\Client\ClientTicketIndexService;
use App\Services\SystemLogService;
use App\Services\TicketEmailAlertService;
use App\Support\LeadingUppercaseNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    public function __construct(
        private ClientTicketIndexService $ticketIndex,
        private TicketEmailAlertService $ticketEmailAlerts,
        private SystemLogService $systemLogs,
    ) {}

    public function index(Request $request)
    {
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString());
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus(
            $request->string('status')->toString(),
            $activeTab
        );

        $query = $this->ticketIndex->scopedTicketQueryFor(auth()->user());
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFilters($query, $request, $selectedStatus);

        $liveSnapshotToken = $this->ticketIndex->buildSnapshotToken(clone $query);
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
            ]);
        }

        $tickets = $query->latest()->paginate(10)->withQueryString();

        return view('client.tickets.index', compact('tickets', 'liveSnapshotToken', 'activeTab', 'selectedStatus'));
    }

    public function create()
    {
        $categories = Category::active()->get();

        return view('client.tickets.create', compact('categories'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticketData = [
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'province' => LeadingUppercaseNormalizer::normalize($request->string('province')->toString()),
            'municipality' => LeadingUppercaseNormalizer::normalize($request->string('municipality')->toString()),
            'subject' => LeadingUppercaseNormalizer::normalize($request->string('subject')->toString()),
            'description' => $request->description,
            'category_id' => $request->category_id,
            'ticket_type' => Ticket::TYPE_EXTERNAL,
            'priority' => null,
            'user_id' => auth()->id(),
            'created_by_user_id' => auth()->id(),
            'creation_source' => Ticket::CREATION_SOURCE_CLIENT_SELF_SERVICE,
            'consent_accepted_at' => now(),
            'consent_version' => (string) config('legal.ticket_consent_version'),
            'consent_ip_address' => $request->ip(),
            'consent_user_agent' => $request->userAgent(),
        ];

        $ticket = $this->withAttachmentWriteGuard(function () use ($request, $ticketData) {
            return DB::transaction(function () use ($request, $ticketData) {
                $ticket = Ticket::create($ticketData);
                $this->persistAttachmentsFromRequest($request, $ticket);

                return $ticket;
            });
        });

        $this->ticketEmailAlerts->notifySuperUsersAboutNewTicket($ticket);
        $this->systemLogs->record(
            'ticket.created',
            'Created a support ticket.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'category_id' => (int) $ticket->category_id,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('client.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully!');
    }
}
