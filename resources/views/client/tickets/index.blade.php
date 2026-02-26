@extends('layouts.app')

@section('title', 'My Tickets - DICT Helpdesk')

@section('content')
<div class="mx-auto max-w-[1460px]">
    <div class="mb-6">
        <h1 class="font-display text-4xl font-semibold text-slate-900">My Tickets</h1>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 sm:px-6">
            <div class="border-b border-slate-200 pb-3">
                <h2 class="text-sm font-semibold text-slate-900">Tickets</h2>
            </div>

            <form method="GET" class="grid grid-cols-1 gap-3 py-4 md:grid-cols-4" data-submit-feedback data-search-history-form data-search-history-key="client-ticket-filters">
                <div class="relative md:col-span-2">
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
                    <label for="status" class="sr-only">Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="">All statuses</option>
                        <option value="open_group" {{ request('status') === 'open_group' ? 'selected' : '' }}>Open (All Active)</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                <div>
                    <label for="priority" class="sr-only">Priority</label>
                    <select id="priority" name="priority" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="">All priorities</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 md:col-span-4">
                    <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-3 text-sm font-semibold text-white transition hover:brightness-95" data-loading-text="Filtering...">Filter</button>
                    <a href="{{ route('client.tickets.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        <div class="space-y-3 px-4 pb-4 md:hidden">
            @forelse($tickets as $ticket)
                @php
                    $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                        ? $ticket->created_at->diffForHumans()
                        : $ticket->created_at->format('M j, Y');
                @endphp
                <a href="{{ route('client.tickets.show', $ticket) }}" class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                    <p class="mt-1 text-xs text-[#af9257]">{{ $ticket->category->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Created {{ $createdLabel }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                        <div>
                            <span class="block text-[11px] uppercase tracking-wide text-slate-400">Assigned</span>
                            <span>{{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}</span>
                        </div>
                        <div>
                            <span class="block text-[11px] uppercase tracking-wide text-slate-400">Activity</span>
                            <span>{{ $ticket->activity_label }}</span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="inline-flex min-w-16 items-center justify-center rounded-md px-2.5 py-1 text-[11px] font-semibold {{ $ticket->priority_badge_class }}">
                            {{ $ticket->priority_label }}
                        </span>
                        <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                            {{ $ticket->status_label }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-10 text-center">
                    <p class="text-base font-semibold text-slate-700">No tickets found</p>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your filters.</p>
                </div>
            @endforelse
        </div>

        <div class="hidden max-h-[70vh] overflow-auto md:block">
            <table class="min-w-full table-fixed divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 z-10 bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="w-[40%] px-6 py-4">Details</th>
                        <th class="w-[18%] px-6 py-4">Assigned Technical</th>
                        <th class="w-[12%] px-6 py-4 text-center">Priority</th>
                        <th class="w-[20%] px-6 py-4 text-center">Activity Status</th>
                        <th class="w-[10%] px-6 py-4 text-center">Status</th>
                    </tr>
                </thead>

                <tbody class="app-table-body divide-y divide-slate-200 bg-white">
                    @forelse($tickets as $ticket)
                        @php
                            $createdLabel = $ticket->created_at->greaterThan(now()->subDay())
                                ? $ticket->created_at->diffForHumans()
                                : $ticket->created_at->format('M j, Y');
                        @endphp

                        <tr class="client-ticket-row transition">
                            <td class="px-6 py-5 align-top">
                                <a href="{{ route('client.tickets.show', $ticket) }}" class="block">
                                    <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                    <p class="mt-0.5 text-sm text-[#af9257]">{{ $ticket->category->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Created {{ $createdLabel }}
                                    </p>
                                </a>
                            </td>

                            <td class="px-6 py-5 align-top text-sm text-slate-700">
                                {{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}
                            </td>

                            <td class="px-6 py-5 text-center align-top">
                                <span class="inline-flex min-w-16 items-center justify-center rounded-md px-3 py-1 text-xs font-semibold {{ $ticket->priority_badge_class }}">
                                    {{ $ticket->priority_label }}
                                </span>
                            </td>

                            <td class="px-6 py-5 text-center align-top">
                                <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $ticket->activity_dot_class }}"></span>
                                    {{ $ticket->activity_label }}
                                </span>
                            </td>

                            <td class="px-6 py-5 text-center align-top">
                                <span class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $ticket->status_badge_class }}">
                                    {{ $ticket->status_label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <p class="text-base font-semibold text-slate-700">No tickets found</p>
                                <p class="mt-1 text-sm text-slate-500">Try adjusting your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->count() > 0)
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $tickets->links() }}
            </div>
        @endif
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialSnapshotToken = @json($liveSnapshotToken ?? '');
    if (!initialSnapshotToken) return;

    const routeBase = @json(route('client.tickets.index'));
    let snapshotToken = initialSnapshotToken;

    const pollTicketListSnapshot = async function () {
        if (document.hidden) return;

        const params = new URLSearchParams(window.location.search);
        params.set('heartbeat', '1');

        try {
            const response = await fetch(routeBase + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== snapshotToken) {
                window.location.reload();
                return;
            }

            snapshotToken = payload.token;
        } catch (error) {
        }
    };

    window.setInterval(pollTicketListSnapshot, 10000);
});
</script>
@endsection
