@extends('layouts.app')

@section('title', 'User Management - iOne Resources Inc.')

@section('content')
<div class="mx-auto max-w-[1460px]">
    <div class="mb-6">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900">{{ $segmentTitle ?? 'User Management' }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ $segmentDescription ?? 'Manage system users and their roles' }}</p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="{{ route('admin.users.create') }}" class="btn-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add User
                </a>
            </div>
        </div>
    </div>

    <div class="mb-6 inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
        <a href="{{ route('admin.users.index') }}"
           class="rounded-md px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'staff' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Staff
        </a>
        <a href="{{ route('admin.users.clients') }}"
           class="rounded-md px-3 py-1.5 font-medium {{ ($segment ?? 'staff') === 'clients' ? 'bg-indigo-100 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
            Clients
        </a>
    </div>

    <form method="GET" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div class="xl:col-span-2">
            <label for="search" class="sr-only">Search users</label>
            <input id="search" name="search" type="text"
                   value="{{ request('search') }}"
                   placeholder="Search users"
                   class="h-10 block w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
        </div>

        <div>
            <label for="role" class="sr-only">Role</label>
            <select id="role" name="role" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                <option value="all">All roles</option>
                @foreach($availableRolesFilter as $role)
                    <option value="{{ $role }}" {{ request('role', 'all') === $role ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="department" class="sr-only">Department</label>
            <select id="department" name="department" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                <option value="all">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department }}" {{ request('department', 'all') === $department ? 'selected' : '' }}>
                        {{ $department }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-4 text-sm font-semibold text-white transition hover:brightness-95">Filter</button>
            <a href="{{ ($segment ?? 'staff') === 'clients' ? route('admin.users.clients') : route('admin.users.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Clear</a>
        </div>
        </div>
    </form>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Department
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        @php
                            $currentUser = auth()->user();
                            $canEdit = $currentUser->isSuperAdmin()
                                || (($user->isClient() || $user->isTechnician()) && $user->id !== $currentUser->id);
                            $canDelete = false;
                            $departmentRaw = strtolower(trim((string) $user->department));
                            $departmentKey = 'ione';
                            if (str_contains($departmentRaw, 'deped')) {
                                $departmentKey = 'deped';
                            } elseif (str_contains($departmentRaw, 'dict')) {
                                $departmentKey = 'dict';
                            } elseif (str_contains($departmentRaw, 'dar')) {
                                $departmentKey = 'dar';
                            }

                            $avatarPathMap = [
                                'ione' => 'images/ione-logo.png',
                                'dict' => 'images/DICT-logo.png',
                                'deped' => 'images/deped-logo.png',
                                'dar' => 'images/dar-logo.png',
                            ];
                            $avatarPath = $avatarPathMap[$departmentKey] ?? 'images/ione-logo.png';
                            if (!file_exists(public_path($avatarPath))) {
                                $avatarPath = 'images/ione-logo.png';
                            }
                            $avatarUrl = asset($avatarPath);
                            $initials = strtoupper(substr((string) $user->name, 0, 2));

                            if ($user->id !== $currentUser->id && !$user->isSuperAdmin()) {
                                if ($currentUser->isSuperAdmin()) {
                                    $canDelete = true;
                                } elseif ($currentUser->isAdmin() && ($user->isClient() || $user->isTechnician())) {
                                    $canDelete = true;
                                }
                            }
                        @endphp
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="relative h-10 w-10 overflow-hidden rounded-full border border-gray-200 bg-white">
                                            <img
                                                src="{{ $avatarUrl }}"
                                                alt="{{ $user->name }} profile image"
                                                class="h-full w-full object-contain p-1"
                                                loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"
                                            >
                                            <span class="hidden absolute inset-0 flex items-center justify-center text-sm font-medium text-gray-700">
                                                {{ $initials }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="text-sm text-gray-500 break-all">{{ $user->email }}</div>
                                        @if($user->phone)
                                            <div class="text-sm text-gray-500">{{ $user->phone }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($user->role === 'super_admin') bg-purple-100 text-purple-800
                                    @elseif($user->role === 'admin') bg-blue-100 text-blue-800
                                    @elseif($user->role === 'technician') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-900 break-words">
                                {{ $user->department ?? '-' }}
                            </td>
                            <td class="px-6 py-4 align-top">
                                @if($canDelete)
                                    <button onclick="toggleUserStatus({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }}, @js($user->name))"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                            {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-500">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 align-top text-sm font-medium">
                                <div class="flex flex-wrap items-center justify-start gap-2 lg:justify-end">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-indigo-600 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-900">
                                        View
                                    </a>
                                    @if($canEdit)
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-blue-600 transition-colors duration-150 hover:bg-blue-50 hover:text-blue-900">
                                            Edit
                                        </a>
                                    @else
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Restricted
                                        </span>
                                    @endif
                                    @if($canDelete)
                                        <button type="button"
                                                class="delete-user-btn inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-sm font-medium text-red-600 transition-colors duration-150 hover:bg-red-50 hover:text-red-900"
                                                data-user-id="{{ $user->id }}"
                                                data-user-name="{{ $user->name }}"
                                                title="Delete {{ $user->name }}">
                                            Delete
                                        </button>
                                    @elseif($user->isSuperAdmin() && $user->id !== $currentUser->id)
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    @elseif($user->id === $currentUser->id && !$user->isSuperAdmin())
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            You
                                        </span>
                                    @elseif(($user->isAdmin() || $user->isSuperAdmin()) && !$currentUser->isSuperAdmin())
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No users found</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by creating a new user.</p>
                                <div class="mt-6">
                                    <a href="{{ route('admin.users.create') }}" class="btn-primary">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Add User
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Account Status Modal -->
<div id="statusConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100">
                <svg class="h-6 w-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 id="statusModalTitle" class="text-lg font-medium text-gray-900 mt-2">Deactivate Account</h3>
            <div class="mt-2 px-7 py-3">
                <p id="statusModalPrompt" class="text-sm text-gray-500">
                    <span id="statusPromptText">Are you sure you want to deactivate</span> <strong id="statusTargetUserName"></strong>?
                </p>
                <label id="statusCheckboxLabel" class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="statusConfirmCheckbox" type="checkbox" class="mr-2 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                    <span id="statusCheckboxText">I understand this user will not be able to sign in.</span>
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmStatusChange" type="button" class="px-4 py-2 bg-amber-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">
                    Deactivate Account
                </button>
                <button id="cancelStatusChange" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Action Notification -->
<div id="actionNotification" class="fixed z-[70] hidden max-w-sm rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-lg" style="top: 16px; right: 16px; bottom: auto; left: auto;">
    <p id="actionNotificationMessage"></p>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-2">Delete User</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete <strong id="deleteUserName"></strong>? This action cannot be undone.
                </p>
                <label class="mt-3 inline-flex items-center text-sm text-gray-700">
                    <input id="deleteConfirmCheckbox" type="checkbox" required aria-required="true" class="mr-2 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    I understand this action is permanent.
                </label>
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" disabled class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300 disabled:cursor-not-allowed disabled:opacity-60">
                    Delete User
                </button>
                <button id="cancelDelete" class="mt-3 px-4 py-2 bg-white text-gray-500 text-base font-medium rounded-md w-full shadow-sm border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let deleteUserId = null;
    let deleteInProgress = false;

    const modal = document.getElementById('deleteModal');
    const confirmButton = document.getElementById('confirmDelete');
    const cancelButton = document.getElementById('cancelDelete');
    const userNameSpan = document.getElementById('deleteUserName');
    const deleteConfirmCheckbox = document.getElementById('deleteConfirmCheckbox');
    const statusModal = document.getElementById('statusConfirmModal');
    const statusNameSpan = document.getElementById('statusTargetUserName');
    const statusConfirmCheckbox = document.getElementById('statusConfirmCheckbox');
    const confirmStatusButton = document.getElementById('confirmStatusChange');
    const cancelStatusButton = document.getElementById('cancelStatusChange');
    const statusModalTitle = document.getElementById('statusModalTitle');
    const statusPromptText = document.getElementById('statusPromptText');
    const statusCheckboxText = document.getElementById('statusCheckboxText');
    const actionNotification = document.getElementById('actionNotification');
    const actionNotificationMessage = document.getElementById('actionNotificationMessage');

    let pendingStatusChange = null;
    let actionNotificationTimeout = null;

    function showActionNotification(message, tone = 'warning') {
        if (!actionNotification || !actionNotificationMessage) {
            return;
        }

        actionNotificationMessage.textContent = message;
        actionNotification.classList.remove('hidden');

        actionNotification.classList.remove('border-amber-200', 'bg-amber-50', 'text-amber-800');
        actionNotification.classList.remove('border-red-200', 'bg-red-50', 'text-red-700');
        actionNotification.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');

        if (tone === 'error') {
            actionNotification.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
        } else if (tone === 'success') {
            actionNotification.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
        } else {
            actionNotification.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
        }

        if (actionNotificationTimeout) {
            clearTimeout(actionNotificationTimeout);
        }

        actionNotificationTimeout = setTimeout(function() {
            actionNotification.classList.add('hidden');
        }, 2600);
    }
    window.showActionNotification = showActionNotification;

    // Function to show modal
    function showDeleteModal(userId, userName) {
        if (deleteInProgress) {
            return;
        }

        deleteUserId = Number.parseInt(userId, 10);

        if (userNameSpan) {
            userNameSpan.textContent = userName;
        }

        if (modal) {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        if (deleteConfirmCheckbox) {
            deleteConfirmCheckbox.checked = false;
        }
        if (confirmButton) {
            confirmButton.disabled = true;
        }
    }

    // Function to hide modal
    function hideDeleteModal() {
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        deleteUserId = null;
        deleteInProgress = false;

        // Reset confirm button
        if (confirmButton) {
            confirmButton.disabled = true;
            confirmButton.textContent = 'Delete User';
        }
        if (deleteConfirmCheckbox) {
            deleteConfirmCheckbox.checked = false;
        }
    }

    if (deleteConfirmCheckbox && confirmButton) {
        deleteConfirmCheckbox.addEventListener('change', function() {
            if (deleteInProgress) {
                return;
            }
            confirmButton.disabled = !deleteConfirmCheckbox.checked;
        });
    }

    // Prevent multiple rapid clicks
    let lastClickTime = 0;
    const CLICK_DELAY = 500; // 500ms between clicks

    // Event delegation for delete buttons
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            e.preventDefault();
            e.stopPropagation();

            const now = Date.now();
            if (now - lastClickTime < CLICK_DELAY) {
                return;
            }
            lastClickTime = now;

            const userId = deleteBtn.getAttribute('data-user-id');
            const userName = deleteBtn.getAttribute('data-user-name');

            showDeleteModal(userId, userName);
        }
    });

    // Confirm delete button
    if (confirmButton) {
        confirmButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (deleteInProgress || !deleteUserId || !deleteConfirmCheckbox || !deleteConfirmCheckbox.checked) {
                showActionNotification('Please check the confirmation box before deleting this account.');
                return;
            }

            deleteInProgress = true;

            // Disable the button to prevent double clicks
            confirmButton.disabled = true;
            confirmButton.textContent = 'Deleting...';

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ route('admin.users.index') }}/${deleteUserId}`;
            form.style.display = 'none';

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

            hideDeleteModal();

            // Submit form immediately
            form.submit();
        });
    }

    // Cancel button
    if (cancelButton) {
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();
            hideDeleteModal();
        });
    }

    // Click outside to close
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideDeleteModal();
            }
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
            confirmStatusButton.className = isReactivation
                ? 'px-4 py-2 bg-emerald-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300'
                : 'px-4 py-2 bg-amber-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300';
        }
        if (statusModal) {
            statusModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }

    function closeStatusModal() {
        pendingStatusChange = null;

    if (statusConfirmCheckbox) {
        statusConfirmCheckbox.checked = false;
    }
    if (statusModal) {
        statusModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}

        if (confirmStatusButton) {
        confirmStatusButton.addEventListener('click', function(e) {
            e.preventDefault();
            const currentStatusCheckbox = document.getElementById('statusConfirmCheckbox');
            if (!pendingStatusChange || !currentStatusCheckbox || !currentStatusCheckbox.checked) {
                showActionNotification('Please check the confirmation box before continuing.');
                return;
            }

            performToggleUserStatus(pendingStatusChange.userId, pendingStatusChange.newStatus);
            closeStatusModal();
        });
    }

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

    window.openDeactivateStatusModal = openStatusModal;
});

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
            if (typeof window.showActionNotification === 'function') {
                window.showActionNotification(data.error || 'An error occurred while updating account status.', 'error');
            }
        }
    })
    .catch(error => {
        if (typeof window.showActionNotification === 'function') {
            window.showActionNotification('An error occurred while updating account status.', 'error');
        }
    });
}

function toggleUserStatus(userId, newStatus, userName = 'this user') {
    if (typeof window.openDeactivateStatusModal === 'function') {
        window.openDeactivateStatusModal(userId, userName, newStatus);
    }
}
</script>
@endsection
