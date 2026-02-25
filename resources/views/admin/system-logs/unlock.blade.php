@extends('layouts.app')

@section('title', 'Unlock System Logs - DICT Helpdesk')

@section('content')
<div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Unlock System Logs</h1>
            <p class="mt-1 text-sm text-slate-500">
                Re-enter your shadow account password to view system and account activity logs.
            </p>
        </div>

        <form action="{{ route('admin.system-logs.unlock.store') }}" method="POST" class="space-y-5 px-6 py-6">
            @csrf
            <div>
                <label for="password" class="form-label">
                    Shadow Account Password <span class="text-rose-500">*</span>
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autofocus
                    class="form-input @error('password') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                    placeholder="Enter your password"
                >
                @error('password')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Unlock Logs</button>
            </div>
        </form>
    </div>
</div>
@endsection
