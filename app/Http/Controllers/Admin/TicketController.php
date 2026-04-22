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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $provinceOptions = $this->ticketIndex->distinctTicketColumnOptions(
            'province',
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['province'])
        );
        $municipalityOptions = $this->ticketIndex->distinctTicketColumnOptions(
            'municipality',
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['municipality'])
        );
        $accountOptions = $this->ticketIndex->accountOptionsFor(
            $currentUser,
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['account'])
        );
        $monthOptions = $this->ticketIndex->monthOptionsFor(
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['month'])
        );
        $categories = $this->ticketIndex->categoryOptionsFor(
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['category'])
        );
        $filterAssignees = $this->ticketIndex->assignedAgentOptionsFor(
            $this->buildFilterOptionQuery($request, $currentUser, $activeTab, $selectedStatus, $createdDateRange, ['assigned_to'])
        );
        $assignmentAssignees = $this->ticketIndex->activeAssignableAgents();

        return view('admin.tickets.index', compact(
            'tickets',
            'categories',
            'filterAssignees',
            'assignmentAssignees',
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
        $supportAccounts = User::query()
            ->whereIn('role', User::TICKET_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'department', 'role']);

        return view('admin.tickets.create', compact('categories', 'clientAccounts', 'supportAccounts'));
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = $this->withAttachmentWriteGuard(function () use ($request) {
            return DB::transaction(function () use ($request) {
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

                return $ticket;
            });
        });

        $this->systemLogs->record(
            'ticket.created_by_support_user',
            'Created a ticket on behalf of a requester account.',
            [
                'category' => 'ticket',
                'target_type' => Ticket::class,
                'target_id' => $ticket->id,
                'metadata' => [
                    'actor_role' => auth()->user()?->normalizedRole(),
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_type' => $ticket->ticket_type,
                    'requester_user_id' => (int) $ticket->user_id,
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

    private function buildFilterOptionQuery(
        Request $request,
        ?User $currentUser,
        string $activeTab,
        string $selectedStatus,
        ?array $createdDateRange,
        array $excludedFilters = [],
    ): Builder {
        $query = $this->ticketIndex->scopedTicketQueryFor($currentUser);
        $this->ticketIndex->applyTabScope($query, $activeTab);
        $this->ticketIndex->applyFiltersExcept($query, $request, $selectedStatus, $createdDateRange, $excludedFilters);

        return $query;
    }
}
