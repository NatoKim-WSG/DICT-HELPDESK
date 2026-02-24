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
        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6">
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
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('role') border-red-300 @enderror">
                                <option value="">Select a role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('role')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div class="sm:col-span-1">
                        <label for="is_active" class="block text-sm font-medium text-gray-700">
                            Status
                        </label>
                        <div class="mt-1">
                            <select name="is_active" id="is_active"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="1" {{ old('is_active', $user->is_active) ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !old('is_active', $user->is_active) ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        @error('is_active')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($canEditPassword ?? false)
                        <!-- Password -->
                        <div class="sm:col-span-1">
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                New Password
                            </label>
                            <div class="mt-1">
                                <input type="password" name="password" id="password"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md @error('password') border-red-300 @enderror">
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
                            <div class="mt-1">
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    @else
                        <div class="sm:col-span-2">
                            <p class="mt-2 text-sm text-gray-500">
                                Password changes for client accounts are restricted to Super Admins.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6 space-x-3">
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Update User</button>
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

    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const syncDepartmentByRole = function () {
        const role = roleSelect.value;
        const isInternal = role === 'super_user' || role === 'technical' || role === 'super_admin';

        if (isInternal) {
            departmentSelect.value = 'iOne';
            departmentSelect.disabled = true;
            departmentHidden.value = 'iOne';
            departmentHidden.disabled = false;
            hint.textContent = 'Internal users (Super User/Technical) are automatically assigned to iOne.';
            return;
        }

        departmentSelect.disabled = false;
        departmentHidden.value = '';
        departmentHidden.disabled = true;
        hint.textContent = 'Select the client department.';
    };

    roleSelect.addEventListener('change', syncDepartmentByRole);
    syncDepartmentByRole();
});
</script>
@endpush
