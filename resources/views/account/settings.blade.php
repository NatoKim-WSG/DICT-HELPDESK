@extends('layouts.app')

@section('title', 'Account Settings - DICT Helpdesk')

@section('content')
@php
    $isClient = !$user->canManageTickets();
@endphp

<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Account Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Update your account profile and security preferences.</p>
        </div>
        <a href="{{ $user->canManageTickets() ? route('admin.dashboard') : route('client.dashboard') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Back to Dashboard
        </a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
            <p class="mt-2 text-sm font-semibold text-slate-800">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
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

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

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
                    {{ $isClient ? 'Company Name' : 'Full Name' }} <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name', $user->name) }}"
                    required
                    class="form-input @error('name') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    placeholder="{{ $isClient ? 'Enter company name' : 'Enter full name' }}"
                >
                @if($isClient)
                    <p class="mt-1 text-xs text-slate-500">Use your organization or company name.</p>
                @endif
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
                    required
                    class="form-input @error('email') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    placeholder="you@example.com"
                >
                @error('email')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-1">
                <label for="department" class="form-label">{{ $isClient ? 'Department / Unit' : 'Department' }}</label>
                <input
                    type="text"
                    name="department"
                    id="department"
                    value="{{ old('department', $user->department) }}"
                    class="form-input @error('department') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    placeholder="Optional"
                >
                @error('department')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            @if(!$isClient)
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
            @endif
        </div>

        <div class="border-y border-slate-200 px-5 py-4">
            <h2 class="font-display text-lg font-semibold text-slate-900">Security</h2>
            <p class="mt-1 text-sm text-slate-500">Update your password if needed.</p>
        </div>

        <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
            <div>
                <label for="password" class="form-label">New Password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-input @error('password') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                >
                <p class="mt-1 text-xs text-slate-500">Leave blank to keep your current password.</p>
                @error('password')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    class="form-input"
                >
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <a href="{{ $user->canManageTickets() ? route('admin.dashboard') : route('client.dashboard') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>
@endsection
