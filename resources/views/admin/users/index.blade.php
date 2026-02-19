@extends('layouts.app')

@section('title', 'User Management - iOne Resources Inc.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                <p class="mt-1 text-sm text-gray-600">Manage system users and their roles</p>
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

    @if(session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4">
        <div>
            <label for="role" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Role</label>
            <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">All roles</option>
                @foreach($availableRolesFilter as $role)
                    <option value="{{ $role }}" {{ request('role', 'all') === $role ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="department" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Department</label>
            <select id="department" name="department" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department }}" {{ request('department', 'all') === $department ? 'selected' : '' }}>
                        {{ $department }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Status</label>
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Clear</a>
        </div>
    </form>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-hidden">
            <table class="w-full table-fixed divide-y divide-gray-200">
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
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
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
                                <button onclick="toggleUserStatus({{ $user->id }}, {{ $user->is_active ? 'false' : 'true' }})"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                        {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </button>
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
                                    @if(auth()->user()->isSuperAdmin() || $user->isClient())
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-blue-600 transition-colors duration-150 hover:bg-blue-50 hover:text-blue-900">
                                            Edit
                                        </a>
                                    @else
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Restricted
                                        </span>
                                    @endif
                                    @if(($user->isClient() || $user->isTechnician()) && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()))
                                        <button type="button"
                                                class="delete-user-btn inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-sm font-medium text-red-600 transition-colors duration-150 hover:bg-red-50 hover:text-red-900"
                                                data-user-id="{{ $user->id }}"
                                                data-user-name="{{ $user->name }}"
                                                title="Delete {{ $user->name }}">
                                            Delete
                                        </button>
                                    @elseif($user->isAdmin() && auth()->user()->isSuperAdmin() && $user->id !== auth()->id())
                                        <button type="button"
                                                class="delete-user-btn inline-flex cursor-pointer items-center rounded-md px-2.5 py-1 text-sm font-medium text-red-600 transition-colors duration-150 hover:bg-red-50 hover:text-red-900"
                                                data-user-id="{{ $user->id }}"
                                                data-user-name="{{ $user->name }}"
                                                title="Delete {{ $user->name }}">
                                            Delete
                                        </button>
                                    @elseif($user->isSuperAdmin() && $user->id !== auth()->id())
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    @elseif($user->id === auth()->id() && !$user->isSuperAdmin())
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            You
                                        </span>
                                    @elseif($user->id === auth()->id() && $user->isSuperAdmin())
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-blue-400">
                                            Super Admin
                                        </span>
                                    @elseif(($user->isAdmin() || $user->isSuperAdmin()) && !auth()->user()->isSuperAdmin())
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
            </div>
            <div class="items-center px-4 py-3">
                <button id="confirmDelete" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-300">
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
            confirmButton.disabled = false;
            confirmButton.textContent = 'Delete User';
        }
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

            if (deleteInProgress || !deleteUserId) {
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
});

function toggleUserStatus(userId, newStatus) {
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
            alert(data.error || 'An error occurred');
        }
    })
    .catch(error => {
        alert('An error occurred');
    });
}
</script>
@endsection
