@extends('layouts.app')

@section('title', 'User Management - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px]" data-admin-users-page data-users-base-url="{{ route('admin.users.index', absolute: false) }}">
    @php
        $listReturnTo = request()->getRequestUri();
    @endphp
    <div class="mb-6">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900">{{ $segmentTitle ?? 'User Management' }}</h1>
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

    @if(auth()->user()->isSuperAdmin())
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
    @endif

    <form method="GET" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4" data-submit-feedback data-search-history-form data-search-history-key="admin-user-filters">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div class="relative xl:col-span-2">
            <label for="search" class="sr-only">Search users</label>
            <input id="search" name="search" type="text"
                   value="{{ request('search') }}"
                   data-search-history-input
                   placeholder="Search"
                   class="h-10 block w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                   autocomplete="off">
            <div class="search-history-panel hidden" data-search-history-panel></div>
        </div>

        <div>
            <label for="role" class="sr-only">Role</label>
            <select id="role" name="role" class="h-10 block w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20">
                <option value="all">All roles</option>
                @foreach($availableRolesFilter as $role)
                    <option value="{{ $role }}" {{ request('role', 'all') === $role ? 'selected' : '' }}>
                        {{ \App\Models\User::publicRoleLabel($role) }}
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
            <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-[#033b3d] px-4 text-sm font-semibold text-white transition hover:brightness-95" data-loading-text="Filtering...">Filter</button>
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
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="app-table-body bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        @php
                            $currentUser = auth()->user();
                            $departmentBrand = \App\Models\User::departmentBrandAssets($user->department, $user->role);
                            $avatarUrl = $departmentBrand['logo_url'];
                            $initials = strtoupper(substr((string) $user->name, 0, 2));
                            $normalizedTargetRole = \App\Models\User::normalizeRole($user->role);
                            $displayRole = \App\Models\User::publicRoleValue($user->role);
                            $currentRole = $currentUser->normalizedRole();
                            $isCurrentShadow = $currentRole === \App\Models\User::ROLE_SHADOW;
                            $isTargetShadow = $normalizedTargetRole === \App\Models\User::ROLE_SHADOW;
                            $isTargetAdmin = $normalizedTargetRole === \App\Models\User::ROLE_ADMIN;
                            $canEdit = $user->id !== $currentUser->id
                                && (
                                    $isCurrentShadow
                                    || ($currentRole === \App\Models\User::ROLE_ADMIN && ! $isTargetShadow)
                                    || (! in_array($currentRole, [\App\Models\User::ROLE_SHADOW, \App\Models\User::ROLE_ADMIN], true) && $user->isClient())
                                );
                            $canDelete = false;

                            if ($user->id !== $currentUser->id && ! $isTargetShadow && ! $user->is_profile_locked) {
                                if ($isCurrentShadow) {
                                    $canDelete = true;
                                } elseif ($currentRole === \App\Models\User::ROLE_ADMIN && ! $isTargetAdmin) {
                                    $canDelete = true;
                                } elseif ($currentUser->isAdmin() && $user->isClient()) {
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
                                                class="avatar-logo js-avatar-logo"
                                                loading="lazy"
                                            >
                                            <span class="hidden absolute inset-0 flex items-center justify-center text-sm font-medium text-gray-700">
                                                {{ $initials }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="font-mono text-xs text-gray-500">{{ '@'.$user->username }}</div>
                                        <div class="text-sm text-gray-500 break-all">{{ $user->email }}</div>
                                        @if($user->phone)
                                            <div class="text-sm text-gray-500">{{ $user->phone }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($displayRole === 'admin') bg-indigo-100 text-indigo-800
                                    @elseif($displayRole === 'super_user') bg-blue-100 text-blue-800
                                    @elseif($displayRole === 'technical') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ \App\Models\User::publicRoleLabel($displayRole) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-900 break-words">
                                {{ $user->department ?? '-' }}
                            </td>
                            <td class="px-6 py-4 align-top">
                                @if($canDelete)
                                    <button
                                            type="button"
                                            class="js-toggle-user-status inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium cursor-pointer
                                            {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->name }}"
                                            data-new-status="{{ $user->is_active ? '0' : '1' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top text-sm font-medium">
                                <div class="flex flex-wrap items-center justify-start gap-2 lg:justify-end">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-indigo-600 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-900">
                                        View
                                    </a>
                                    @if($canEdit)
                                        <a href="{{ route('admin.users.edit', ['user' => $user, 'return_to' => $listReturnTo]) }}"
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
                                    @elseif($isTargetShadow && $user->id !== $currentUser->id)
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    @elseif($user->id === $currentUser->id)
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            You
                                        </span>
                                    @elseif($isTargetAdmin && ! $isCurrentShadow)
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Protected
                                        </span>
                                    @elseif($user->is_profile_locked)
                                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                            Locked
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
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
                <p id="statusModalPrompt" class="text-sm text-gray-500">
                    <span id="statusPromptText">Are you sure you want to deactivate</span> <strong id="statusTargetUserName"></strong>?
                </p>
                <label id="statusCheckboxLabel" class="mt-3 inline-flex items-center text-sm text-gray-700">
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
                    Delete User
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

