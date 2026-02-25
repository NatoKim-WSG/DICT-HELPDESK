@extends('layouts.app')

@section('title', 'Account Settings - DICT Helpdesk')

@section('content')
@php
    $normalizedRole = $user->normalizedRole();
    $displayRole = $user->publicRole();
    $isSuperAdmin = in_array($normalizedRole, [\App\Models\User::ROLE_SHADOW, \App\Models\User::ROLE_ADMIN], true);
    $isDepartmentLocked = !$isSuperAdmin;
    $isEmailLocked = !$isSuperAdmin;
    $isUsernameLocked = in_array($normalizedRole, [\App\Models\User::ROLE_SUPER_USER, \App\Models\User::ROLE_TECHNICAL], true);
    $backRoute = $user->canAccessAdminTickets()
        ? route('admin.dashboard')
        : route('client.dashboard');
@endphp

<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Account Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Update your account profile and security preferences.</p>
        </div>
        <a href="{{ $backRoute }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Back to Dashboard
        </a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
            <p class="mt-2 text-sm font-semibold text-slate-800">{{ \App\Models\User::publicRoleLabel($displayRole) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Account Status</p>
            <p class="mt-2 text-sm font-semibold {{ $user->is_active ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $user->is_active ? 'Active' : 'Inactive' }}
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member Since</p>
            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $user->created_at->format('M d, Y') }}</p>
        </div>
    </div>

    <form action="{{ route('account.settings.update') }}" method="POST" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @csrf
        @method('PUT')

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Profile Information</h2>
            <p class="mt-1 text-sm text-slate-500">Keep your account details up to date.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
            <div class="sm:col-span-1">
                <label for="name" class="form-label">
                    Username <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $user->name) }}"
                    {{ $isUsernameLocked ? 'readonly aria-readonly=true' : 'required' }}
                    class="form-input @error('name') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror {{ $isUsernameLocked ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                    placeholder="Enter username"
                >
                @error('name')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="email" class="form-label">Email Address <span class="text-rose-500">*</span></label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email', $user->email) }}"
                    {{ $isEmailLocked ? 'readonly aria-readonly=true' : 'required' }}
                    class="form-input @error('email') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror {{ $isEmailLocked ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                    placeholder="you@example.com"
                >
                @if($isEmailLocked)
                    <p class="mt-1 px-3.5 text-xs text-slate-500">Only admins can change account email addresses.</p>
                @endif
                @error('email')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="department" class="form-label">Department</label>
                @if($isSuperAdmin)
                    <select
                        name="department"
                        id="department"
                        class="form-input @error('department') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    >
                        @foreach($departmentOptions as $departmentOption)
                            <option value="{{ $departmentOption }}" {{ old('department', $user->department) === $departmentOption ? 'selected' : '' }}>
                                {{ $departmentOption }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input
                        type="text"
                        name="department"
                        id="department"
                        value="{{ old('department', $user->department) }}"
                        class="form-input @error('department') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror {{ $isDepartmentLocked ? 'bg-slate-100 text-slate-500 cursor-not-allowed' : '' }}"
                        placeholder="{{ $isDepartmentLocked ? '' : 'Optional' }}"
                        {{ $isDepartmentLocked ? 'readonly aria-readonly=true' : '' }}
                    >
                @endif
                @if($isDepartmentLocked)
                    <p class="mt-1 text-xs text-slate-500">Only admins can change your department assignment.</p>
                @endif
                @error('department')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="phone" class="form-label">Phone Number</label>
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="{{ old('phone', $user->phone) }}"
                    class="form-input @error('phone') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    placeholder="Optional"
                >
                @error('phone')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-y border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Security</h2>
            <p class="mt-1 text-sm text-slate-500">Update your password if needed.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
            <div class="sm:col-span-1">
                <label for="current_password" class="form-label">Current Password</label>
                <input
                    type="password"
                    name="current_password"
                    id="current_password"
                    class="form-input @error('current_password') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Required when changing your password.</p>
                @error('current_password')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">New Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input pr-11 @error('password') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 transition hover:text-slate-700"
                        data-password-peek="#password"
                        aria-label="Show password briefly"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z"/>
                            <circle cx="12" cy="12" r="3.25" stroke-width="1.8"></circle>
                        </svg>
                    </button>
                </div>
                <p class="mt-1 text-xs text-slate-500">Leave blank to keep your current password.</p>
                @error('password')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="form-input pr-11"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 inline-flex items-center justify-center px-3 text-slate-500 transition hover:text-slate-700"
                        data-password-peek="#password_confirmation"
                        aria-label="Show password briefly"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z"/>
                            <circle cx="12" cy="12" r="3.25" stroke-width="1.8"></circle>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <a href="{{ $backRoute }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const peekButtons = document.querySelectorAll('[data-password-peek]');

        peekButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const selector = button.getAttribute('data-password-peek');
                if (!selector) return;

                const input = document.querySelector(selector);
                if (!input || input.type !== 'password') return;

                input.type = 'text';

                window.setTimeout(function () {
                    input.type = 'password';
                }, 1000);
            });
        });
    });
    </script>
</div>
@endsection

