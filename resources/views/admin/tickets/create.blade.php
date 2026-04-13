@extends('layouts.app')

@section('title', 'Create Ticket - ' . config('app.name'))

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8" data-admin-ticket-create-page>
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="h-5 w-5 flex-shrink-0 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="{{ route('admin.tickets.index') }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">Tickets</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="h-5 w-5 flex-shrink-0 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-gray-900">Create Ticket</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="rounded-lg bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="mb-6">
                <h3 class="mb-2 text-lg font-medium leading-6 text-gray-900">Create Ticket for Client</h3>
                <p class="text-sm text-slate-600">
                    Use this form when a client contacts a technician directly and the ticket needs to be logged by a superuser.
                </p>
            </div>

            <form id="admin-ticket-create-form" action="{{ route('admin.tickets.store') }}" method="POST" enctype="multipart/form-data" data-submit-feedback>
                @csrf

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div class="xl:col-span-2">
                        <label for="user_id" class="form-label">Client Account <span class="text-red-600">*</span></label>
                        <select
                            name="user_id"
                            id="user_id"
                            required
                            class="form-input @error('user_id') border-red-500 @enderror"
                            data-client-account-select
                        >
                            <option value="">Select a client account</option>
                            @foreach($clientAccounts as $clientAccount)
                                <option
                                    value="{{ $clientAccount->id }}"
                                    data-name="{{ $clientAccount->name }}"
                                    data-email="{{ $clientAccount->email }}"
                                    data-phone="{{ $clientAccount->phone }}"
                                    data-department="{{ $clientAccount->department }}"
                                    {{ old('user_id') == $clientAccount->id ? 'selected' : '' }}
                                >
                                    {{ $clientAccount->name }}{{ $clientAccount->department ? ' - ' . $clientAccount->department : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="form-label">Name <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" required
                               class="form-input @error('name') border-red-500 @enderror"
                               value="{{ old('name') }}"
                               placeholder="Client full name">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                        <div>
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" name="contact_number" id="contact_number"
                                   class="form-input @error('contact_number') border-red-500 @enderror"
                                   value="{{ old('contact_number') }}"
                                   placeholder="e.g. 09123456789">
                            @error('contact_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email"
                                   class="form-input @error('email') border-red-500 @enderror"
                                   value="{{ old('email') }}"
                                   placeholder="client@example.com">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <p class="inline-block max-w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            This ticket will be linked to the selected client account, while the contact details below capture the request snapshot used for support follow-up.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                        <div>
                            <label for="province" class="form-label">Province <span class="text-red-600">*</span></label>
                            <input type="text" name="province" id="province" required
                                   class="form-input @error('province') border-red-500 @enderror"
                                   value="{{ old('province') }}"
                                   placeholder="e.g. Iloilo">
                            @error('province')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="municipality" class="form-label">Municipality / City <span class="text-red-600">*</span></label>
                            <input type="text" name="municipality" id="municipality" required
                                   class="form-input @error('municipality') border-red-500 @enderror"
                                   value="{{ old('municipality') }}"
                                   placeholder="e.g. Iloilo City">
                            @error('municipality')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label for="subject" class="form-label">Subject <span class="text-red-600">*</span></label>
                        <input type="text" name="subject" id="subject" required
                               class="form-input @error('subject') border-red-500 @enderror"
                               value="{{ old('subject') }}"
                               placeholder="Brief description of the issue">
                        @error('subject')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="category_id" class="form-label">Category <span class="text-red-600">*</span></label>
                        <select name="category_id" id="category_id" required
                                class="form-input @error('category_id') border-red-500 @enderror">
                            <option value="">Select a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ticket_type" class="form-label">Ticket Type <span class="text-red-600">*</span></label>
                        <select name="ticket_type" id="ticket_type" required
                                class="form-input @error('ticket_type') border-red-500 @enderror">
                            <option value="{{ \App\Models\Ticket::TYPE_EXTERNAL }}" {{ old('ticket_type', \App\Models\Ticket::TYPE_EXTERNAL) === \App\Models\Ticket::TYPE_EXTERNAL ? 'selected' : '' }}>External</option>
                            <option value="{{ \App\Models\Ticket::TYPE_INTERNAL }}" {{ old('ticket_type') === \App\Models\Ticket::TYPE_INTERNAL ? 'selected' : '' }}>Internal</option>
                        </select>
                        @error('ticket_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <p class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Severity is assigned by the support staff after the ticket is reviewed.
                        </p>
                    </div>

                    <div class="xl:col-span-2">
                        <label for="description" class="form-label">Description <span class="text-red-600">*</span></label>
                        <textarea name="description" id="description" rows="6" required
                                  class="form-input @error('description') border-red-500 @enderror"
                                  placeholder="Provide the issue details, context, and any steps already taken.">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <label for="attachments" class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" id="attachments" multiple
                               class="form-input cursor-pointer @error('attachments.*') border-red-500 @enderror"
                               accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.xls,.xlsx">
                        <p class="mt-1 text-sm text-gray-500">
                            Optional. Upload supporting files if available. Maximum 10MB per file.
                        </p>
                        @error('attachments')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <a href="{{ route('admin.tickets.index') }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">
                        Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
