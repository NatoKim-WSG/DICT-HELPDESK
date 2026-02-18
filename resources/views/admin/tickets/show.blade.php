@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('admin.tickets.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to All Tickets
        </a>
    </div>

    <!-- Ticket Header -->
    <div class="bg-white shadow sm:rounded-lg mb-6">
        <div class="px-4 py-5 sm:px-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                        <span class="font-medium">{{ $ticket->ticket_number }}</span>
                        <span>•</span>
                        <span>Created by {{ $ticket->user->name }} ({{ $ticket->user->email }})</span>
                        <span>•</span>
                        <span>{{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $ticket->status_color }}">
                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $ticket->priority_color }}">
                        {{ ucfirst($ticket->priority) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Original Message -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Original Message</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $ticket->created_at->diffForHumans() }}</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <div class="prose max-w-none">
                        {!! nl2br(e($ticket->description)) !!}
                    </div>
                    @if($ticket->attachments->count() > 0)
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Attachments</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($ticket->attachments as $attachment)
                                    <a href="{{ Storage::url($attachment->file_path) }}"
                                       class="js-attachment-link flex items-center p-3 border border-gray-200 rounded-md hover:bg-gray-50"
                                       data-file-url="{{ Storage::url($attachment->file_path) }}"
                                       data-file-name="{{ $attachment->original_filename }}"
                                       data-file-mime="{{ $attachment->mime_type }}">
                                        <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $attachment->original_filename }}</div>
                                            <div class="text-xs text-gray-500">{{ number_format($attachment->file_size / 1024, 1) }} KB</div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Replies -->
            @foreach($ticket->replies as $reply)
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <h4 class="text-sm font-medium text-gray-900">
                                    {{ $reply->user->name }}
                                </h4>
                                @if($reply->is_internal)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Internal Note
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500">{{ $reply->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="prose max-w-none">
                            {!! nl2br(e($reply->message)) !!}
                        </div>
                        @if($reply->attachments && $reply->attachments->count() > 0)
                            <div class="mt-4">
                                <h5 class="text-sm font-medium text-gray-900 mb-2">Attachments</h5>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($reply->attachments as $attachment)
                                        <a href="{{ Storage::url($attachment->file_path) }}"
                                           class="js-attachment-link flex items-center p-2 border border-gray-200 rounded hover:bg-gray-50 text-sm"
                                           data-file-url="{{ Storage::url($attachment->file_path) }}"
                                           data-file-name="{{ $attachment->original_filename }}"
                                           data-file-mime="{{ $attachment->mime_type }}">
                                            <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            {{ $attachment->original_filename }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <!-- Reply Form -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Add Reply</h3>
                </div>
                <div class="border-t border-gray-200">
                    <form action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" enctype="multipart/form-data" class="px-4 py-5 sm:px-6">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="message" class="form-label">Message</label>
                                <textarea name="message" id="message" rows="5" required
                                        class="form-input" placeholder="Type your reply..."></textarea>
                            </div>

                            <div class="flex items-center">
                                <input type="checkbox" name="is_internal" id="is_internal" value="1"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_internal" class="ml-2 block text-sm text-gray-900">
                                    Internal note (not visible to customer)
                                </label>
                            </div>

                            <div>
                                <label for="attachments" class="form-label">Attachments (optional)</label>
                                <input type="file" name="attachments[]" id="attachments" multiple
                                       class="form-input" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                                <p class="mt-1 text-xs text-gray-500">Max 10MB per file. Allowed: JPG, PNG, PDF, DOC, DOCX, TXT</p>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="btn-primary">
                                    Add Reply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Ticket Details -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Ticket Details</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->name ?? $ticket->user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->contact_number ?? ($ticket->user->phone ?? 'Not provided') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Category</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->category->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                            <dd class="text-sm text-gray-900">
                                {{ $ticket->assignedUser ? $ticket->assignedUser->name : 'Unassigned' }}
                            </dd>
                        </div>
                        @if($ticket->due_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Due Date</dt>
                                <dd class="text-sm {{ $ticket->due_date->isPast() ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $ticket->due_date->format('M j, Y \a\t g:i A') }}
                                </dd>
                            </div>
                        @endif
                        @if($ticket->resolved_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Resolved At</dt>
                                <dd class="text-sm text-gray-900">{{ $ticket->resolved_at->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                        @endif
                        @if($ticket->closed_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Closed At</dt>
                                <dd class="text-sm text-gray-900">{{ $ticket->closed_at->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Actions</h3>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:px-6 space-y-4">
                    <!-- Assign Ticket -->
                    <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST">
                        @csrf
                        <div>
                            <label for="assigned_to" class="form-label">Assign To</label>
                            <select name="assigned_to" id="assigned_to" class="form-input">
                                <option value="">Select Admin</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}"
                                            {{ $ticket->assigned_to == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Assignment</button>
                        </div>
                    </form>

                    <!-- Update Status -->
                    <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST">
                        @csrf
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-input">
                                <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="pending" {{ $ticket->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Status</button>
                        </div>
                    </form>

                    <!-- Update Priority -->
                    <form action="{{ route('admin.tickets.priority', $ticket) }}" method="POST">
                        @csrf
                        <div>
                            <label for="priority" class="form-label">Priority</label>
                            <select name="priority" id="priority" class="form-input">
                                <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ $ticket->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Priority</button>
                        </div>
                    </form>

                    <!-- Set Due Date -->
                    <form action="{{ route('admin.tickets.due-date', $ticket) }}" method="POST">
                        @csrf
                        <div>
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" id="due_date"
                                   value="{{ $ticket->due_date ? $ticket->due_date->format('Y-m-d\TH:i') : '' }}"
                                   class="form-input">
                            <button type="submit" class="mt-2 btn-secondary w-full">Set Due Date</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="attachment-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-60" data-modal-close="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-5xl bg-white rounded-lg shadow-xl">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <h3 id="attachment-modal-title" class="text-sm font-medium text-gray-900">Attachment Preview</h3>
                <button type="button" id="attachment-modal-close" class="text-gray-500 hover:text-gray-700 text-xl leading-none">&times;</button>
            </div>
            <div class="p-4 bg-gray-50">
                <img id="attachment-modal-image" class="hidden w-auto max-w-full h-auto max-h-[75vh] mx-auto" alt="Attachment preview">
                <iframe id="attachment-modal-frame" class="hidden w-full h-[75vh] border-0 bg-white"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('attachment-modal');
    if (!modal) return;

    const modalTitle = document.getElementById('attachment-modal-title');
    const modalImage = document.getElementById('attachment-modal-image');
    const modalFrame = document.getElementById('attachment-modal-frame');
    const closeButton = document.getElementById('attachment-modal-close');

    const closeModal = function () {
        modal.classList.add('hidden');
        modalImage.classList.add('hidden');
        modalFrame.classList.add('hidden');
        modalImage.removeAttribute('src');
        modalFrame.removeAttribute('src');
        document.body.classList.remove('overflow-hidden');
    };

    const openModal = function (url, fileName, mimeType) {
        modalTitle.textContent = fileName || 'Attachment Preview';
        if (mimeType && mimeType.startsWith('image/')) {
            modalImage.src = url;
            modalImage.classList.remove('hidden');
            modalFrame.classList.add('hidden');
        } else {
            modalFrame.src = url;
            modalFrame.classList.remove('hidden');
            modalImage.classList.add('hidden');
        }
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    document.querySelectorAll('.js-attachment-link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openModal(
                link.dataset.fileUrl,
                link.dataset.fileName,
                link.dataset.fileMime
            );
        });
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target.dataset.modalClose === 'true') {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
});
</script>
@endsection
