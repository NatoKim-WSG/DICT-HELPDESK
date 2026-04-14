@php
    $currentUser = auth()->user();
@endphp

<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="overflow-x-auto">
        <table class="min-w-[980px] w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="app-table-body bg-white divide-y divide-gray-200">
                @forelse($users as $user)
                    @php
                        $departmentBrand = \App\Models\User::departmentBrandAssets($user->department, $user->role);
                        $avatarUrl = $departmentBrand['logo_url'];
                        $initials = strtoupper(substr((string) $user->name, 0, 2));
                        $normalizedTargetRole = \App\Models\User::normalizeRole($user->role);
                        $currentRole = $currentUser->normalizedRole();
                        $isCurrentShadow = $currentRole === \App\Models\User::ROLE_SHADOW;
                        $isTargetShadow = $normalizedTargetRole === \App\Models\User::ROLE_SHADOW;
                        $isTargetAdmin = $normalizedTargetRole === \App\Models\User::ROLE_ADMIN;
                        $canView = $currentUser->can('view', $user);
                        $canEdit = $currentUser->can('update', $user);
                        $canToggleStatus = $currentUser->can('toggleStatus', $user);
                        $canDelete = $currentUser->can('delete', $user);
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
                            @include('admin.users.partials.role-badge', ['role' => $user->role])
                        </td>
                        <td class="px-6 py-4 align-top text-sm text-gray-900 break-words">
                            {{ $user->department ?? '-' }}
                        </td>
                        <td class="px-6 py-4 align-top">
                            @if($canToggleStatus)
                                <button
                                    type="button"
                                    class="js-toggle-user-status inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}"
                                    data-user-id="{{ $user->id }}"
                                    data-user-name="{{ $user->name }}"
                                    data-new-status="{{ $user->is_active ? '0' : '1' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            @else
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 align-top text-sm font-medium">
                            <div class="flex flex-wrap items-center justify-start gap-2 lg:justify-end">
                                @if($canView)
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-indigo-600 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-900">
                                        View
                                    </a>
                                @else
                                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-sm font-medium text-gray-400">
                                        Restricted
                                    </span>
                                @endif
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
