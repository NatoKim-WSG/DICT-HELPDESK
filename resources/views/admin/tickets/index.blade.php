@extends('layouts.app')

@section('title', 'Tickets - DICT Helpdesk')

@section('content')
<div class="mx-auto max-w-[1460px]">
    <div class="mb-6">
        <h1 class="font-display text-4xl font-semibold text-slate-900">Tickets</h1>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 pt-4 sm:px-6">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200">
                <div class="flex items-center gap-7">
                    <button type="button" class="border-b-[3px] border-[#ff2f88] pb-3 text-sm font-semibold text-slate-900">Tickets</button>
                    <button type="button" class="border-b-[3px] border-transparent pb-3 text-sm font-semibold text-slate-400">Scheduled Tickets</button>
                </div>

                <div class="mb-3 flex items-center gap-3">
                    <select
                        name="status"
                        onchange="window.location.href='{{ route('admin.tickets.index') }}?status=' + this.value + '&priority={{ request('priority', 'all') }}&category={{ request('category', 'all') }}&assigned_to={{ request('assigned_to', 'all') }}&search={{ urlencode(request('search', '')) }}'"
                        class="h-10 rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                    >
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Select view</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-5 border-b border-slate-200 py-3 text-sm text-slate-400">
                <span class="inline-flex items-center gap-2"><span class="h-4 w-4 rounded border border-slate-300"></span> Delete</span>
                <span class="inline-flex items-center gap-2">Assign tickets</span>
                <span class="inline-flex items-center gap-2">Set status</span>
                <span class="inline-flex items-center gap-2">Set priority</span>
                <span class="inline-flex items-center gap-2">Merge tickets</span>
            </div>

            <form method="GET" class="grid grid-cols-1 gap-3 py-4 md:grid-cols-2 xl:grid-cols-6">
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
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
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
                        <option value="all">All technicians</option>
                        <option value="unassigned" {{ request('assigned_to') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ request('assigned_to') == $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-4 text-sm font-semibold text-white transition hover:brightness-95">Filter</button>
                    <a href="{{ route('admin.tickets.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="w-10 px-6 py-4">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0f8d88] focus:ring-[#0f8d88]/30">
                        </th>
                        <th class="px-6 py-4">Details</th>
                        <th class="px-6 py-4">SLA</th>
                        <th class="px-6 py-4">Assigned Technician</th>
                        <th class="px-6 py-4">Priority</th>
                        <th class="px-6 py-4">Activity Status</th>
                        <th class="px-6 py-4 text-right">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($tickets as $ticket)
                        @php
                            $hoursToDue = $ticket->due_date ? now()->diffInHours($ticket->due_date, false) : null;
                            if (is_null($hoursToDue)) {
                                $slaLabel = 'N/A';
                                $slaClass = 'bg-slate-100 text-slate-500';
                            } elseif ($hoursToDue < 0) {
                                $slaLabel = '-' . abs($hoursToDue) . 'H';
                                $slaClass = 'bg-red-500 text-white';
                            } elseif ($hoursToDue <= 2) {
                                $slaLabel = $hoursToDue . 'H';
                                $slaClass = 'bg-amber-400 text-white';
                            } else {
                                $slaLabel = $hoursToDue . 'H';
                                $slaClass = 'bg-emerald-500 text-white';
                            }

                            $priorityLabel = in_array($ticket->priority, ['urgent', 'high']) ? 'Critical' : ucfirst($ticket->priority);
                            $activityDot = match($ticket->status) {
                                'pending' => 'bg-emerald-500',
                                'in_progress' => 'bg-sky-500',
                                'resolved', 'closed' => 'bg-slate-400',
                                default => 'bg-amber-400',
                            };

                            $activityText = match($ticket->status) {
                                'pending' => 'Awaiting customer response',
                                'in_progress' => 'In progress',
                                'resolved', 'closed' => 'Read',
                                default => 'Unread',
                            };

                            $statusClass = match($ticket->status) {
                                'open' => 'bg-[#00494b] text-white',
                                'pending' => 'bg-amber-400 text-white',
                                'in_progress' => 'bg-sky-500 text-white',
                                'resolved' => 'bg-emerald-500 text-white',
                                'closed' => 'bg-slate-500 text-white',
                                default => 'bg-slate-400 text-white',
                            };
                        @endphp

                        <tr class="transition hover:bg-slate-50/90">
                            <td class="px-6 py-5 align-top">
                                <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#0f8d88] focus:ring-[#0f8d88]/30">
                            </td>

                            <td class="px-6 py-5 align-top">
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block">
                                    <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                    <p class="mt-0.5 text-sm text-[#af9257]">{{ $ticket->category->name }} - {{ $ticket->user->name }}</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Created {{ $ticket->created_at->diffForHumans() }}
                                        @if($ticket->updated_at->ne($ticket->created_at))
                                            - Modified {{ $ticket->updated_at->diffForHumans() }}
                                        @endif
                                    </p>
                                </a>
                            </td>

                            <td class="px-6 py-5 align-top">
                                <span class="inline-flex min-w-12 items-center justify-center rounded-md px-2 py-1 text-xs font-semibold {{ $slaClass }}">
                                    {{ $slaLabel }}
                                </span>
                            </td>

                            <td class="px-6 py-5 align-top text-sm text-slate-700">
                                @if($ticket->assignedUser)
                                    {{ $ticket->assignedUser->name }}
                                @else
                                    <span class="text-[#b49252]">Assign</span>
                                @endif
                            </td>

                            <td class="px-6 py-5 align-top text-sm text-slate-700">{{ $priorityLabel }}</td>

                            <td class="px-6 py-5 align-top">
                                <span class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $activityDot }}"></span>
                                    {{ $activityText }}
                                </span>
                            </td>

                            <td class="px-6 py-5 text-right align-top">
                                <span class="inline-flex min-w-16 items-center justify-center rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClass }}">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center">
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
@endsection
