@extends('layouts.app')

@section('title', 'Create User - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-users-create-page data-support-department="{{ \App\Models\User::supportDepartment() }}">
    <div class="mb-8">
        <div class="flex items-center">
            <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-gray-700 mr-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Create New User</h1>
                <p class="mt-1 text-sm text-gray-600">Add a new user to the system</p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-6" data-submit-feedback>
            @csrf
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
                    <!-- Username -->
                    <div class="sm:col-span-1">
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="username" id="username" value="{{ old('username') }}" required
                                autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('username') border-red-300 @enderror">
                        </div>
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Display Name -->
                    <div class="sm:col-span-1">
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Display Name <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('name') border-red-300 @enderror">
                        </div>
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="sm:col-span-1">
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email Address (Optional)
                        </label>
                        <div class="mt-1">
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
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
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
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
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('department') border-red-300 @enderror">
                                <option value="">Select department</option>
                                @foreach(\App\Models\User::allowedDepartments() as $departmentOption)
                                    <option value="{{ $departmentOption }}" {{ old('department') === $departmentOption ? 'selected' : '' }}>
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
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('role') border-red-300 @enderror">
                                <option value="">Select a role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('role')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @if(auth()->user()->isSuperAdmin())
                            <p class="mt-2 text-sm text-gray-500">
                                As an admin, you can create admin, super user, technical, and client users.
                            </p>
                        @else
                            <p class="mt-2 text-sm text-gray-500">
                                You can create client users only.
                            </p>
                        @endif
                    </div>

                    <!-- Password -->
                    <div class="sm:col-span-1">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="password" name="password" id="password" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('password') border-red-300 @enderror">
                        </div>
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Confirmation -->
                    <div class="sm:col-span-1">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    @if(auth()->user()->isShadow())
                        <div id="client-notes-wrap" class="hidden sm:col-span-2 xl:col-span-3">
                            <label for="client_notes" class="block text-sm font-medium text-gray-700">
                                Client Notes
                            </label>
                            <div class="mt-1">
                                <textarea
                                    name="client_notes"
                                    id="client_notes"
                                    rows="4"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('client_notes') border-red-300 @enderror"
                                    placeholder="Optional internal notes for this client account"
                                    disabled
                                >{{ old('client_notes') }}</textarea>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Shadow-only note shown for client accounts.</p>
                            @error('client_notes')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 space-x-3">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" data-loading-text="Creating...">Create User</button>
            </div>
        </form>
    </div>
</div>
@endsection
