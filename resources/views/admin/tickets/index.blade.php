@extends('layouts.app')

@section('title', 'Tickets - DICT Helpdesk')

@section('content')
@php
    $tab = $activeTab ?? 'tickets';
    $isHistoryTab = $tab === 'history';
    $viewedTicketIds = array_map('intval', session('admin_viewed_ticket_ids', []));
    $baseQuery = request()->except(['page', 'tab', 'selected_ids', 'action', 'assigned_to', 'status', 'priority']);
    $tabTicketsUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'tickets']));
    $tabAttentionUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'attention']));
    $tabHistoryUrl = route('admin.tickets.index', array_merge($baseQuery, ['tab' => 'history']));
@endphp
<div class="mx-auto max-w-[1460px]">
    <div class="mb-6">
        <h1 class="font-display text-4xl font-semibold text-slate-900">Tickets</h1>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200">
                <div class="flex items-center gap-7">
                    <a href="{{ $tabTicketsUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'tickets' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">Tickets</a>
                    <a href="{{ $tabAttentionUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'attention' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">Aging (16h+)</a>
                    <a href="{{ $tabHistoryUrl }}" class="border-b-[3px] pb-3 text-sm font-semibold {{ $tab === 'history' ? 'border-[#ff2f88] text-slate-900' : 'border-transparent text-slate-400 hover:text-slate-600' }}">History</a>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <div class="relative">
                        <select
                            id="admin-status-view"
                            name="status"
                            class="h-10 min-w-[140px] appearance-none rounded-xl border border-[#64b5a8] bg-white pl-4 pr-10 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                        >
                            <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Select view</option>
                            @if($isHistoryTab)
                                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
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

            <form method="GET" class="grid grid-cols-1 gap-3 py-4 md:grid-cols-2 xl:grid-cols-8">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="xl:col-span-2">
                    <label for="search" class="sr-only">Search</label>
                    <input
                        id="search"
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="h-10 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                        placeholder="Search tickets"
                    >
                </div>

                <div>
                    <label for="priority" class="sr-only">Priority</label>
                    <select id="priority" name="priority" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="all">All priorities</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>

                <div>
                    <label for="category" class="sr-only">Category</label>
                    <select id="category" name="category" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="all">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assigned_to" class="sr-only">Assigned To</label>
                    <select id="assigned_to" name="assigned_to" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="all">All technical users</option>
                        <option value="unassigned" {{ request('assigned_to') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ request('assigned_to') == $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="region" class="sr-only">Region</label>
                    <select id="region" name="region" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="all">All regions</option>
                        @foreach($regions as $region)
                            <option value="{{ $region }}" {{ request('region') === $region ? 'selected' : '' }}>
                                {{ $region }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="account" class="sr-only">Account</label>
                    <select id="account" name="account" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="all">All accounts</option>
                        @foreach($accountOptions as $account)
                            <option value="{{ $account }}" {{ request('account') === $account ? 'selected' : '' }}>
                                {{ $account }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-4 text-sm font-semibold text-white transition hover:brightness-95">Filter</button>
                    <a href="{{ route('admin.tickets.index', ['tab' => $tab]) }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        <div class="max-h-[70vh] overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 z-10 bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="w-10 px-6 py-4">
                            <input id="select-all-tickets" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0f8d88] focus:ring-[#0f8d88]/30">
                        </th>
                        <th class="px-6 py-4">Details</th>
                        <th class="px-6 py-4">Assigned Technical</th>
                        <th class="px-6 py-4">Priority</th>
                        @if($isHistoryTab)
                            <th class="px-6 py-4">Completed At</th>
                        @else
                            <th class="px-6 py-4">Activity Status</th>
                        @endif
                        <th class="px-6 py-4 text-right">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($tickets as $ticket)
                        @php
                            $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                                ? $ticket->created_at->diffForHumans()
                                : $ticket->created_at->format('M j, Y');
                            $isNew = $ticket->created_at->greaterThanOrEqualTo(now()->subDay()) && !in_array($ticket->id, $viewedTicketIds, true);
                            $completedAt = $ticket->closed_at ?? $ticket->resolved_at;
                        @endphp

                        <tr class="transition hover:bg-slate-50/90">
                            <td class="px-6 py-5 align-top">
                                <input type="checkbox" value="{{ $ticket->id }}" class="js-ticket-checkbox h-4 w-4 rounded border-slate-300 text-[#0f8d88] focus:ring-[#0f8d88]/30">
                            </td>

                            <td class="px-6 py-5 align-top">
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                                    <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                    <p class="mt-0.5 text-sm text-[#af9257]">{{ $ticket->category->name }} - {{ $ticket->user->name }}</p>
                                    <p class="mt-1 flex items-center gap-2 text-sm text-slate-500">
                                        <span>Created {{ $createdLabel }}</span>
                                        @if($isNew)
                                            <span class="inline-flex items-center rounded-full bg-[#e9fff6] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#067647]">New</span>
                                        @endif
                                    </p>
                                </a>
                            </td>

                            <td class="px-6 py-5 align-top text-sm text-slate-700">
                                @if($ticket->assignedUser)
                                    <button
                                        type="button"
                                        class="js-open-assign-modal font-medium text-[#0f8d88] hover:underline"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-ticket-number="{{ $ticket->ticket_number }}"
                                        data-assigned-to="{{ $ticket->assigned_to }}"
                                    >
                                        {{ $ticket->assignedUser->name }}
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        class="js-open-assign-modal font-medium text-[#b49252] hover:underline"
                                        data-ticket-id="{{ $ticket->id }}"
                                        data-ticket-number="{{ $ticket->ticket_number }}"
                                        data-assigned-to=""
                                    >
                                        Assign
                                    </button>
                                @endif
                            </td>

                            <td class="px-6 py-5 align-top">
                                <span class="inline-flex min-w-16 items-center justify-center rounded-md px-3 py-1 text-xs font-semibold {{ $ticket->priority_badge_class }}">
                                    {{ $ticket->priority_label }}
                                </span>
                            </td>

                            @if($isHistoryTab)
                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    {{ $completedAt ? $completedAt->format('M j, Y \a\t g:i A') : '-' }}
                                </td>
                            @else
                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                        <span class="h-2.5 w-2.5 rounded-full {{ $ticket->activity_dot_class }}"></span>
                                        {{ $ticket->activity_label }}
                                    </span>
                                </td>
                            @endif

                            <td class="px-6 py-5 text-right align-top">
                                <span class="inline-flex min-w-16 items-center justify-center rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                                    {{ $ticket->status_label }}
                                </span>
                            </td>

                            <td class="px-6 py-5 text-right align-top">
                                <button
                                    type="button"
                                    class="js-open-edit-modal inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    data-ticket-id="{{ $ticket->id }}"
                                    data-ticket-number="{{ $ticket->ticket_number }}"
                                    data-assigned-to="{{ $ticket->assigned_to }}"
                                    data-status="{{ $ticket->status }}"
                                    data-priority="{{ $ticket->priority }}"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isHistoryTab ? 7 : 7 }}" class="px-6 py-14 text-center">
                                <p class="text-base font-semibold text-slate-700">No tickets found</p>
                                <p class="mt-1 text-sm text-slate-500">Try adjusting your filters to broaden results.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->count() > 0)
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $tickets->appends(request()->query())->links() }}
            </div>
        @endif
    </section>
</div>

<div id="assign-ticket-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60" data-modal-overlay="assign"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Assign Technical User</h3>
                <p id="assign-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="assign-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <div>
                    <label for="assign-modal-select" class="form-label">Technical User</label>
                    <select id="assign-modal-select" name="assigned_to" class="form-input">
                        <option value="">Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
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

<div id="edit-ticket-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60" data-modal-overlay="edit"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Edit Ticket</h3>
                <p id="edit-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="edit-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <div>
                    <label for="edit-modal-assigned" class="form-label">Technical User</label>
                    <select id="edit-modal-assigned" name="assigned_to" class="form-input">
                        <option value="">Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
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
                <div>
                    <label for="edit-modal-priority" class="form-label">Priority</label>
                    <select id="edit-modal-priority" name="priority" class="form-input">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <button type="button" id="edit-modal-delete-btn" class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12m-1 0v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7m3-3h4a2 2 0 012 2v1H8V6a2 2 0 012-2z"></path>
                        </svg>
                        Delete Ticket
                    </button>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" data-modal-close="edit">Cancel</button>
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="delete-ticket-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60" data-modal-overlay="delete"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Delete Ticket</h3>
                <p id="delete-modal-ticket" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <form id="delete-ticket-form" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                @method('DELETE')
                <p class="text-sm text-slate-600">This action cannot be undone.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="delete">Cancel</button>
                    <button type="submit" class="btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusView = document.getElementById('admin-status-view');
    const selectAll = document.getElementById('select-all-tickets');
    const rowCheckboxes = Array.from(document.querySelectorAll('.js-ticket-checkbox'));
    const routeBase = @json(route('admin.tickets.index'));
    const assignForm = document.getElementById('assign-ticket-form');
    const assignModal = document.getElementById('assign-ticket-modal');
    const assignTicketText = document.getElementById('assign-modal-ticket');
    const assignSelect = document.getElementById('assign-modal-select');
    const editForm = document.getElementById('edit-ticket-form');
    const editModal = document.getElementById('edit-ticket-modal');
    const editTicketText = document.getElementById('edit-modal-ticket');
    const editAssignedSelect = document.getElementById('edit-modal-assigned');
    const editStatusSelect = document.getElementById('edit-modal-status');
    const editPrioritySelect = document.getElementById('edit-modal-priority');
    const editDeleteButton = document.getElementById('edit-modal-delete-btn');
    const deleteForm = document.getElementById('delete-ticket-form');
    const deleteModal = document.getElementById('delete-ticket-modal');
    const deleteTicketText = document.getElementById('delete-modal-ticket');
    const assignRouteTemplate = @json(route('admin.tickets.assign', ['ticket' => '__TICKET__']));
    const quickUpdateRouteTemplate = @json(route('admin.tickets.quick-update', ['ticket' => '__TICKET__']));
    const deleteRouteTemplate = @json(route('admin.tickets.destroy', ['ticket' => '__TICKET__']));

    if (statusView) {
        statusView.addEventListener('change', function () {
            const params = new URLSearchParams(window.location.search);
            params.set('status', statusView.value);
            params.delete('page');
            window.location.href = routeBase + '?' + params.toString();
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (!selectAll) return;
            selectAll.checked = rowCheckboxes.length > 0 && rowCheckboxes.every(function (item) { return item.checked; });
        });
    });

    const assignModalController = window.ModalKit ? window.ModalKit.bind(assignModal) : null;
    const editModalController = window.ModalKit ? window.ModalKit.bind(editModal) : null;
    const deleteModalController = window.ModalKit ? window.ModalKit.bind(deleteModal) : null;

    document.querySelectorAll('.js-open-assign-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const ticketId = button.dataset.ticketId;
            if (!ticketId || !assignForm) return;

            assignForm.action = assignRouteTemplate.replace('__TICKET__', ticketId);
            if (assignTicketText) {
                assignTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
            }
            if (assignSelect) {
                assignSelect.value = button.dataset.assignedTo || '';
            }
            if (assignModalController) assignModalController.open();
        });
    });

    document.querySelectorAll('.js-open-edit-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const ticketId = button.dataset.ticketId;
            if (!ticketId || !editForm) return;

            editForm.action = quickUpdateRouteTemplate.replace('__TICKET__', ticketId);
            if (editTicketText) {
                editTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
            }
            if (editAssignedSelect) editAssignedSelect.value = button.dataset.assignedTo || '';
            if (editStatusSelect) editStatusSelect.value = button.dataset.status || 'open';
            if (editPrioritySelect) editPrioritySelect.value = button.dataset.priority || 'medium';

            if (editDeleteButton) {
                editDeleteButton.dataset.ticketId = ticketId;
                editDeleteButton.dataset.ticketNumber = button.dataset.ticketNumber || '';
            }
            if (editModalController) editModalController.open();
        });
    });

    if (editDeleteButton) {
        editDeleteButton.addEventListener('click', function () {
            const ticketId = editDeleteButton.dataset.ticketId;
            if (!ticketId || !deleteForm) return;
            deleteForm.action = deleteRouteTemplate.replace('__TICKET__', ticketId);
            if (deleteTicketText) {
                deleteTicketText.textContent = 'Ticket #' + (editDeleteButton.dataset.ticketNumber || '');
            }
            if (editModalController) editModalController.close();
            if (deleteModalController) deleteModalController.open();
        });
    }
});
</script>
@endsection
