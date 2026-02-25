@extends('layouts.app')

@section('title', ($dashboardTitle ?? 'Support Dashboard') . ' - iOne Resources Inc.')

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="panel mb-8 px-5 py-5 sm:px-6">
        <h3 class="font-display text-lg font-semibold text-slate-900">Quick Actions</h3>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('admin.tickets.index') }}" class="btn-primary">View All Tickets</a>
            <a href="{{ route('admin.tickets.index', ['tab' => 'attention']) }}" class="btn-warning">Needs Attention</a>
            <a href="{{ route('admin.tickets.index', ['priority' => 'urgent']) }}" class="btn-danger">Urgent Tickets</a>
            @if(!$isTechnical)
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Manage Users</a>
            @endif
        </div>
    </div>

    <div class="stagger-fade mb-8 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tickets</p>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['total_tickets'] }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Tickets</p>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['open_tickets'] }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Needs Attention</p>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.7 17.5A1.5 1.5 0 0 0 4 19.8h16a1.5 1.5 0 0 0 1.3-2.3L13.7 3.9a1.5 1.5 0 0 0-2.6 0Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-display text-3xl font-semibold text-amber-600">{{ $stats['attention_tickets'] }}</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Urgent Tickets</p>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M8.25 6.75h7.5m-7.5 10.5h7.5M4.5 12A7.5 7.5 0 1 0 19.5 12a7.5 7.5 0 0 0-15 0Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-display text-3xl font-semibold text-rose-600">{{ $stats['urgent_tickets'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <h3 class="font-display text-xl font-semibold text-slate-900">Recent Tickets</h3>
                    <p class="mt-1 text-sm text-slate-500">Latest support requests from users.</p>
                </div>
                <div class="overflow-y-auto" style="max-height: 525px;">
                    <ul class="divide-y divide-slate-100">
                        @forelse($recentTickets as $ticket)
                            <li>
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="block px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->status_color }}">
                                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                                </span>
                                                <span class="text-xs text-slate-500">{{ $ticket->ticket_number }}</span>
                                            </div>
                                            <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                            <p class="mt-1 truncate text-sm text-slate-500">{{ $ticket->user->name }} - {{ $ticket->category->name }}</p>
                                        </div>
                                        <div class="flex items-center gap-2 self-start sm:self-auto">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->priority_color }}">
                                                {{ ucfirst($ticket->priority) }}
                                            </span>
                                            <span class="text-xs text-slate-500">{{ $ticket->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-12 text-center sm:px-6">
                                <h3 class="text-sm font-semibold text-slate-900">No tickets yet</h3>
                                <p class="mt-1 text-sm text-slate-500">No support tickets have been created.</p>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="font-display text-lg font-semibold text-slate-900">Tickets by Status</h3>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach(['open', 'in_progress', 'pending', 'resolved', 'closed'] as $status)
                        @php
                            $statusFilter = in_array($status, ['resolved', 'closed'], true)
                                ? ['tab' => 'history', 'status' => $status]
                                : ['tab' => 'tickets', 'status' => $status];
                        @endphp
                        <a href="{{ route('admin.tickets.index', $statusFilter) }}" class="group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600 transition group-hover:text-slate-700">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $ticketsByStatus->get($status, 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="font-display text-lg font-semibold text-slate-900">Tickets by Priority</h3>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach(['urgent', 'high', 'medium', 'low'] as $priority)
                        <a href="{{ route('admin.tickets.index', ['tab' => 'tickets', 'priority' => $priority]) }}" class="group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600 transition group-hover:text-slate-700">{{ ucfirst($priority) }}</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $ticketsByPriority->get($priority, 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if(auth()->user()->isSuperAdmin())
                <div class="panel">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="font-display text-lg font-semibold text-slate-900">System Snapshot</h3>
                    </div>
                    <dl class="space-y-3 px-5 py-4">
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Assigned To Me</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $stats['assigned_to_me'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Client Users</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $stats['total_users'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Support Staff</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $stats['total_staff'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Closed Tickets</dt>
                            <dd class="text-sm font-semibold text-slate-900">{{ $stats['closed_tickets'] }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const initialToken = @json($liveSnapshotToken ?? '');
    if (!initialToken) return;

    const heartbeatUrl = new URL(@json(route('admin.dashboard')), window.location.origin);
    heartbeatUrl.searchParams.set('heartbeat', '1');
    let activeToken = initialToken;
    let checking = false;

    const pollSnapshot = async function () {
        if (checking || document.hidden) return;
        checking = true;

        try {
            const response = await fetch(heartbeatUrl.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;
            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== activeToken) {
                window.location.reload();
                return;
            }

            activeToken = payload.token;
        } catch (error) {
        } finally {
            checking = false;
        }
    };

    window.setInterval(pollSnapshot, 10000);
});
</script>
@endsection
