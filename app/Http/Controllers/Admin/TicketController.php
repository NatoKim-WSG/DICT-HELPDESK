<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTicketReplies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tickets\StoreTicketRequest;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use App\Services\Admin\TicketIndexService;
use App\Services\SystemLogService;
use App\Services\TicketAcknowledgmentService;
use App\Support\LeadingUppercaseNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TicketController extends Controller
{
    use InteractsWithTicketReplies;

    public function __construct(
        private TicketAcknowledgmentService $ticketAcknowledgments,
        private SystemLogService $systemLogs,
        private TicketIndexService $ticketIndex,
    ) {}

    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $activeTab = $this->ticketIndex->resolveActiveTab($request->string('tab')->toString(), $currentUser);
        $selectedStatus = $this->ticketIndex->resolveSelectedStatus($request->string('status')->toString(), $activeTab);
        $createdDateRange = $this->ticketIndex->resolveCreatedDateRange($request);
        $currentPage = max(1, $request->integer('page', 1));
        $perPage = 15;

        $filteredQuery = $this->ticketIndex->scopedTicketQueryFor($currentUser);
        $this->ticketIndex->applyTabScope($filteredQuery, $activeTab);
        $this->ticketIndex->applyFilters($filteredQuery, $request, $selectedStatus, $createdDateRange);

        $liveSnapshotToken = $this->ticketIndex->buildTicketListSnapshotToken(clone $filteredQuery);
        $pageSnapshotToken = $this->ticketIndex->buildTicketListPageSnapshotToken(
            (clone $filteredQuery)->latest(),
            $currentPage,
            $perPage
        );
        if ($request->boolean('heartbeat')) {
            return response()->json([
                'token' => $liveSnapshotToken,
                'page_token' => $pageSnapshotToken,
            ]);
        }

        $tickets = (clone $filteredQuery)
            ->with(['user', 'category', 'assignedUser', 'assignedUsers', 'closedBy'])
            ->latest()
            ->paginate($perPage, ['*'], 'page', $currentPage);
        $pageSnapshotToken = $this->ticketIndex->buildTicketListPageSnapshotTokenForTickets($tickets);
        $ticketSeenTimestamps = TicketUserState::where('user_id', auth()->id())
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->get()
            ->mapWithKeys(function (TicketUserState $state) {
                return [
                    (int) $state->ticket_id => optional($state->last_seen_at)->timestamp,
                ];
            })
            ->all();

        if ($request->boolean('partial')) {
            return response()->json([
                'html' => view('admin.tickets.partials.results', compact(
                    'tickets',
                    'ticketSeenTimestamps',
                    'createdDateRange',
                    'activeTab'
                ))->render(),
                'token' => $liveSnapshotToken,
                'page_token' => $pageSnapshotToken,
            ]);
        }

        $scopedTickets = $this->ticketIndex->scopedTicketQueryFor($currentUser);
        $provinceOptions = $this->ticketIndex->distinctTicketColumnOptions('province', clone $scopedTickets);
        $municipalityOptions = $this->ticketIndex->distinctTicketColumnOptions('municipality', clone $scopedTickets);
        $accountOptions = $this->ticketIndex->accountOptionsFor($currentUser, clone $scopedTickets);
        $monthOptions = $this->ticketIndex->monthOptionsFor(clone $scopedTickets);

        $categories = Cache::remember('admin_ticket_active_categories_v1', now()->addSeconds(120), function () {
            return Category::active()
                ->orderBy('name')
                ->get(['id', 'name']);
        });
        $assignees = $this->ticketIndex->activeAssignableAgents();

        return view('admin.tickets.index', compact(
            'tickets',
            'categories',
            'assignees',
            'provinceOptions',
            'municipalityOptions',
            'accountOptions',
            'monthOptions',
            'activeTab',
            'liveSnapshotToken',
            'pageSnapshotToken',
            'ticketSeenTimestamps',
            'createdDateRange'
        ));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canCreateClientTickets(), 403);

        $categories = Category::active()
            ->orderBy('name')
            ->get(['id', 'name']);
        $clientAccounts = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'department']);

        return view('admin.tickets.create', compact('categories', 'clientAccounts'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = Ticket::create([
            'name' => $request->string('name')->toString(),
            'contact_number' => $request->filled('contact_number')
                ? $request->string('contact_number')->toString()
                : null,
            'email' => $request->filled('email')
                ? $request->string('email')->toString()
                : null,
            'province' => LeadingUppercaseNormalizer::normalize($request->string('province')->toString()),
            'municipality' => LeadingUppercaseNormalizer::normalize($request->string('municipality')->toString()),
            'subject' => LeadingUppercaseNormalizer::normalize($request->string('subject')->toString()),
            'description' => $request->string('description')->toString(),
            'category_id' => $request->integer('category_id'),
            'ticket_type' => $request->string('ticket_type')->toString(),
            'priority' => null,
            'status' => 'open',
            'user_id' => $request->integer('user_id'),
        ]);

        $this->persistAttachmentsFromRequest($request, $ticket);
        $this->ticketAcknowledgments->acknowledge($ticket, auth()->user());
        $this->systemLogs->record(
            'ticket.created_by_super_user',
            'Created a ticket on behalf of a client.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_type' => $ticket->ticket_type,
                    'client_user_id' => (int) $ticket->user_id,
                    'category_id' => (int) $ticket->category_id,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeTicketAccess($ticket);

        TicketUserState::markSeenAndDismiss($ticket, (int) auth()->id(), $ticket->updated_at ?? now());

        $this->loadTicketWithVisibleReplies($ticket);
        $replyFeedCursor = $this->replyFeedCursorForReplies($ticket->replies);
        $assignees = $this->ticketIndex->activeAssignableAgents();
        $currentUserState = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', (int) auth()->id())
            ->first();

        return view('admin.tickets.show', compact('ticket', 'assignees', 'currentUserState', 'replyFeedCursor'));
    }

    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
    }
}
