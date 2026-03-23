@extends('layouts.app')

@section('title', 'User Details - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-users-page data-users-base-url="{{ route('admin.users.index', absolute: false) }}">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">User Details</h1>
                    <p class="mt-1 text-sm text-gray-600">View user information and activity</p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <!-- User Information -->
        <div class="lg:col-span-8">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">User Information</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Personal details and account information</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Full name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email address</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone number</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->phone ?? 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Department</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->department ?? 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Role</dt>
                            @php
                                $displayRole = \App\Models\User::publicRoleValue($user->role);
                            @endphp
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($displayRole === 'admin') bg-indigo-100 text-indigo-800
                                    @elseif($displayRole === 'super_user') bg-blue-100 text-blue-800
                                    @elseif($displayRole === 'technical') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ \App\Models\User::publicRoleLabel($displayRole) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('F j, Y \a\t g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                        </div>
                        @if($user->isClient() && auth()->user()->isShadow())
                            <div class="sm:col-span-2 xl:col-span-3">
                                <dt class="text-sm font-medium text-gray-500">Client Notes</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if(filled($user->client_notes))
                                        {!! nl2br(e($user->client_notes)) !!}
                                    @else
                                        No notes added.
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="mt-6 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Tickets</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Latest tickets created or assigned to this user</p>
                </div>
                <div class="border-t border-gray-200">
                    <ul class="divide-y divide-gray-200">
                        @forelse($recentTickets as $ticket)
                            <li class="px-4 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center min-w-0">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->status_color }}">
                                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                            </span>
                                        </div>
                                        <div class="ml-4 min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="hover:text-indigo-600">
                                                    {{ $ticket->subject }}
                                                </a>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $ticket->ticket_number }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->priority_color }} mr-2">
                                            @if(strtolower($ticket->priority) === 'urgent')
                                                <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2.25m0 3.75h.01M10.34 3.94 1.82 18a2.25 2.25 0 001.92 3.38h16.52a2.25 2.25 0 001.92-3.38L13.66 3.94a2.25 2.25 0 00-3.32 0z"></path>
                                                </svg>
                                            @endif
                                            {{ ucfirst($ticket->priority) }}
                                        </span>
                                        <div class="text-sm text-gray-500">
                                            {{ $ticket->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No tickets</h3>
                                <p class="mt-1 text-sm text-gray-500">This user hasn't created any tickets yet.</p>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Sidebar -->
        <div class="space-y-6 lg:col-span-4">
            <!-- Statistics Card -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">User Statistics</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="space-y-4">
                        <div>
                            <a href="{{ $statisticsLinks['total_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                                <dt class="text-sm font-medium text-gray-500">Total Tickets</dt>
                                <dd class="text-sm text-gray-900">{{ $statistics['total_tickets'] }}</dd>
                            </a>
                        </div>
                        <div>
                            <a href="{{ $statisticsLinks['open_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                                <dt class="text-sm font-medium text-gray-500">Open Tickets</dt>
                                <dd class="text-sm text-gray-900">{{ $statistics['open_tickets'] }}</dd>
                            </a>
                        </div>
                        <div>
                            <a href="{{ $statisticsLinks['closed_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                                <dt class="text-sm font-medium text-gray-500">Closed Tickets</dt>
                                <dd class="text-sm text-gray-900">{{ $statistics['closed_tickets'] }}</dd>
                            </a>
                        </div>
                        @if($statistics['show_assigned'])
                            <div>
                                <a href="{{ $statisticsLinks['assigned_tickets'] }}" class="flex items-center justify-between rounded-md px-2 py-2 transition hover:bg-slate-50">
                                    <dt class="text-sm font-medium text-gray-500">Assigned Tickets</dt>
                                    <dd class="text-sm text-gray-900">{{ $statistics['assigned_tickets'] }}</dd>
                                </a>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            @if($canRevealManagedPassword ?? false)
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Password Access</h3>
                        <p class="mt-1 text-sm text-gray-500">Shadow-only credential tools for this account.</p>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-3">
                        @if(!empty($revealedManagedPassword))
                            <div>
                                <label for="managedUserPassword" class="text-sm font-medium text-gray-700">Temporary Login Password (One-Time Reveal)</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <input
                                        id="managedUserPassword"
                                        type="password"
                                        readonly
                                        value="{{ $revealedManagedPassword }}"
                                        class="block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-900"
                                    >
                                    <button
                                        type="button"
                                        id="toggleManagedUserPassword"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                        aria-pressed="false"
                                    >
                                        Show
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-rose-700">Copy this temporary password now. It will not be shown again.</p>
                            </div>
                        @elseif($activeCredentialHandoff)
                            <p class="text-xs text-sky-700">
                                A temporary password is ready and expires at {{ optional($activeCredentialHandoff->expires_at)->format('M j, Y g:i A') }}.
                            </p>
                            <form method="POST" action="{{ route('admin.users.password.reveal-temporary', $user) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-md border border-sky-300 px-3 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-50">
                                    Reveal Temporary Password
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-amber-700">No active temporary password is available for this account.</p>
                            <form method="POST" action="{{ route('admin.users.password.reset-default', $user) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">
                                    Issue Temporary Password
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Account Actions -->
            @if($user->id !== auth()->id())
                @php
                    $currentViewer = auth()->user();
                    $targetRole = $user->normalizedRole();
                    $canModifyProtectedRole = $currentViewer->isShadow() || $targetRole !== \App\Models\User::ROLE_ADMIN;
                    $canToggleAccount = $targetRole !== \App\Models\User::ROLE_SHADOW && $canModifyProtectedRole;
                    $canDeleteAccount = $targetRole !== \App\Models\User::ROLE_SHADOW
                        && $canModifyProtectedRole
                        && ! $user->is_profile_locked;
                @endphp
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Account Actions</h3>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="space-y-3">
                            @if($canToggleAccount)
                                <button
                                        type="button"
                                        class="js-toggle-user-status w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-new-status="{{ $user->is_active ? '0' : '1' }}">
                                    {{ $user->is_active ? 'Deactivate Account' : 'Activate Account' }}
                                </button>
                            @endif
                            @if($canDeleteAccount)
                                <button
                                        type="button"
                                        class="delete-user-btn w-full text-left px-3 py-2 text-sm text-red-700 hover:bg-red-50 rounded-md"
                                        data-user-id="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}">
                                    Delete Account
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Account Status Modal -->
<div id="statusConfirmModal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="status"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
    <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-6 w-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 id="statusModalTitle" class="mt-2 text-lg font-medium text-gray-900">Deactivate Account</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    <span id="statusPromptText">Are you sure you want to deactivate</span> <strong id="statusTargetUserName"></strong>?
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="statusConfirmCheckbox" type="checkbox" class="mr-2 ticket-checkbox">
                    <span id="statusCheckboxText">I understand this user will not be able to sign in.</span>
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmStatusChange" type="button" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-60" disabled>
                    Deactivate Account
                </button>
                <button id="cancelStatusChange" type="button" class="btn-secondary mt-3 w-full">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Action Notification -->
<div id="actionNotification" class="fixed right-4 top-4 z-[70] hidden max-w-sm rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-lg">
    <p id="actionNotificationMessage"></p>
</div>

<!-- Deactivate User Modal -->
<div id="deleteModal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
    <div class="app-modal-panel relative mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete <strong id="deleteUserName"></strong>? Ticket history will be preserved.
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="deleteConfirmCheckbox" type="checkbox" required aria-required="true" class="mr-2 ticket-checkbox">
                    I understand this action is permanent.
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" disabled class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-60">
                    Delete
                </button>
                <button id="cancelDelete" type="button" class="btn-secondary mt-3 w-full">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

@endsection


