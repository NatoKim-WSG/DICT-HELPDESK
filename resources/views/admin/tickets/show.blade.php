@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources Inc.')

@section('content')
@php
    $ticketOwnerDepartment = strtolower((string) data_get($ticket, 'user.department'));
    $clientCompanyLogo = str_contains($ticketOwnerDepartment, 'ione')
        ? asset('images/ione-logo.png')
        : asset('images/DICT-logo.png');
    $supportCompanyLogo = asset('images/ione-logo.png');
@endphp
<style>
#admin-conversation-thread {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

#admin-conversation-thread::-webkit-scrollbar {
    width: 0;
    height: 0;
}

#admin-conversation-thread:hover {
    scrollbar-width: thin;
}

#admin-conversation-thread:hover::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#admin-conversation-thread:hover::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}

#admin-conversation-thread:hover::-webkit-scrollbar-track {
    background: transparent;
}
</style>
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


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                <span class="font-medium">{{ $ticket->ticket_number }}</span>
                                <span>&bull;</span>
                                <span>Created {{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $ticket->status_color }}">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $ticket->priority_color }}">
                                @if(strtolower($ticket->priority) === 'urgent')
                                    <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2.25m0 3.75h.01M10.34 3.94 1.82 18a2.25 2.25 0 001.92 3.38h16.52a2.25 2.25 0 001.92-3.38L13.66 3.94a2.25 2.25 0 00-3.32 0z"></path>
                                    </svg>
                                @endif
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Conversation</h3>
                            <p class="mt-1 text-sm text-slate-500">Message thread between client and support.</p>
                        </div>
                        <span id="admin-message-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $ticket->replies->count() + 1 }} messages
                        </span>
                    </div>
                </div>

                <div id="admin-conversation-thread" class="h-[560px] space-y-4 overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white px-4 py-5 sm:px-6">
                    <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $ticket->created_at->toIso8601String() }}">
                        {{ $ticket->created_at->greaterThan(now()->subDay()) ? $ticket->created_at->format('g:i A') : $ticket->created_at->format('M j, Y') }}
                    </div>
                    <div class="js-chat-row flex justify-start" data-created-at="{{ $ticket->created_at->toIso8601String() }}">
                        <div class="flex w-full max-w-3xl items-start gap-2">
                            <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                <img src="{{ $clientCompanyLogo }}" alt="Client company logo" class="h-7 w-7 object-contain">
                            </div>
                            <div class="max-w-[82%] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                <p class="js-message-text whitespace-pre-wrap text-sm leading-6 text-slate-800">{!! nl2br(e($ticket->description)) !!}</p>

                                @if($ticket->attachments->count() > 0)
                                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach($ticket->attachments as $attachment)
                                            <a href="{{ $attachment->download_url }}"
                                               class="js-attachment-link flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
                                               data-file-url="{{ $attachment->preview_url }}"
                                               data-file-name="{{ $attachment->original_filename }}"
                                               data-file-mime="{{ $attachment->mime_type }}">
                                                <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                </svg>
                                                <span class="truncate">{{ $attachment->original_filename }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @php
                        $lastTimestamp = $ticket->created_at;
                    @endphp
                    @foreach($ticket->replies->sortBy('created_at') as $reply)
                        @php
                            $isInternal = (bool) $reply->is_internal;
                            $fromSupport = $reply->user->canAccessAdminTickets();
                            $showTimestamp = $reply->created_at->diffInMinutes($lastTimestamp) >= 15;
                            $canManageReply = (int) ($reply->user_id === auth()->id());
                            $lastTimestamp = $reply->created_at;
                        @endphp
                        @if($showTimestamp)
                            <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $reply->created_at->toIso8601String() }}">
                                {{ $reply->created_at->greaterThan(now()->subDay()) ? $reply->created_at->format('g:i A') : $reply->created_at->format('M j, Y') }}
                            </div>
                        @endif
                        <div class="js-chat-row flex {{ $fromSupport ? 'justify-end' : 'justify-start' }}" data-created-at="{{ $reply->created_at->toIso8601String() }}" data-reply-id="{{ $reply->id }}" data-can-manage="{{ $canManageReply }}">
                            @php $avatarLogo = $fromSupport ? $supportCompanyLogo : $clientCompanyLogo; @endphp
                            <div class="flex w-full max-w-3xl items-start gap-2 {{ $fromSupport ? 'justify-end' : '' }}">
                                <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white {{ $fromSupport ? 'order-2' : '' }}">
                                    <img src="{{ $avatarLogo }}" alt="{{ $fromSupport ? 'Support' : 'Client' }} company logo" class="h-7 w-7 object-contain">
                                </div>
                                <div class="js-chat-bubble relative group max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm {{ $fromSupport ? 'order-1 border-ione-blue-200 bg-ione-blue-50' : 'border-slate-200 bg-white' }}" data-message="{{ e($reply->message) }}" data-deleted="{{ $reply->deleted_at ? '1' : '0' }}" data-edited="{{ $reply->edited_at ? '1' : '0' }}">
                                    <div class="js-state-row mb-1 flex items-center gap-2 {{ $reply->edited_at || $reply->deleted_at ? '' : 'hidden' }}">
                                        <span class="js-edited-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 {{ $reply->edited_at ? '' : 'hidden' }}">Edited</span>
                                        <span class="js-deleted-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 {{ $reply->deleted_at ? '' : 'hidden' }}">Deleted</span>
                                    </div>
                                    <div class="mb-1 flex items-center gap-2 {{ $isInternal ? '' : 'hidden' }}">
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                                            Internal
                                        </span>
                                    </div>
                                    <div class="js-reply-reference mb-2 {{ $reply->replyTo ? '' : 'hidden' }}">
                                        <p class="mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/>
                                            </svg>
                                            {{ $fromSupport ? 'Support replied' : 'Client replied' }}
                                        </p>
                                        <div class="rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">
                                            {{ $reply->replyTo ? \Illuminate\Support\Str::limit($reply->replyTo->message, 120) : '' }}
                                        </div>
                                    </div>
                                    <p class="js-message-text whitespace-pre-wrap text-sm leading-6 {{ $reply->deleted_at ? 'italic text-slate-500' : 'text-slate-800' }}">{!! nl2br(e($reply->message)) !!}</p>

                                    @if($reply->attachments && $reply->attachments->count() > 0 && !$reply->deleted_at)
                                        <div class="js-attachments-wrap mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach($reply->attachments as $attachment)
                                                <a href="{{ $attachment->download_url }}"
                                                   class="js-attachment-link flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
                                                   data-file-url="{{ $attachment->preview_url }}"
                                                   data-file-name="{{ $attachment->original_filename }}"
                                                   data-file-mime="{{ $attachment->mime_type }}">
                                                    <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                    </svg>
                                                    <span class="truncate">{{ $attachment->original_filename }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="js-message-actions absolute {{ $fromSupport ? '-left-[4.75rem]' : '-right-10' }} top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100">
                                        @if($canManageReply)
                                            <div class="relative">
                                                <button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="More actions">
                                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                                    </svg>
                                                </button>
                                                <div class="js-more-menu absolute {{ $fromSupport ? 'left-0' : 'right-0' }} z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                                    <button type="button" class="js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 {{ $reply->deleted_at ? 'hidden' : '' }}">Edit</button>
                                                    <button type="button" class="js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50 {{ $reply->deleted_at ? 'hidden' : '' }}">Delete</button>
                                                </div>
                                            </div>
                                        @endif
                                        <button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="Reply to this message">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 pb-4 pt-2 sm:px-6">
                    <form id="admin-ticket-reply-form" action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" enctype="multipart/form-data" class="space-y-3" data-update-url-template="{{ route('admin.tickets.replies.update', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}" data-delete-url-template="{{ route('admin.tickets.replies.delete', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}">
                        @csrf
                        <p id="admin-reply-error" class="hidden text-xs font-medium text-rose-600"></p>
                        <input type="hidden" id="admin_reply_to_id" name="reply_to_id" value="">
                        <div id="admin-reply-target-banner" class="hidden items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 shadow-sm">
                            <span id="admin-reply-target-text" class="truncate pr-3 font-medium"></span>
                            <button type="button" id="admin-clear-reply-target" class="rounded-md px-2 py-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">Cancel</button>
                        </div>
                        <div class="px-0 py-0">
                            <textarea name="message" id="message" rows="1" required class="block max-h-48 min-h-[44px] w-full resize-none overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20" placeholder="Write your reply..."></textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <label for="attachments" class="inline-flex cursor-pointer items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100">Attach</label>
                                <input type="file" name="attachments[]" id="attachments" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt">
                                <span id="admin-attachment-count" class="text-xs text-slate-500">No files selected</span>
                            </div>
                            <div class="flex items-center gap-4">
                                <label for="is_internal" class="inline-flex items-center gap-2 text-sm text-gray-900">
                                    <input type="checkbox" name="is_internal" id="is_internal" value="1"
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    Internal note (hidden from client)
                                </label>
                                <button id="admin-send-reply-btn" type="submit" class="inline-flex items-center rounded-full bg-[#033b3d] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#022a2c] disabled:cursor-not-allowed disabled:opacity-70">
                                    Send
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Max 10MB per file.</p>
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
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->email ?? $ticket->user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Province</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->province ?? 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Municipality</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->municipality ?? 'Not provided' }}</dd>
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
    const thread = document.getElementById('admin-conversation-thread');
    const replyForm = document.getElementById('admin-ticket-reply-form');
    const messageInput = document.getElementById('message');
    const sendReplyButton = document.getElementById('admin-send-reply-btn');
    const attachmentInput = document.getElementById('attachments');
    const attachmentCount = document.getElementById('admin-attachment-count');
    const messageCountNode = document.getElementById('admin-message-count');
    const replyError = document.getElementById('admin-reply-error');
    const replyToInput = document.getElementById('admin_reply_to_id');
    const replyTargetBanner = document.getElementById('admin-reply-target-banner');
    const replyTargetText = document.getElementById('admin-reply-target-text');
    const clearReplyTargetButton = document.getElementById('admin-clear-reply-target');
    const updateUrlTemplate = replyForm ? replyForm.dataset.updateUrlTemplate : '';
    const deleteUrlTemplate = replyForm ? replyForm.dataset.deleteUrlTemplate : '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const clientLogo = @json($clientCompanyLogo);
    const supportLogo = @json($supportCompanyLogo);

    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    if (messageInput) {
        const autoResize = function () {
            const maxHeight = 192;
            messageInput.style.height = 'auto';
            const nextHeight = Math.min(messageInput.scrollHeight, maxHeight);
            messageInput.style.height = nextHeight + 'px';
            messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
        };

        autoResize();
        messageInput.addEventListener('input', autoResize);

        messageInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && replyForm) {
                event.preventDefault();
                if (messageInput.value.trim().length > 0) {
                    replyForm.requestSubmit();
                }
            }
        });
    }

    const setSendState = function (isSending) {
        if (!sendReplyButton) return;
        if (isSending) {
            sendReplyButton.disabled = true;
            sendReplyButton.textContent = 'Sending...';
        } else {
            sendReplyButton.disabled = false;
            sendReplyButton.textContent = 'Send';
        }
    };

    const updateAttachmentCount = function () {
        if (!attachmentInput || !attachmentCount) return;
        const totalFiles = attachmentInput.files ? attachmentInput.files.length : 0;
        attachmentCount.textContent = totalFiles > 0
            ? (totalFiles + (totalFiles === 1 ? ' file selected' : ' files selected'))
            : 'No files selected';
    };

    if (attachmentInput) {
        attachmentInput.addEventListener('change', updateAttachmentCount);
        updateAttachmentCount();
    }

    const setReplyTarget = function (replyId, message) {
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = String(replyId);
        replyTargetText.textContent = 'Replying to: ' + (message || '').slice(0, 120);
        replyTargetBanner.classList.remove('hidden');
        replyTargetBanner.classList.add('flex');
        if (messageInput) messageInput.focus();
    };

    const clearReplyTarget = function () {
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = '';
        replyTargetText.textContent = '';
        replyTargetBanner.classList.remove('flex');
        replyTargetBanner.classList.add('hidden');
    };

    const closeMenus = function () {
        document.querySelectorAll('.js-more-menu').forEach(function (menu) {
            menu.classList.add('hidden');
        });
    };

    const escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const nl2br = function (value) {
        return escapeHtml(value).replace(/\n/g, '<br>');
    };

    const parseIso = function (value) {
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const formatTimestampLabel = function (date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '';
        }

        const diffMs = Date.now() - date.getTime();
        const dayMs = 24 * 60 * 60 * 1000;

        if (diffMs > dayMs) {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        }

        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    };

    const maybeAppendSeparator = function (isoDate) {
        if (!thread || !isoDate) return;
        const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
        const lastRow = rows.length ? rows[rows.length - 1] : null;

        if (!lastRow) return;

        const lastDate = parseIso(lastRow.dataset.createdAt);
        const nextDate = parseIso(isoDate);
        if (!lastDate || !nextDate) return;

        const diffMinutes = Math.floor((nextDate.getTime() - lastDate.getTime()) / 60000);
        if (diffMinutes < 15) return;

        const separator = document.createElement('div');
        separator.className = 'js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400';
        separator.dataset.time = isoDate;
        separator.textContent = formatTimestampLabel(nextDate);
        thread.appendChild(separator);
    };

    const appendReply = function (payload) {
        if (!thread || !payload) return;

        maybeAppendSeparator(payload.created_at_iso);

        const fromSupport = Boolean(payload.from_support);
        const canManage = Boolean(payload.can_manage);
        const row = document.createElement('div');
        row.className = 'js-chat-row flex ' + (fromSupport ? 'justify-end' : 'justify-start');
        row.dataset.createdAt = payload.created_at_iso;
        row.dataset.replyId = payload.id;
        row.dataset.canManage = canManage ? '1' : '0';

        const avatarLogo = fromSupport ? supportLogo : clientLogo;
        const isDeleted = Boolean(payload.deleted);
        const isEdited = Boolean(payload.edited);
        const internalBadge = payload.is_internal
            ? '<div class="mb-1 flex items-center gap-2"><span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Internal</span></div>'
            : '';
        const replyRefText = payload.reply_to_message || payload.reply_to_excerpt || '';
        const replyReference = replyRefText
            ? '<div class="js-reply-reference mb-2"><p class="mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg>' + (fromSupport ? 'Support replied' : 'Client replied') + '</p><div class="js-reply-reference-text rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">' + escapeHtml(replyRefText) + '</div></div>'
            : '';
        const attachmentsHtml = !isDeleted && Array.isArray(payload.attachments) && payload.attachments.length
            ? '<div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">' + payload.attachments.map(function (attachment) {
                return '<a href="' + attachment.download_url + '" class="js-attachment-link flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50" data-file-url="' + attachment.preview_url + '" data-file-name="' + escapeHtml(attachment.original_filename) + '" data-file-mime="' + escapeHtml(attachment.mime_type || '') + '"><svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><span class="truncate">' + escapeHtml(attachment.original_filename) + '</span></a>';
            }).join('') + '</div>'
            : '';
        const stateRow = '<div class="js-state-row mb-1 flex items-center gap-2 ' + ((isEdited || isDeleted) ? '' : 'hidden') + '"><span class="js-edited-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 ' + (isEdited ? '' : 'hidden') + '">Edited</span><span class="js-deleted-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 ' + (isDeleted ? '' : 'hidden') + '">Deleted</span></div>';
        const moreActions = canManage
            ? '<div class="relative"><button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="More actions"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg></button><div class="js-more-menu absolute ' + (fromSupport ? 'left-0' : 'right-0') + ' z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">' + (isDeleted ? '' : '<button type="button" class="js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button><button type="button" class="js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50">Delete</button>') + '</div></div>'
            : '';

        row.innerHTML =
            '<div class="flex w-full max-w-3xl items-start gap-2 ' + (fromSupport ? 'justify-end' : '') + '">' +
                '<div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white ' + (fromSupport ? 'order-2' : '') + '">' +
                    '<img src="' + avatarLogo + '" alt="' + (fromSupport ? 'Support' : 'Client') + ' company logo" class="h-7 w-7 object-contain">' +
                '</div>' +
                '<div class="js-chat-bubble relative group max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm ' + (fromSupport ? 'order-1 border-ione-blue-200 bg-ione-blue-50' : 'border-slate-200 bg-white') + '" data-message="' + escapeHtml(payload.message || '') + '" data-deleted="' + (isDeleted ? '1' : '0') + '" data-edited="' + (isEdited ? '1' : '0') + '">' +
                    stateRow +
                    internalBadge +
                    replyReference +
                    '<p class="js-message-text whitespace-pre-wrap text-sm leading-6 ' + (isDeleted ? 'italic text-slate-500' : 'text-slate-800') + '">' + nl2br(payload.message) + '</p>' +
                    attachmentsHtml +
                    '<div class="js-message-actions absolute ' + (fromSupport ? '-left-[4.75rem]' : '-right-10') + ' top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100">' +
                        moreActions +
                        '<button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="Reply to this message"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg></button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        thread.appendChild(row);
        thread.scrollTop = thread.scrollHeight;

        if (messageCountNode) {
            const countMatch = messageCountNode.textContent.trim().match(/^\d+/);
            if (countMatch) {
                const next = Number(countMatch[0]) + 1;
                messageCountNode.textContent = String(next) + ' messages';
            }
        }
    };

    if (clearReplyTargetButton) {
        clearReplyTargetButton.addEventListener('click', clearReplyTarget);
    }

    if (thread) {
        thread.addEventListener('click', function (event) {
            const moreButton = event.target.closest('.js-more-btn');
            if (moreButton) {
                const menu = moreButton.parentElement.querySelector('.js-more-menu');
                closeMenus();
                if (menu) menu.classList.toggle('hidden');
                return;
            }

            const replyButton = event.target.closest('.js-reply-msg');
            if (replyButton) {
                const row = replyButton.closest('.js-chat-row');
                if (!row) return;

                const replyId = row.dataset.replyId;
                const messageNode = row.querySelector('.js-message-text');
                const message = messageNode ? messageNode.textContent.trim() : '';
                if (replyId) {
                    setReplyTarget(replyId, message);
                }
                closeMenus();
                return;
            }

            const editButton = event.target.closest('.js-edit-msg');
            if (editButton) {
                const row = editButton.closest('.js-chat-row');
                if (!row) return;
                closeMenus();

                if (row.dataset.canManage !== '1') {
                    return;
                }

                const bubble = row.querySelector('.js-chat-bubble');
                const currentMessage = bubble ? (bubble.dataset.message || '') : '';
                const editedMessage = window.prompt('Edit message', currentMessage);

                if (editedMessage === null || editedMessage.trim() === '' || editedMessage === currentMessage) {
                    return;
                }

                fetch(updateUrlTemplate.replace('__REPLY__', row.dataset.replyId), {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: editedMessage }),
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Unable to edit message.');
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !data.reply) return;
                        const reply = data.reply;
                        bubble.dataset.message = reply.message || '';
                        bubble.dataset.edited = reply.edited ? '1' : '0';
                        bubble.dataset.deleted = reply.deleted ? '1' : '0';

                        const messageText = row.querySelector('.js-message-text');
                        if (messageText) {
                            messageText.textContent = reply.message || '';
                            messageText.classList.toggle('italic', !!reply.deleted);
                            messageText.classList.toggle('text-slate-500', !!reply.deleted);
                            messageText.classList.toggle('text-slate-800', !reply.deleted);
                        }

                        const editedBadge = row.querySelector('.js-edited-badge');
                        if (editedBadge) editedBadge.classList.toggle('hidden', !reply.edited);

                        const stateRow = row.querySelector('.js-state-row');
                        if (stateRow) stateRow.classList.toggle('hidden', !(reply.edited || reply.deleted));
                    })
                    .catch(function (error) {
                        if (!replyError) return;
                        replyError.textContent = error.message || 'Unable to edit message.';
                        replyError.classList.remove('hidden');
                    });
                return;
            }

            const deleteButton = event.target.closest('.js-delete-msg');
            if (deleteButton) {
                const row = deleteButton.closest('.js-chat-row');
                if (!row) return;
                closeMenus();

                if (row.dataset.canManage !== '1') {
                    return;
                }

                if (!window.confirm('Delete this message?')) {
                    return;
                }

                fetch(deleteUrlTemplate.replace('__REPLY__', row.dataset.replyId), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Unable to delete message.');
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || !data.reply) return;
                        const reply = data.reply;
                        const bubble = row.querySelector('.js-chat-bubble');
                        if (!bubble) return;

                        bubble.dataset.message = reply.message || '';
                        bubble.dataset.edited = reply.edited ? '1' : '0';
                        bubble.dataset.deleted = reply.deleted ? '1' : '0';

                        const messageText = row.querySelector('.js-message-text');
                        if (messageText) {
                            messageText.textContent = reply.message || '';
                            messageText.classList.add('italic', 'text-slate-500');
                            messageText.classList.remove('text-slate-800');
                        }

                        const deletedBadge = row.querySelector('.js-deleted-badge');
                        if (deletedBadge) deletedBadge.classList.remove('hidden');

                        const stateRow = row.querySelector('.js-state-row');
                        if (stateRow) stateRow.classList.remove('hidden');

                        const attachmentsWrap = row.querySelector('.js-attachments-wrap');
                        if (attachmentsWrap) attachmentsWrap.remove();

                        const menu = row.querySelector('.js-more-menu');
                        if (menu) menu.innerHTML = '';
                    })
                    .catch(function (error) {
                        if (!replyError) return;
                        replyError.textContent = error.message || 'Unable to delete message.';
                        replyError.classList.remove('hidden');
                    });
            }
        });
    }

    if (replyForm) {
        replyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!messageInput || messageInput.value.trim().length === 0) return;

            if (replyError) {
                replyError.classList.add('hidden');
                replyError.textContent = '';
            }

            setSendState(true);

            fetch(replyForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(replyForm),
                credentials: 'same-origin',
            })
                .then(function (response) {
                    const contentType = response.headers.get('content-type') || '';
                    if (!contentType.includes('application/json')) {
                        window.location.reload();
                        return null;
                    }

                    return response.json().then(function (data) {
                        if (!response.ok) {
                            const message = data.message || 'Unable to send reply.';
                            throw new Error(message);
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (!data || !data.reply) return;
                    appendReply(data.reply);
                    replyForm.reset();
                    clearReplyTarget();
                    if (messageInput) {
                        messageInput.style.height = 'auto';
                    }
                    updateAttachmentCount();
                    if (messageInput) messageInput.focus();
                })
                .catch(function (error) {
                    if (!replyError) return;
                    replyError.textContent = error.message || 'Unable to send reply.';
                    replyError.classList.remove('hidden');
                })
                .finally(function () {
                    setSendState(false);
                });
        });
    }

    const attachmentPreview = window.ModalKit
        ? window.ModalKit.bindAttachmentPreview({
            modal: '#attachment-modal',
            title: '#attachment-modal-title',
            image: '#attachment-modal-image',
            frame: '#attachment-modal-frame',
            closeButton: '#attachment-modal-close',
            triggerSelector: null,
        })
        : null;

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-more-btn') && !event.target.closest('.js-more-menu')) {
            closeMenus();
        }

        const link = event.target.closest('.js-attachment-link');
        if (!link) return;

        event.preventDefault();
        if (attachmentPreview) {
            attachmentPreview.openFromLink(link);
        }
    });
});
</script>
@endsection

