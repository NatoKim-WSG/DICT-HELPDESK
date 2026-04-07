@php
    $tab = $activeTab ?? 'tickets';
    $isHistoryTab = $tab === 'history';
    $closedRevertWindowDays = 7;
    $canDeleteTickets = auth()->user()->isSuperAdmin();
    $requiresDelayedClose = in_array(auth()->user()->normalizedRole(), [\App\Models\User::ROLE_TECHNICAL, \App\Models\User::ROLE_SUPER_USER], true);
    $paginationQuery = request()->except(['page', 'partial']);
@endphp

<div data-admin-tickets-results>
    @if($createdDateRange)
        <div class="px-5 pb-4 sm:px-6">
            <p class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Report scope: {{ !empty($createdDateRange['label']) ? $createdDateRange['label'] : ($createdDateRange['from'].' to '.$createdDateRange['to']) }}
            </p>
        </div>
    @endif

    <div class="space-y-3 px-4 pb-4 lg:hidden">
        @forelse($tickets as $ticket)
            @php
                $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                    ? $ticket->created_at->diffForHumans()
                    : $ticket->created_at->format('M j, Y');
                $lastSeenTs = $ticketSeenTimestamps[(int) $ticket->id] ?? null;
                $isNew = $ticket->created_at->greaterThanOrEqualTo(now()->subDay())
                    && (!$lastSeenTs || $lastSeenTs < $ticket->created_at->timestamp);
                $completedAt = $ticket->closed_at ?? $ticket->resolved_at;
                $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
                $canRevertTicket = $ticket->status === 'resolved'
                    || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
            @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                @if($canDeleteTickets)
                    <div class="mb-3 flex items-center justify-end">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <input type="checkbox" class="js-ticket-checkbox ticket-checkbox ticket-checkbox--large" value="{{ $ticket->id }}">
                            <span>Select</span>
                        </label>
                    </div>
                @endif
                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                    <p class="text-sm font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                    <p class="mt-1 text-xs text-[#af9257]">{{ $ticket->category->name }} - {{ $ticket->user->name }}</p>
                    <p class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                        <span>Created {{ $createdLabel }}</span>
                        @if($isNew)
                            <span data-ticket-new-badge="1" class="inline-flex items-center rounded-full bg-[#e9fff6] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#067647]">New</span>
                        @endif
                    </p>
                </a>

                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wide text-slate-400">Assigned</span>
                        @if($ticket->assigned_user_ids !== [])
                            <button
                                type="button"
                                class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--assigned"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->ticket_number }}"
                                data-assigned-to='@json($ticket->assigned_user_ids)'
                            >
                                {{ $ticket->assigned_users_label }}
                            </button>
                        @else
                            <button
                                type="button"
                                class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--unassigned"
                                data-ticket-id="{{ $ticket->id }}"
                                data-ticket-number="{{ $ticket->ticket_number }}"
                                data-assigned-to="[]"
                            >
                                Assign
                            </button>
                        @endif
                    </div>

                    @if($isHistoryTab)
                        <div>
                            <span class="block text-[11px] uppercase tracking-wide text-slate-400">Completed</span>
                            <span>{{ $completedAt ? $completedAt->format('M j, Y g:i A') : '-' }}</span>
                        </div>
                    @else
                        <div>
                            <span class="block text-[11px] uppercase tracking-wide text-slate-400">Activity</span>
                            <span>{{ $ticket->activity_label }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-3 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex min-w-16 items-center justify-center rounded-md px-2.5 py-1 text-[11px] font-semibold {{ $ticket->priority_badge_class }}">
                            {{ $ticket->priority_label }}
                        </span>
                        <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                            {{ $ticket->status_label }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if(in_array($ticket->status, ['resolved', 'closed'], true))
                            @if($canRevertTicket)
                                <button
                                    type="button"
                                    class="js-open-revert-modal inline-flex items-center rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                >
                                    Revert
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-400"
                                    disabled
                                    title="Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days."
                                >
                                    Revert expired
                                </button>
                            @endif
                        @endif
                        <button
                            type="button"
                            class="js-open-edit-modal inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            data-ticket-id="{{ $ticket->id }}"
                            data-ticket-number="{{ $ticket->ticket_number }}"
                            data-assigned-to='@json($ticket->assigned_user_ids)'
                            data-status="{{ $ticket->status }}"
                            data-priority="{{ $ticket->priority }}"
                            data-can-revert="{{ $canRevertTicket ? '1' : '0' }}"
                            data-can-close-now="{{ (!$requiresDelayedClose || ($ticket->resolved_at && now()->gte($ticket->resolved_at->copy()->addDay()))) ? '1' : '0' }}"
                            data-close-available-at="{{ $ticket->resolved_at ? $ticket->resolved_at->copy()->addDay()->format('M j, Y \\a\\t g:i A') : '' }}"
                        >
                            Edit
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center">
                <p class="text-base font-semibold text-slate-700">No tickets found</p>
                <p class="mt-1 text-sm text-slate-500">Try adjusting your filters to broaden results.</p>
            </div>
        @endforelse
    </div>

    <div class="hidden max-h-[70vh] overflow-auto lg:block">
        <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    @if($canDeleteTickets)
                        <th class="w-[4%] px-4 py-4 text-center">
                            <label class="inline-flex items-center justify-center">
                                <input id="select-all-tickets" type="checkbox" class="ticket-checkbox ticket-checkbox--large" aria-label="Select all visible tickets">
                            </label>
                        </th>
                    @endif
                    <th class="{{ $canDeleteTickets ? 'w-[36%]' : 'w-[40%]' }} px-6 py-4">Details</th>
                    <th class="w-[16%] px-6 py-4 text-center">Assigned Technical</th>
                    <th class="w-[10%] px-6 py-4 text-center">Severity</th>
                    @if($isHistoryTab)
                        <th class="w-[16%] px-6 py-4 text-center">Completed At</th>
                    @else
                        <th class="w-[16%] px-6 py-4 text-center">Activity Status</th>
                    @endif
                    <th class="w-[8%] px-6 py-4 text-center">Status</th>
                    <th class="w-[10%] px-6 py-4 text-center">Action</th>
                </tr>
            </thead>

            <tbody class="app-table-body divide-y divide-slate-200 bg-white">
                @forelse($tickets as $ticket)
                    @php
                        $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                            ? $ticket->created_at->diffForHumans()
                            : $ticket->created_at->format('M j, Y');
                        $lastSeenTs = $ticketSeenTimestamps[(int) $ticket->id] ?? null;
                        $isNew = $ticket->created_at->greaterThanOrEqualTo(now()->subDay())
                            && (!$lastSeenTs || $lastSeenTs < $ticket->created_at->timestamp);
                        $completedAt = $ticket->closed_at ?? $ticket->resolved_at;
                        $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
                        $canRevertTicket = $ticket->status === 'resolved'
                            || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
                    @endphp

                    <tr class="admin-ticket-row transition hover:bg-slate-50">
                        @if($canDeleteTickets)
                            <td class="px-4 py-5 text-center align-middle">
                                <input type="checkbox" class="js-ticket-checkbox ticket-checkbox ticket-checkbox--large" value="{{ $ticket->id }}" aria-label="Select ticket {{ $ticket->ticket_number }}">
                            </td>
                        @endif
                        <td class="px-6 py-5 align-top">
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                                <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                <p class="mt-0.5 text-sm text-[#af9257]">{{ $ticket->category->name }} - {{ $ticket->user->name }}</p>
                                <p class="mt-1 flex items-center gap-2 text-sm text-slate-500">
                                    <span>Created {{ $createdLabel }}</span>
                                    @if($isNew)
                                        <span data-ticket-new-badge="1" class="inline-flex items-center rounded-full bg-[#e9fff6] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#067647]">New</span>
                                    @endif
                                </p>
                            </a>
                        </td>

                        <td class="px-6 py-5 align-top text-center text-sm text-slate-700">
                            @if($ticket->assigned_user_ids !== [])
                                <button
                                    type="button"
                                    class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--assigned justify-center"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to='@json($ticket->assigned_user_ids)'
                                >
                                    {{ $ticket->assigned_users_label }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    class="js-open-assign-modal assigned-tech-btn assigned-tech-btn--unassigned justify-center"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to="[]"
                                >
                                    Assign
                                </button>
                            @endif
                        </td>

                        <td class="px-6 py-5 text-center align-top">
                            <span class="inline-flex min-w-16 items-center justify-center rounded-md px-3 py-1 text-xs font-semibold {{ $ticket->priority_badge_class }}">
                                {{ $ticket->priority_label }}
                            </span>
                        </td>

                        @if($isHistoryTab)
                            <td class="px-6 py-5 text-center align-top text-sm text-slate-700">
                                {{ $completedAt ? $completedAt->format('M j, Y \a\t g:i A') : '-' }}
                            </td>
                        @else
                            <td class="px-6 py-5 text-center align-top">
                                <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $ticket->activity_dot_class }}"></span>
                                    {{ $ticket->activity_label }}
                                </span>
                            </td>
                        @endif

                        <td class="px-6 py-5 text-center align-top">
                            <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                                {{ $ticket->status_label }}
                            </span>
                        </td>

                        <td class="px-6 py-5 text-center align-top">
                            <div class="flex flex-col items-center gap-2">
                                @if(in_array($ticket->status, ['resolved', 'closed'], true))
                                    @if($canRevertTicket)
                                        <button
                                            type="button"
                                            class="js-open-revert-modal inline-flex items-center rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50"
                                            data-ticket-id="{{ $ticket->id }}"
                                            data-ticket-number="{{ $ticket->ticket_number }}"
                                        >
                                            Revert
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-400"
                                            disabled
                                            title="Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days."
                                        >
                                            Revert expired
                                        </button>
                                    @endif
                                @endif
                                <button
                                    type="button"
                                    class="js-open-edit-modal inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to='@json($ticket->assigned_user_ids)'
                                    data-status="{{ $ticket->status }}"
                                    data-priority="{{ $ticket->priority }}"
                                    data-can-revert="{{ $canRevertTicket ? '1' : '0' }}"
                                    data-can-close-now="{{ (!$requiresDelayedClose || ($ticket->resolved_at && now()->gte($ticket->resolved_at->copy()->addDay()))) ? '1' : '0' }}"
                                    data-close-available-at="{{ $ticket->resolved_at ? $ticket->resolved_at->copy()->addDay()->format('M j, Y \\a\\t g:i A') : '' }}"
                                >
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canDeleteTickets ? '7' : '6' }}" class="px-6 py-14 text-center">
                            <p class="text-base font-semibold text-slate-700">No tickets found</p>
                            <p class="mt-1 text-sm text-slate-500">Try adjusting your filters to broaden results.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tickets->count() > 0)
        <div data-admin-ticket-pagination class="border-t border-slate-200 px-6 py-4">
            {{ $tickets->appends($paginationQuery)->links() }}
        </div>
    @endif
</div>
