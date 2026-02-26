@extends('layouts.app')

@section('title', 'Create Ticket - iOne Resources Ticketing')

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <div>
                        <a href="{{ route('client.dashboard') }}" class="text-gray-400 hover:text-gray-500">
                            <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="{{ route('client.tickets.index') }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">Tickets</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-gray-900">Create New Ticket</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">Create New Support Ticket</h3>

            <form id="ticket-create-form" action="{{ route('client.tickets.store') }}" method="POST" enctype="multipart/form-data" data-submit-feedback>
                @csrf

                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div>
                        <label for="name" class="form-label">Name <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" required
                               class="form-input @error('name') border-red-500 @enderror"
                               value="{{ old('name') }}"
                               placeholder="Your full name">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:col-span-2">
                        <div>
                            <label for="contact_number" class="form-label">Contact Number <span class="text-red-600">*</span></label>
                            <input type="text" name="contact_number" id="contact_number" required
                                   class="form-input @error('contact_number') border-red-500 @enderror"
                                   value="{{ old('contact_number') }}"
                                   placeholder="e.g. 09123456789">
                            @error('contact_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="form-label">Email <span class="text-red-600">*</span></label>
                            <input type="email" name="email" id="email" required
                                   class="form-input @error('email') border-red-500 @enderror"
                                   value="{{ old('email') }}"
                                   placeholder="you@example.com">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="xl:col-span-2">
                        <p class="ticket-contact-note inline-block max-w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Contact details are visible only to the iOne technical team for emergency communication purposes.
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

                    <div>
                        <label for="subject" class="form-label">Subject <span class="text-red-600">*</span></label>
                        <input type="text" name="subject" id="subject" required
                               class="form-input @error('subject') border-red-500 @enderror"
                               value="{{ old('subject') }}"
                               placeholder="Brief description of your issue">
                        @error('subject')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:col-span-2">
                        <div>
                            <label for="category_id" class="form-label">Category <span class="text-red-600">*</span></label>
                            <select name="category_id" id="category_id" required
                                    class="form-input @error('category_id') border-red-500 @enderror">
                                <option value="">Select a category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                            {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="priority" class="form-label">Priority <span class="text-red-600">*</span></label>
                            <select name="priority" id="priority" required
                                    class="form-input @error('priority') border-red-500 @enderror">
                                <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                            @error('priority')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="xl:col-span-2">
                        <label for="description" class="form-label">Description <span class="text-red-600">*</span></label>
                        <textarea name="description" id="description" rows="6" required
                                  class="form-input @error('description') border-red-500 @enderror"
                                  placeholder="Please provide detailed information about your issue, including steps to reproduce if applicable">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <label for="attachments" class="form-label">Attachments <span class="text-red-600">*</span></label>
                        <input type="file" name="attachments[]" id="attachments" multiple required
                               class="form-input cursor-pointer @error('attachments.*') border-red-500 @enderror"
                               accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                        <p class="mt-1 text-sm text-gray-500">
                            Upload at least one file. Supported formats: JPG, PNG, PDF, DOC, DOCX, TXT (max 10MB each)
                        </p>
                        @error('attachments')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('attachments.*')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <label class="inline-flex items-start gap-3 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            name="ticket_consent"
                            value="1"
                            required
                            {{ old('ticket_consent') ? 'checked' : '' }}
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-ione-blue-600 focus:ring-ione-blue-500"
                        >
                        <span>
                            I confirm that I am authorized to submit this ticket and consent to support-related processing of submitted data and attachments.
                            I have read the
                            <button type="button" @click="openLegalModal('privacy')" class="border-0 bg-transparent p-0 font-semibold text-ione-blue-700 hover:text-ione-blue-900">Privacy Notice</button>
                            and
                            <button type="button" @click="openLegalModal('ticket-consent')" class="border-0 bg-transparent p-0 font-semibold text-ione-blue-700 hover:text-ione-blue-900">Ticket Submission Consent</button>.
                        </span>
                    </label>
                    @error('ticket_consent')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <a href="{{ route('client.tickets.index') }}" class="btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary" data-loading-text="Creating...">
                        Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Information -->
    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-slate-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-slate-900">Tips for better support</h3>
                <div class="mt-2 text-sm text-slate-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Be specific about the problem you're experiencing</li>
                        <li>Include screenshots or error messages if relevant</li>
                        <li>Mention your operating system and browser if it's a technical issue</li>
                        <li>List the steps you've already tried to resolve the issue</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ticket-create-form');
    if (!form) return;

    const fields = [
        document.getElementById('province'),
        document.getElementById('municipality'),
        document.getElementById('subject'),
    ].filter(Boolean);

    const normalizeLeadingUppercase = function (value) {
        const trimmed = String(value || '').trim();
        if (trimmed.length === 0) return '';
        return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
    };

    const normalizeField = function (field) {
        field.value = normalizeLeadingUppercase(field.value);
    };

    fields.forEach(function (field) {
        field.addEventListener('blur', function () {
            normalizeField(field);
        });
    });

    form.addEventListener('submit', function () {
        fields.forEach(normalizeField);
    });
});
</script>
@endpush
@endsection
