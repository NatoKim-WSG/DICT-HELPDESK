@extends('layouts.app')

@section('title', 'Dashboard - DICT | iOne Resources Ticketing')

@section('content')
<div class="app-shell mx-auto">
    <section class="hero-glow panel mb-8 animate-fade-in-up px-6 py-6 sm:px-8">
        <h1 class="font-display text-3xl font-semibold text-slate-900">Welcome back, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Track your support requests and create new tickets quickly.</p>
    </section>

    <div class="stagger-fade mb-8 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tickets</p>
                    <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['total_tickets'] }}</p>
                </div>
                <div class="rounded-xl bg-ione-blue-100 p-3 text-ione-blue-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Tickets</p>
                    <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['open_tickets'] }}</p>
                </div>
                <div class="rounded-xl bg-emerald-100 p-3 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Closed Tickets</p>
                    <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['closed_tickets'] }}</p>
                </div>
                <div class="rounded-xl bg-slate-200 p-3 text-slate-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Urgent Tickets</p>
                    <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['urgent_tickets'] }}</p>
                </div>
                <div class="rounded-xl bg-rose-100 p-3 text-rose-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <div class="panel mb-8 px-5 py-5 sm:px-6">
        <h3 class="font-display text-lg font-semibold text-slate-900">Quick Actions</h3>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('client.tickets.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create New Ticket
            </a>
            <a href="{{ route('client.tickets.index') }}" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                View All Tickets
            </a>
        </div>
    </div>

    <div class="panel overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
            <h3 class="font-display text-xl font-semibold text-slate-900">Recent Tickets</h3>
            <p class="mt-1 text-sm text-slate-500">Your most recent support requests.</p>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($recentTickets as $ticket)
                <li>
                    <a href="{{ route('client.tickets.show', $ticket) }}" class="block px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ticket->status_color }}">
                                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ $ticket->ticket_number }}</span>
                                </div>
                                <p class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $ticket->category->name }}</p>
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
                    <p class="mt-1 text-sm text-slate-500">Get started by creating your first support ticket.</p>
                    <div class="mt-6">
                        <a href="{{ route('client.tickets.create') }}" class="btn-primary">
                            Create New Ticket
                        </a>
                    </div>
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
