@extends('layouts.app')

@section('title', 'Tickets - ' . config('app.name'))

@section('content')
@php
    $tab = $activeTab ?? 'tickets';
    $isAllTab = $tab === 'all';
    $isHistoryTab = $tab === 'history';
    $canDeleteTickets = auth()->user()->isSuperAdmin();
    $canCreateTickets = auth()->user()->canCreateClientTickets();
    $canManageTicketType = auth()->user()->canManageTicketType();
    $selectedPriority = \App\Models\Ticket::normalizePriorityValue(request('priority'));
    $baseQuery = request()->except(['page', 'tab', 'selected_ids', 'action', 'status', 'priority']);
    $tabAllUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'all']));
    $tabTicketsUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'tickets']));
    $tabAttentionUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'attention']));
    $tabHistoryUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'history']));
    $selectedMonth = old('month', request('month', data_get($createdDateRange, 'month', '')));
@endphp
<div class="mx-auto max-w-[1460px]"
    data-admin-tickets-index-page
    data-route-base="{{ route('admin.tickets.index', absolute: false) }}"
    data-snapshot-token="{{ $liveSnapshotToken ?? '' }}"
    data-assign-route-template="{{ route('admin.tickets.assign', ['ticket' => '__TICKET__'], absolute: false) }}"
    data-status-route-template="{{ route('admin.tickets.status', ['ticket' => '__TICKET__'], absolute: false) }}"
    data-quick-update-route-template="{{ route('admin.tickets.quick-update', ['ticket' => '__TICKET__'], absolute: false) }}"
    data-delete-route-template="{{ route('admin.tickets.destroy', ['ticket' => '__TICKET__'], absolute: false) }}">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-4xl font-semibold text-slate-900">Tickets</h1>
        @if($canCreateTickets)
            <a href="{{ route('admin.tickets.create') }}" class="btn-primary">
                Create Ticket
            </a>
        @endif
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200">
                <div class="flex items-center gap-7">
                    <a href="{{ $tabAllUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'all' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">All</a>
                    <a href="{{ $tabTicketsUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'tickets' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">Tickets</a>
                    <a href="{{ $tabAttentionUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'attention' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">Needs Attention</a>
                    <a href="{{ $tabHistoryUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'history' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">History</a>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <div class="relative">
                        <select
                            id="admin-status-view"
                            name="status"
                            data-text-transform="capitalize"
                            class="h-10 min-w-[140px] appearance-none rounded-xl border border-[#64b5a8] bg-white pl-4 pr-10 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize"
                        >
                            <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Select view</option>
                            @if($isHistoryTab)
                                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                            @elseif($isAllTab)
                                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                            @elseif($tab === 'attention')
                                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            @else
                                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            @endif
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <form method="GET" class="space-y-3 py-4" data-submit-feedback data-search-history-form data-search-history-key="admin-ticket-filters">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($createdDateRange)
                    @if(empty($createdDateRange['month']))
                        <input type="hidden" name="created_from" value="{{ $createdDateRange['from'] }}">
                        <input type="hidden" name="created_to" value="{{ $createdDateRange['to'] }}">
                    @endif
                    @if(!empty($createdDateRange['label']) && empty($createdDateRange['month']))
                        <input type="hidden" name="report_scope" value="{{ $createdDateRange['label'] }}">
                    @endif
                @endif
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <div class="relative xl:col-span-2">
                        <label for="search" class="sr-only">Search</label>
                        <input
                            id="search"
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            data-search-history-input
                            class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                            placeholder="Search tickets"
                            autocomplete="off"
                        >
                        <div class="search-history-panel hidden" data-search-history-panel></div>
                    </div>

                    <div>
                        <label for="priority" class="sr-only">Severity</label>
                        <select id="priority" name="priority" data-text-transform="capitalize" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize">
                            <option value="all">All severities</option>
                            <option value="unassigned" {{ request('priority') === 'unassigned' ? 'selected' : '' }}>Pending review</option>
                            <option value="severity_1" {{ $selectedPriority === 'severity_1' ? 'selected' : '' }}>Severity 1</option>
                            <option value="severity_2" {{ $selectedPriority === 'severity_2' ? 'selected' : '' }}>Severity 2</option>
                            <option value="severity_3" {{ $selectedPriority === 'severity_3' ? 'selected' : '' }}>Severity 3</option>
                        </select>
                    </div>

                    <div>
                        <label for="category" class="sr-only">Category</label>
                        <select id="category" name="category" data-text-transform="capitalize" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize">
                            <option value="all">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assigned_to" class="sr-only">Assigned user</label>
                        <select id="assigned_to" name="assigned_to" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                            <option value="all">All assigned users</option>
                            <option value="0" {{ request('assigned_to') === '0' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($assignees as $assignee)
                                <option value="{{ $assignee->id }}" {{ (string) request('assigned_to') === (string) $assignee->id ? 'selected' : '' }}>
                                    {{ $assignee->publicDisplayName() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="account" class="sr-only">Account</label>
                        <select id="account" name="account_id" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                            <option value="all">All accounts</option>
                            @foreach($accountOptions as $account)
                                <option value="{{ $account->id }}" {{ (string) request('account_id') === (string) $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <label for="month" class="sr-only">Month</label>
                        <select id="month" name="month" data-text-transform="capitalize" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize">
                            <option value="">All months</option>
                            @foreach($monthOptions as $monthOption)
                                <option value="{{ $monthOption['value'] }}" {{ $selectedMonth === $monthOption['value'] ? 'selected' : '' }}>
                                    {{ $monthOption['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="province" class="sr-only">Province</label>
                        <select id="province" name="province" data-text-transform="capitalize" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize">
                            <option value="all">All provinces</option>
                            @foreach($provinceOptions as $province)
                                <option value="{{ $province }}" {{ request('province') === $province ? 'selected' : '' }}>
                                    {{ $province }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="municipality" class="sr-only">Municipality</label>
                        <select id="municipality" name="municipality" data-text-transform="capitalize" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20 capitalize">
                            <option value="all">All municipalities</option>
                            @foreach($municipalityOptions as $municipality)
                                <option value="{{ $municipality }}" {{ request('municipality') === $municipality ? 'selected' : '' }}>
                                    {{ $municipality }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2 xl:col-span-3 xl:justify-end">
                        @if($canDeleteTickets)
                            <button id="bulk-delete-submit" type="button" class="btn-danger h-10 px-4" disabled>Delete</button>
                        @endif
                        <a href="{{ route('admin.tickets.index', ['tab' => $tab]) }}" data-admin-ticket-clear class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                    </div>
                </div>
            </form>

            @if($canDeleteTickets)
                <form id="bulk-ticket-delete-form" method="POST" action="{{ route('admin.tickets.bulk-action') }}" class="hidden" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <div id="bulk-selected-ids"></div>
                </form>
            @endif
        </div>
        @include('admin.tickets.partials.results')
    </section>
</div>

<div id="assign-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="assign"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Assign Technical User</h3>
                <p id="assign-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="assign-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <div>
                    <span class="form-label">Technical Users</span>
                    <select
                        id="assign-modal-select"
                        name="assigned_to[]"
                        class="form-input mt-2"
                        multiple
                        data-enhanced-multiselect="1"
                        data-placeholder="Select technicians"
                    >
                        @foreach($assignees as $assignee)
                            <option value="{{ $assignee->id }}">{{ $assignee->publicDisplayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="assign">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="revert-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="revert"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Confirm Revert</h3>
                <p id="revert-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="revert-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <input type="hidden" name="status" value="in_progress">
                <p class="text-sm text-slate-600">This will move the ticket back to <strong>In Progress</strong>.</p>
                <label for="revert-confirm-checkbox" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input id="revert-confirm-checkbox" type="checkbox" class="ticket-checkbox mt-0.5" required>
                    <span>I confirm that this ticket should be reverted to In Progress.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="revert">Cancel</button>
                    <button id="revert-submit-btn" type="submit" class="btn-primary disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100" disabled>Confirm Revert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="edit-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="edit"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Edit Ticket</h3>
                <p id="edit-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="edit-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <div>
                    <span class="form-label">Technical Users</span>
                    <select
                        id="edit-modal-assigned"
                        name="assigned_to[]"
                        class="form-input mt-2"
                        multiple
                        data-enhanced-multiselect="1"
                        data-placeholder="Select technicians"
                    >
                        @foreach($assignees as $assignee)
                            <option value="{{ $assignee->id }}">{{ $assignee->publicDisplayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="edit-modal-status" class="form-label">Status</label>
                    <select id="edit-modal-status" name="status" class="form-input">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div id="edit-modal-close-reason-wrap" class="hidden">
                    <label for="edit-modal-close-reason" class="form-label">Close Reason <span class="text-rose-500">*</span></label>
                    <textarea
                        id="edit-modal-close-reason"
                        name="close_reason"
                        rows="3"
                        class="form-input"
                        placeholder="Provide a reason for closing this ticket..."
                    ></textarea>
                </div>
                <p id="edit-modal-close-hint" class="hidden text-xs text-amber-700"></p>
                <div>
                    <label for="edit-modal-priority" class="form-label">Severity</label>
                    <select id="edit-modal-priority" name="priority" class="form-input">
                        <option value="">Pending review</option>
                        <option value="severity_1">Severity 1</option>
                        <option value="severity_2">Severity 2</option>
                        <option value="severity_3">Severity 3</option>
                    </select>
                </div>
                @if($canManageTicketType)
                    <div>
                        <label for="edit-modal-ticket-type" class="form-label">Ticket Type</label>
                        <select id="edit-modal-ticket-type" name="ticket_type" class="form-input">
                            <option value="{{ \App\Models\Ticket::TYPE_EXTERNAL }}">External</option>
                            <option value="{{ \App\Models\Ticket::TYPE_INTERNAL }}">Internal</option>
                        </select>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3">
                    @if($canDeleteTickets)
                        <button type="button" id="edit-modal-delete-btn" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12m-1 0v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7m3-3h4a2 2 0 012 2v1H8V6a2 2 0 012-2z"></path>
                            </svg>
                            Delete Ticket
                        </button>
                    @else
                        <span class="text-xs font-semibold text-slate-500">Delete is restricted to admins.</span>
                    @endif
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" data-modal-close="edit">Cancel</button>
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@if($canDeleteTickets)
    <div id="delete-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
        <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete"></div>
        <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
            <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Delete Ticket</h3>
                    <p id="delete-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
                </div>
                <form id="delete-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                    <p class="text-sm text-slate-600">This action cannot be undone.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" data-modal-close="delete">Cancel</button>
                        <button type="submit" class="btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="bulk-delete-confirm-modal" class="app-modal-root fixed inset-0 z-50 hidden">
        <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="bulk-delete"></div>
        <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
            <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Delete Selected Tickets</h3>
                    <p class="mt-1 text-sm text-slate-500">This action cannot be undone.</p>
                </div>
                <div class="space-y-4 px-5 py-4">
                    <label for="bulk-delete-confirm-checkbox" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                        <input id="bulk-delete-confirm-checkbox" type="checkbox" class="ticket-checkbox mt-0.5">
                        <span>I understand that the selected tickets will be permanently deleted.</span>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" data-modal-close="bulk-delete">Cancel</button>
                        <button id="bulk-delete-confirm-submit" type="button" class="btn-danger disabled:cursor-not-allowed disabled:opacity-60" disabled>
                            Delete Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endif

@endsection
