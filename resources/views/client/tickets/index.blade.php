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

            <form method="GET" class="grid grid-cols-1 gap-3 py-4 md:grid-cols-4">
                <div class="md:col-span-2">
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
                    <label for="status" class="sr-only">Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                        <option value="">All statuses</option>
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
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 md:col-span-4">
                    <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-4 text-sm font-semibold text-white transition hover:brightness-95">Filter</button>
                    <a href="{{ route('client.tickets.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
                </div>
            </form>
        </div>

        <div class="max-h-[70vh] overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 z-10 bg-[#fafbfc] text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
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
                                <a href="{{ route('client.tickets.show', $ticket) }}" class="block">
                                    <p class="truncate text-base font-semibold text-slate-900">({{ $ticket->ticket_number }}) {{ $ticket->subject }}</p>
                                    <p class="mt-0.5 text-sm text-[#af9257]">{{ $ticket->category->name }}</p>
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
                                {{ $ticket->assignedUser?->name ?? 'Unassigned' }}
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
                            <td colspan="6" class="px-6 py-14 text-center">
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
@endsection
