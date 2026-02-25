@extends('layouts.app')

@section('title', 'User Details - iOne Resources Inc.')

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
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
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($user->role === 'super_admin') bg-purple-100 text-purple-800
                                    @elseif($user->role === 'super_user') bg-blue-100 text-blue-800
                                    @elseif($user->role === 'technical') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $user->role)) }}
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

            <!-- Account Actions -->
            @if($user->id !== auth()->id())
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Account Actions</h3>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="space-y-3">
                            <button onclick="toggleUserStatus({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }}, @js($user->name))"
                                    class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">
                                {{ $user->is_active ? 'Deactivate Account' : 'Activate Account' }}
                            </button>
                            @if(!$user->isSuperAdmin())
                                <button onclick="deleteUser({{ $user->id }})"
                                        class="w-full text-left px-3 py-2 text-sm text-red-700 hover:bg-red-50 rounded-md">
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
<div id="statusConfirmModal" class="fixed inset-0 z-50 hidden bg-gray-600 bg-opacity-50">
    <div class="flex min-h-full items-center justify-center p-4">
    <div class="relative mx-auto w-full max-w-md rounded-md border bg-white p-5 shadow-lg">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100">
                <svg class="h-6 w-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 id="statusModalTitle" class="text-lg font-medium text-gray-900 mt-2">Deactivate Account</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    <span id="statusPromptText">Are you sure you want to deactivate</span> <strong id="statusTargetUserName"></strong>?
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="statusConfirmCheckbox" type="checkbox" class="mr-2 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span id="statusCheckboxText">I understand this user will not be able to sign in.</span>
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmStatusChange" type="button" class="inline-flex w-full items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">
                    Deactivate Account
                </button>
                <button id="cancelStatusChange" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Action Notification -->
<div id="actionNotification" class="fixed z-[70] hidden max-w-sm rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-lg" style="top: 16px; right: 16px; bottom: auto; left: auto;">
    <p id="actionNotificationMessage"></p>
</div>

<!-- Deactivate User Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="flex min-h-full items-center justify-center p-4">
    <div class="relative mx-auto w-full max-w-md rounded-md border bg-white p-5 shadow-lg">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-2">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete this user? Ticket history will be preserved.
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="deleteConfirmCheckbox" type="checkbox" required aria-required="true" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    I understand this action is permanent.
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" disabled class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 disabled:cursor-not-allowed disabled:opacity-60">
                    Delete
                </button>
                <button onclick="closeDeleteModal()" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<script>
const deleteConfirmCheckbox = document.getElementById('deleteConfirmCheckbox');
const confirmDeleteButton = document.getElementById('confirmDelete');
const statusConfirmCheckbox = document.getElementById('statusConfirmCheckbox');
const confirmStatusButton = document.getElementById('confirmStatusChange');
const statusModal = document.getElementById('statusConfirmModal');
const statusNameSpan = document.getElementById('statusTargetUserName');
const statusModalTitle = document.getElementById('statusModalTitle');
const statusPromptText = document.getElementById('statusPromptText');
const statusCheckboxText = document.getElementById('statusCheckboxText');
const actionNotification = document.getElementById('actionNotification');
const actionNotificationMessage = document.getElementById('actionNotificationMessage');
let pendingStatusChange = null;
let actionNotificationTimeout = null;

function setStatusConfirmButtonTheme(isReactivation) {
    if (!confirmStatusButton) return;

    confirmStatusButton.classList.remove(
        'bg-amber-600', 'hover:bg-amber-700', 'focus:ring-amber-300',
        'bg-emerald-600', 'hover:bg-emerald-700', 'focus:ring-emerald-300'
    );

    if (isReactivation) {
        confirmStatusButton.classList.add('bg-emerald-600', 'hover:bg-emerald-700', 'focus:ring-emerald-300');
    } else {
        confirmStatusButton.classList.add('bg-amber-600', 'hover:bg-amber-700', 'focus:ring-amber-300');
    }
}

function showActionNotification(message, tone = 'warning') {
    if (!actionNotification || !actionNotificationMessage) return;

    actionNotificationMessage.textContent = message;
    actionNotification.classList.remove('hidden');

    actionNotification.classList.remove('border-amber-200', 'bg-amber-50', 'text-amber-800');
    actionNotification.classList.remove('border-red-200', 'bg-red-50', 'text-red-700');

    if (tone === 'error') {
        actionNotification.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
    } else {
        actionNotification.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
    }

    if (actionNotificationTimeout) clearTimeout(actionNotificationTimeout);
    actionNotificationTimeout = setTimeout(function() {
        actionNotification.classList.add('hidden');
    }, 2600);
}

function deleteUser(userId) {
    document.getElementById('deleteModal').classList.remove('hidden');

    if (deleteConfirmCheckbox) {
        deleteConfirmCheckbox.checked = false;
    }
    if (confirmDeleteButton) {
        confirmDeleteButton.disabled = true;
    }

    document.getElementById('confirmDelete').onclick = function() {
        if (!deleteConfirmCheckbox || !deleteConfirmCheckbox.checked) {
            showActionNotification('Please check the confirmation box before deleting this account.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/users/' + userId;

        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';

        const tokenField = document.createElement('input');
        tokenField.type = 'hidden';
        tokenField.name = '_token';
        tokenField.value = '{{ csrf_token() }}';

        form.appendChild(methodField);
        form.appendChild(tokenField);
        document.body.appendChild(form);
        form.submit();
    };
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    if (deleteConfirmCheckbox) {
        deleteConfirmCheckbox.checked = false;
    }
    if (confirmDeleteButton) {
        confirmDeleteButton.disabled = true;
    }
}

if (deleteConfirmCheckbox && confirmDeleteButton) {
    deleteConfirmCheckbox.addEventListener('change', function() {
        confirmDeleteButton.disabled = !deleteConfirmCheckbox.checked;
    });
}

function openStatusModal(userId, userName, newStatus) {
    pendingStatusChange = { userId, newStatus };
    const isReactivation = Boolean(newStatus);

    if (statusNameSpan) {
        statusNameSpan.textContent = userName || 'this user';
    }
    if (statusModalTitle) {
        statusModalTitle.textContent = isReactivation ? 'Reactivate Account' : 'Deactivate Account';
    }
    if (statusPromptText) {
        statusPromptText.textContent = isReactivation
            ? 'Are you sure you want to reactivate'
            : 'Are you sure you want to deactivate';
    }
    if (statusCheckboxText) {
        statusCheckboxText.textContent = isReactivation
            ? 'I understand this user will be able to sign in again.'
            : 'I understand this user will not be able to sign in.';
    }
    if (statusConfirmCheckbox) {
        statusConfirmCheckbox.checked = false;
        statusConfirmCheckbox.className = isReactivation
            ? 'mr-2 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500'
            : 'mr-2 rounded border-gray-300 text-amber-600 focus:ring-amber-500';
    }
    if (confirmStatusButton) {
        confirmStatusButton.textContent = isReactivation ? 'Reactivate Account' : 'Deactivate Account';
        confirmStatusButton.disabled = false;
        setStatusConfirmButtonTheme(isReactivation);
    }
    if (statusModal) {
        statusModal.classList.remove('hidden');
    }
}

function closeStatusModal() {
    pendingStatusChange = null;

    if (statusConfirmCheckbox) {
        statusConfirmCheckbox.checked = false;
    }
    if (statusModal) {
        statusModal.classList.add('hidden');
    }
}

if (confirmStatusButton) {
    confirmStatusButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (!pendingStatusChange || !statusConfirmCheckbox || !statusConfirmCheckbox.checked) {
            showActionNotification('Please check the confirmation box before continuing.');
            return;
        }

        performToggleUserStatus(pendingStatusChange.userId, pendingStatusChange.newStatus);
        closeStatusModal();
    });
}

const cancelStatusButton = document.getElementById('cancelStatusChange');
if (cancelStatusButton) {
    cancelStatusButton.addEventListener('click', function(e) {
        e.preventDefault();
        closeStatusModal();
    });
}

if (statusModal) {
    statusModal.addEventListener('click', function(e) {
        if (e.target === statusModal) {
            closeStatusModal();
        }
    });
}

function performToggleUserStatus(userId, newStatus) {
    fetch(`/admin/users/${userId}/toggle-status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            is_active: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showActionNotification(data.error || 'An error occurred while updating account status.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showActionNotification('An error occurred while updating account status.', 'error');
    });
}

function toggleUserStatus(userId, newStatus, userName = 'this user') {
    openStatusModal(userId, userName, newStatus);
}
</script>
@endsection
