@extends('layouts.app')

@section('title', 'My Tickets - iOne Resources Ticketing')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">My Tickets</h1>
                <p class="mt-1 text-sm text-gray-600">View and manage your support tickets</p>
            </div>
            <a href="{{ route('client.tickets.create') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Ticket
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           class="form-input" placeholder="Search tickets...">
                </div>

                <div>
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-input">
                        <option value="">All Statuses</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>

                <div>
                    <label for="priority" class="form-label">Priority</label>
                    <select name="priority" id="priority" class="form-input">
                        <option value="">All Priorities</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn-primary mr-2">Filter</button>
                    <a href="{{ route('client.tickets.index') }}" class="btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets List -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        @if($tickets->count() > 0)
            <ul class="divide-y divide-gray-200">
                @foreach($tickets as $ticket)
                    <li>
                        <a href="{{ route('client.tickets.show', $ticket) }}" class="block hover:bg-gray-50 px-4 py-4 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->status_color }}">
                                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                        </span>
                                    </div>
                                    <div class="ml-4 min-w-0 flex-1">
                                        <div class="text-sm font-medium text-gray-900 truncate">
                                            {{ $ticket->subject }}
                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center mt-1">
                                            <span>{{ $ticket->ticket_number }}</span>
                                            <span class="mx-2">•</span>
                                            <span>{{ $ticket->category->name }}</span>
                                            @if($ticket->assignedUser)
                                                <span class="mx-2">•</span>
                                                <span>Assigned to {{ $ticket->assignedUser->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->priority_color }} mr-3">
                                        {{ ucfirst($ticket->priority) }}
                                    </span>
                                    <div class="text-sm text-gray-500 text-right">
                                        <div>{{ $ticket->created_at->format('M j, Y') }}</div>
                                        <div>{{ $ticket->created_at->diffForHumans() }}</div>
                                    </div>
                                    <svg class="ml-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $tickets->links() }}
            </div>
        @else
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tickets found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if(request()->hasAny(['search', 'status', 'priority']))
                        No tickets match your current filters.
                    @else
                        You haven't created any support tickets yet.
                    @endif
                </p>
                <div class="mt-6">
                    @if(request()->hasAny(['search', 'status', 'priority']))
                        <a href="{{ route('client.tickets.index') }}" class="btn-secondary mr-3">
                            Clear Filters
                        </a>
                    @endif
                    <a href="{{ route('client.tickets.create') }}" class="btn-primary">
                        Create New Ticket
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection