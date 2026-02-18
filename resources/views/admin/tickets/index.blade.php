@extends('layouts.app')

@section('title', 'All Tickets - iOne Resources Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">All Tickets</h1>
                <p class="mt-1 text-sm text-gray-600">Manage all support tickets in the system</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.dashboard') }}" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 13-3 3-3-3"/>
                    </svg>
                    Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           class="form-input" placeholder="Search tickets...">
                </div>

                <div>
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-input">
                        <option value="all">All Statuses</option>
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
                        <option value="all">All Priorities</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    </select>
                </div>

                <div>
                    <label for="category" class="form-label">Category</label>
                    <select name="category" id="category" class="form-input">
                        <option value="all">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assigned_to" class="form-label">Assigned To</label>
                    <select name="assigned_to" id="assigned_to" class="form-input">
                        <option value="all">All Admins</option>
                        <option value="unassigned" {{ request('assigned_to') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{ request('assigned_to') == $agent->id ? 'selected' : '' }}>
                                {{ $agent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end col-span-full">
                    <button type="submit" class="btn-primary mr-2">Filter</button>
                    <a href="{{ route('admin.tickets.index') }}" class="btn-secondary">Clear</a>
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
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="block hover:bg-gray-50 px-4 py-4 sm:px-6">
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
                                            <span>{{ $ticket->user->name }} ({{ $ticket->user->email }})</span>
                                            <span class="mx-2">•</span>
                                            <span>{{ $ticket->category->name }}</span>
                                            @if($ticket->assignedUser)
                                                <span class="mx-2">•</span>
                                                <span>Assigned to {{ $ticket->assignedUser->name }}</span>
                                            @else
                                                <span class="mx-2">•</span>
                                                <span class="text-red-600">Unassigned</span>
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
                                        @if($ticket->due_date)
                                            <div class="text-xs {{ $ticket->due_date->isPast() ? 'text-red-600' : 'text-yellow-600' }}">
                                                Due: {{ $ticket->due_date->format('M j') }}
                                            </div>
                                        @endif
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
                {{ $tickets->appends(request()->query())->links() }}
            </div>
        @else
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tickets found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if(request()->hasAny(['search', 'status', 'priority', 'category', 'assigned_to']) && !request()->query->every(fn($value) => in_array($value, ['all', ''])))
                        No tickets match your current filters.
                    @else
                        No support tickets have been created yet.
                    @endif
                </p>
                <div class="mt-6">
                    @if(request()->hasAny(['search', 'status', 'priority', 'category', 'assigned_to']) && !request()->query->every(fn($value) => in_array($value, ['all', ''])))
                        <a href="{{ route('admin.tickets.index') }}" class="btn-secondary">
                            Clear Filters
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection