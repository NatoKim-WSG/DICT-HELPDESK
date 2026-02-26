@extends('layouts.app')

@section('title', 'Edit User - iOne Resources Inc.')

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <div class="flex items-center">
            <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700 mr-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Edit User</h1>
                <p class="mt-1 text-sm text-gray-600">Update user information and settings</p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6" data-submit-feedback>
            @csrf
            @method('PUT')
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
                    <!-- Name -->
                    <div class="sm:col-span-1">
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                                data-profile-edit-lockable
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('name') border-red-300 @enderror">
                        </div>
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="sm:col-span-1">
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                                data-profile-edit-lockable
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('email') border-red-300 @enderror">
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div class="sm:col-span-1">
                        <label for="phone" class="block text-sm font-medium text-gray-700">
                            Phone Number
                        </label>
                        <div class="mt-1">
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"
                                data-profile-edit-lockable
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('phone') border-red-300 @enderror">
                        </div>
                        @error('phone')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Department -->
                    <div class="sm:col-span-1">
                        <label for="department" class="block text-sm font-medium text-gray-700">
                            Department <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select name="department" id="department" required
                                data-profile-edit-lockable
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('department') border-red-300 @enderror">
                                <option value="">Select department</option>
                                @foreach(\App\Models\User::allowedDepartments() as $departmentOption)
                                    <option value="{{ $departmentOption }}" {{ old('department', $user->department) === $departmentOption ? 'selected' : '' }}>
                                        {{ $departmentOption }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" id="department_hidden" name="department" value="" disabled>
                        </div>
                        <p id="department-role-hint" class="mt-2 text-sm text-gray-500"></p>
                        @error('department')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div class="sm:col-span-1">
                        <label for="role" class="block text-sm font-medium text-gray-700">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select name="role" id="role" required
                                data-profile-edit-lockable
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('role') border-red-300 @enderror">
                                <option value="">Select a role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role }}" {{ old('role', $user->normalizedRole()) === $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('role')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Account Access -->
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">
                            Account Access
                        </label>
                        @php
                            $isActiveValue = old('is_active', $user->is_active ? '1' : '0');
                        @endphp
                        <fieldset class="mt-1 space-y-2">
                            <legend class="sr-only">Lock or unlock this account</legend>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                                <input
                                    type="radio"
                                    name="is_active"
                                    value="1"
                                    data-profile-edit-lockable
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    {{ (string) $isActiveValue === '1' ? 'checked' : '' }}
                                >
                                <span>Unlock account (Active)</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700">
                                <input
                                    type="radio"
                                    name="is_active"
                                    value="0"
                                    data-profile-edit-lockable
                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    {{ (string) $isActiveValue === '0' ? 'checked' : '' }}
                                >
                                <span>Lock account (Inactive)</span>
                            </label>
                        </fieldset>
                        @error('is_active')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Profile Edit Lock -->
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">
                            Profile Edit Lock
                        </label>
                        @php
                            $isProfileLockedValue = old('is_profile_locked', $user->is_profile_locked ? '1' : '0');
                        @endphp
                        <input type="hidden" name="is_profile_locked" id="is_profile_locked" value="{{ $isProfileLockedValue }}">
                        <button
                            type="button"
                            id="profile-lock-toggle"
                            class="mt-1 inline-flex w-full items-center justify-between rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            aria-pressed="{{ (string) $isProfileLockedValue === '1' ? 'true' : 'false' }}"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span id="profile-lock-icon-locked" class="hidden text-rose-600" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M8 11V8a4 4 0 118 0v3m-9 0h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/>
                                    </svg>
                                </span>
                                <span id="profile-lock-icon-unlocked" class="hidden text-emerald-600" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="M16 11V8a4 4 0 00-7.5-2m-1.5 5h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/>
                                    </svg>
                                </span>
                                <span id="profile-lock-state-label">Unlock profile editing</span>
                            </span>
                            <span class="text-xs text-gray-500">Toggle</span>
                        </button>
                        <p id="profile-edit-locked-banner" class="mt-2 hidden rounded border border-slate-300 bg-slate-50 px-2 py-1 text-xs text-slate-700">
                            Profile editing is locked. Unlock first to edit this account.
                        </p>
                        <p class="mt-2 text-xs text-gray-500">When locked, even admins must unlock first before making edits.</p>
                        @error('is_profile_locked')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($canEditPassword ?? false)
                        <!-- Password -->
                        <div class="sm:col-span-1">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                New Password
                            </label>
                            <div class="mt-1 relative">
                                <input type="password" name="password" id="password"
                                    data-profile-edit-lockable
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-24 sm:text-sm border-gray-300 rounded-md @error('password') border-red-300 @enderror">
                                <button
                                    type="button"
                                    data-peek-password-for="password"
                                    class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-indigo-600 transition hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    aria-label="Show password briefly"
                                    title="Show password for 0.5 seconds"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z"/>
                                        <circle cx="12" cy="12" r="3.25" stroke-width="1.8"></circle>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Leave blank to keep current password</p>
                            @error('password')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password Confirmation -->
                        <div class="sm:col-span-1">
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                Confirm New Password
                            </label>
                            <div class="mt-1 relative">
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    data-profile-edit-lockable
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-24 sm:text-sm border-gray-300 rounded-md">
                                <button
                                    type="button"
                                    data-peek-password-for="password_confirmation"
                                    class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-indigo-600 transition hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    aria-label="Show password briefly"
                                    title="Show password for 0.5 seconds"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z"/>
                                        <circle cx="12" cy="12" r="3.25" stroke-width="1.8"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <p class="mt-2 text-sm text-gray-500">
                                Password changes for client accounts are restricted to admins.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 space-x-3">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Saving...">Update User</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const departmentSelect = document.getElementById('department');
    const departmentHidden = document.getElementById('department_hidden');
    const hint = document.getElementById('department-role-hint');
    const profileLockInput = document.getElementById('is_profile_locked');
    const profileLockToggle = document.getElementById('profile-lock-toggle');
    const profileLockStateLabel = document.getElementById('profile-lock-state-label');
    const profileLockIconLocked = document.getElementById('profile-lock-icon-locked');
    const profileLockIconUnlocked = document.getElementById('profile-lock-icon-unlocked');
    const profileEditLockedBanner = document.getElementById('profile-edit-locked-banner');
    const lockableFields = document.querySelectorAll('[data-profile-edit-lockable]');

    if (!roleSelect || !departmentSelect || !departmentHidden || !profileLockInput || !profileLockToggle) return;

    const syncDepartmentByRole = function () {
        const role = roleSelect.value;
        const isInternal = role === 'shadow' || role === 'admin' || role === 'super_user' || role === 'technical';

        if (isInternal) {
            departmentSelect.value = 'iOne';
            departmentSelect.disabled = true;
            departmentHidden.value = 'iOne';
            departmentHidden.disabled = false;
            hint.textContent = 'Internal users are automatically assigned to iOne.';
            return;
        }

        departmentSelect.disabled = false;
        departmentHidden.value = '';
        departmentHidden.disabled = true;
        hint.textContent = 'Select the client department.';
    };

    const applyProfileLockState = function () {
        const isLocked = profileLockInput.value === '1';

        profileLockToggle.setAttribute('aria-pressed', isLocked ? 'true' : 'false');
        profileLockToggle.classList.add('border-gray-300');
        profileLockToggle.classList.remove('border-rose-300', 'bg-rose-50', 'border-emerald-300', 'bg-emerald-50');

        if (profileLockStateLabel) {
            profileLockStateLabel.textContent = isLocked ? 'Locked' : 'Unlocked';
        }

        if (profileLockIconLocked) {
            profileLockIconLocked.classList.toggle('hidden', !isLocked);
        }

        if (profileLockIconUnlocked) {
            profileLockIconUnlocked.classList.toggle('hidden', isLocked);
        }

        if (profileEditLockedBanner) {
            profileEditLockedBanner.classList.toggle('hidden', !isLocked);
        }

        lockableFields.forEach(function (field) {
            const isTextLike = field.matches('input[type="text"], input[type="email"], input[type="password"], input[type="tel"]');

            if (isTextLike) {
                field.readOnly = isLocked;
                field.classList.toggle('bg-gray-100', isLocked);
                field.classList.toggle('cursor-not-allowed', isLocked);
                return;
            }

            if (!field.dataset.profileLockTabIndex) {
                field.dataset.profileLockTabIndex = String(field.tabIndex);
            }

            if (isLocked) {
                field.classList.add('pointer-events-none', 'opacity-60');
                field.setAttribute('aria-disabled', 'true');
                field.tabIndex = -1;
                return;
            }

            field.classList.remove('pointer-events-none', 'opacity-60');
            field.removeAttribute('aria-disabled');
            field.tabIndex = Number(field.dataset.profileLockTabIndex || 0);
        });
    };

    profileLockToggle.addEventListener('click', function () {
        profileLockInput.value = profileLockInput.value === '1' ? '0' : '1';
        applyProfileLockState();
        syncDepartmentByRole();
    });

    roleSelect.addEventListener('change', syncDepartmentByRole);
    syncDepartmentByRole();
    applyProfileLockState();

    const revealTimers = new Map();
    const peekButtons = document.querySelectorAll('[data-peek-password-for]');

    peekButtons.forEach(function (button) {
        const targetId = button.getAttribute('data-peek-password-for');
        const input = targetId ? document.getElementById(targetId) : null;

        if (!input) return;

        button.addEventListener('click', function () {
            input.setAttribute('type', 'text');
            button.disabled = true;
            button.classList.add('opacity-60');

            const existingTimer = revealTimers.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            const timer = window.setTimeout(function () {
                input.setAttribute('type', 'password');
                button.disabled = false;
                button.classList.remove('opacity-60');
                revealTimers.delete(input);
            }, 500);

            revealTimers.set(input, timer);
        });
    });
});
</script>
@endpush

