@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources')

@section('content')
@php
    $department = strtolower((string) auth()->user()->department);
    $clientCompanyLogo = str_contains($department, 'ione')
        ? asset('images/ione-logo.png')
        : asset('images/DICT-logo.png');
    $supportCompanyLogo = asset('images/ione-logo.png');
@endphp
<style>
#conversation-thread {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

#conversation-thread::-webkit-scrollbar {
    width: 0;
    height: 0;
}

#conversation-thread:hover {
    scrollbar-width: thin;
}

#conversation-thread:hover::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

#conversation-thread:hover::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}

#conversation-thread:hover::-webkit-scrollbar-track {
    background: transparent;
}
</style>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-3">
        <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Tickets
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
                            @if($ticket->assignedUser)
                                <span>&bull;</span>
                                <span>Assigned to {{ $ticket->assignedUser->name }}</span>
                            @endif
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
                            <p class="mt-1 text-sm text-slate-500">Message thread between you and support.</p>
                        </div>
                        <span id="message-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $ticket->replies->where('is_internal', false)->count() + 1 }} messages
                        </span>
                    </div>
                </div>

                <div id="conversation-thread" class="h-[560px] space-y-4 overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white px-4 py-5 sm:px-6">
                    <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $ticket->created_at->toIso8601String() }}">
                        {{ $ticket->created_at->greaterThan(now()->subDay()) ? $ticket->created_at->format('g:i A') : $ticket->created_at->format('M j, Y') }}
                    </div>

                    <div class="js-chat-row flex justify-end" data-created-at="{{ $ticket->created_at->toIso8601String() }}">
                        <div class="flex w-full max-w-3xl items-start justify-end gap-2">
                            <div class="order-2 mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                <img src="{{ $clientCompanyLogo }}" alt="Client company logo" class="h-7 w-7 object-contain">
                            </div>
                            <div class="order-1 max-w-[82%] rounded-2xl border border-ione-blue-200 bg-ione-blue-50 px-4 py-3 shadow-sm">
                                <p class="js-message-text whitespace-pre-wrap text-sm leading-6 text-slate-800">{!! nl2br(e($ticket->description)) !!}</p>

                                @if($ticket->attachments->count() > 0)
                                    <div class="js-attachments-wrap mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach($ticket->attachments as $attachment)
                                            <a href="{{ $attachment->download_url }}"
                                               class="js-attachment-link flex items-center rounded-xl border border-ione-blue-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50"
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
                    @foreach($ticket->replies->where('is_internal', false)->sortBy('created_at') as $reply)
                        @php
                            $fromSupport = $reply->user->canAccessAdminTickets();
                            $avatarLogo = $fromSupport ? $supportCompanyLogo : $clientCompanyLogo;
                            $showTimestamp = $reply->created_at->diffInMinutes($lastTimestamp) >= 15;
                            $canManageReply = (int) ($reply->user_id === auth()->id());
                            $lastTimestamp = $reply->created_at;
                        @endphp
                        @if($showTimestamp)
                            <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $reply->created_at->toIso8601String() }}">
                                {{ $reply->created_at->greaterThan(now()->subDay()) ? $reply->created_at->format('g:i A') : $reply->created_at->format('M j, Y') }}
                            </div>
                        @endif

                        <div
                            class="js-chat-row flex {{ $fromSupport ? 'justify-start' : 'justify-end' }}"
                            data-created-at="{{ $reply->created_at->toIso8601String() }}"
                            data-reply-id="{{ $reply->id }}"
                            data-is-support="{{ $fromSupport ? '1' : '0' }}"
                            data-can-manage="{{ $canManageReply }}"
                        >
                            <div class="flex w-full max-w-3xl items-start gap-2 {{ $fromSupport ? '' : 'justify-end' }}">
                                <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white {{ $fromSupport ? '' : 'order-2' }}">
                                    <img src="{{ $avatarLogo }}" alt="{{ $fromSupport ? 'Support' : 'Client' }} company logo" class="h-7 w-7 object-contain">
                                </div>
                                <div
                                    class="js-chat-bubble relative group max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm {{ $fromSupport ? 'border-slate-200 bg-white' : 'order-1 border-ione-blue-200 bg-ione-blue-50' }}"
                                    data-message="{{ e($reply->message) }}"
                                    data-deleted="{{ $reply->deleted_at ? '1' : '0' }}"
                                    data-edited="{{ $reply->edited_at ? '1' : '0' }}"
                                >
                                    <div class="js-state-row mb-1 flex items-center gap-2 {{ $reply->edited_at || $reply->deleted_at ? '' : 'hidden' }}">
                                        <span class="js-edited-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 {{ $reply->edited_at ? '' : 'hidden' }}">Edited</span>
                                        <span class="js-deleted-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 {{ $reply->deleted_at ? '' : 'hidden' }}">Deleted</span>
                                    </div>
                                    <div class="js-message-actions absolute {{ $fromSupport ? '-right-10' : '-left-[4.75rem]' }} top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100">
                                        @if($canManageReply && $reply->created_at->greaterThanOrEqualTo(now()->subHours(3)))
                                            <div class="relative">
                                                <button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="More actions">
                                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                                    </svg>
                                                </button>
                                                <div class="js-more-menu absolute {{ $fromSupport ? 'right-0' : 'left-0' }} z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
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

                                    <div class="js-reply-reference mb-2 {{ $reply->replyTo ? '' : 'hidden' }}">
                                        <p class="js-reply-reference-label mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/>
                                            </svg>
                                            {{ $fromSupport ? 'Support replied' : 'You replied' }}
                                        </p>
                                        <div class="js-reply-reference-text rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">
                                            {{ $reply->replyTo ? \Illuminate\Support\Str::limit($reply->replyTo->message, 120) : '' }}
                                        </div>
                                    </div>

                                    <p class="js-message-text whitespace-pre-wrap text-sm leading-6 {{ $reply->deleted_at ? 'italic text-slate-500' : 'text-slate-800' }}">{!! nl2br(e($reply->message)) !!}</p>

                                    @if($reply->attachments && $reply->attachments->count() > 0 && !$reply->deleted_at)
                                        <div class="js-attachments-wrap mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach($reply->attachments as $attachment)
                                                <a href="{{ $attachment->download_url }}"
                                                   class="js-attachment-link flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50"
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
                    @endforeach
                </div>

                <div class="border-t border-slate-200 px-4 pb-4 pt-2 sm:px-6">
                    @if(!in_array($ticket->status, ['closed']))
                        <form
                            id="ticket-reply-form"
                            action="{{ route('client.tickets.reply', $ticket) }}"
                            method="POST"
                            enctype="multipart/form-data"
                            class="space-y-3"
                            data-client-logo="{{ $clientCompanyLogo }}"
                            data-update-url-template="{{ route('client.tickets.replies.update', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}"
                            data-delete-url-template="{{ route('client.tickets.replies.delete', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}"
                        >
                            @csrf
                            <p id="reply-error" class="hidden text-xs font-medium text-rose-600"></p>
                            <input type="hidden" id="reply_to_id" name="reply_to_id" value="">
                            <div id="reply-target-banner" class="hidden items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 shadow-sm">
                                <span id="reply-target-text" class="truncate pr-3 font-medium"></span>
                                <button type="button" id="clear-reply-target" class="rounded-md px-2 py-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">Cancel</button>
                            </div>
                            <div class="px-0 py-0">
                                <textarea
                                    name="message"
                                    id="message"
                                    rows="1"
                                    required
                                    class="block max-h-48 min-h-[44px] w-full resize-none overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                                    placeholder="Write your message..."
                                ></textarea>

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <label for="attachments" class="inline-flex cursor-pointer items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100">Attach</label>
                                        <input
                                            type="file"
                                            name="attachments[]"
                                            id="attachments"
                                            multiple
                                            class="hidden"
                                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt"
                                        >
                                        <p id="attachment-count" class="text-xs text-slate-500">No files selected</p>
                                    </div>
                                    <button id="send-reply-btn" type="submit" class="inline-flex items-center rounded-full bg-[#033b3d] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#022a2c] disabled:cursor-not-allowed disabled:opacity-70">
                                        Send
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Max 10MB per file.</p>
                            </div>
                        </form>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                            This ticket is closed. Reply is disabled.
                        </div>
                    @endif
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
                            <dd class="text-sm text-gray-900">{{ $ticket->name ?? auth()->user()->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->contact_number ?? (auth()->user()->phone ?? 'Not provided') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->email ?? auth()->user()->email }}</dd>
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
                            <dt class="text-sm font-medium text-gray-500">Priority</dt>
                            <dd class="text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->priority_color }}">
                                    @if(strtolower($ticket->priority) === 'urgent')
                                        <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2.25m0 3.75h.01M10.34 3.94 1.82 18a2.25 2.25 0 001.92 3.38h16.52a2.25 2.25 0 001.92-3.38L13.66 3.94a2.25 2.25 0 00-3.32 0z"></path>
                                        </svg>
                                    @endif
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $ticket->status_color }}">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </dd>
                        </div>
                        @if($ticket->assignedUser)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                                <dd class="text-sm text-gray-900">{{ $ticket->assignedUser->name }}</dd>
                            </div>
                        @endif
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
                    @if($ticket->status === 'resolved' && !$ticket->satisfaction_rating)
                        <!-- Satisfaction Rating -->
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-green-800 mb-3">Rate Our Support</h4>
                            <form action="{{ route('client.tickets.rate', $ticket) }}" method="POST">
                                @csrf
                                <div class="space-y-3">
                                    <div>
                                        <label class="form-label text-green-700">Rating (1-5 stars)</label>
                                        <select name="rating" class="form-input" required>
                                            <option value="">Select Rating</option>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Good</option>
                                            <option value="3">3 - Average</option>
                                            <option value="2">2 - Poor</option>
                                            <option value="1">1 - Very Poor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-green-700">Comment (optional)</label>
                                        <textarea name="comment" rows="3" class="form-input" placeholder="Tell us about your experience..."></textarea>
                                    </div>
                                    <button type="submit" class="btn-success w-full">Submit Rating</button>
                                </div>
                            </form>
                        </div>
                    @elseif($ticket->satisfaction_rating)
                        <!-- Show Rating -->
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Your Rating</h4>
                            <div class="flex items-center mb-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $ticket->satisfaction_rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            </div>
                            @if($ticket->satisfaction_comment)
                                <p class="text-sm text-blue-700">{{ $ticket->satisfaction_comment }}</p>
                            @endif
                        </div>
                    @endif

                    @if(!in_array($ticket->status, ['resolved', 'closed']))
                        <button type="button" id="open-resolve-ticket-modal" class="btn-success w-full">
                            Mark as Resolved
                        </button>
                    @endif

                    @if(!in_array($ticket->status, ['closed']))
                        <!-- Close Ticket -->
                        <button type="button" id="open-close-ticket-modal" class="btn-secondary w-full">
                            Close Ticket
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(!in_array($ticket->status, ['resolved', 'closed']))
<div id="resolve-ticket-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-60" data-resolve-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-base font-medium text-gray-900">Mark Ticket as Resolved</h3>
                <p class="mt-1 text-sm text-gray-600">Are you sure you want to mark this ticket as resolved?</p>
            </div>
            <form action="{{ route('client.tickets.resolve', $ticket) }}" method="POST" class="p-4">
                @csrf
                <div class="flex justify-end space-x-3">
                    <button type="button" id="resolve-ticket-cancel" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-success">Confirm Resolve</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(!in_array($ticket->status, ['closed']))
<div id="close-ticket-modal" class="fixed inset-0 z-50 {{ $errors->has('close_reason') ? '' : 'hidden' }}">
    <div class="absolute inset-0 bg-black bg-opacity-60" data-close-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-lg shadow-xl">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-base font-medium text-gray-900">Close Ticket as Unresolved</h3>
                <p class="mt-1 text-sm text-gray-600">Please provide a reason before closing this ticket.</p>
            </div>
            <form action="{{ route('client.tickets.close', $ticket) }}" method="POST" class="p-4 space-y-4">
                @csrf
                <div>
                    <label for="close_reason" class="form-label">Reason <span class="text-red-600">*</span></label>
                    <textarea name="close_reason" id="close_reason" rows="4" required
                              class="form-input @error('close_reason') border-red-500 @enderror"
                              placeholder="Why are you closing this ticket unresolved?">{{ old('close_reason') }}</textarea>
                    @error('close_reason')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="close-ticket-cancel" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-danger">Close Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

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
    if (!window.ModalKit) return;

    window.ModalKit.bindAttachmentPreview({
        modal: '#attachment-modal',
        title: '#attachment-modal-title',
        image: '#attachment-modal-image',
        frame: '#attachment-modal-frame',
        closeButton: '#attachment-modal-close',
        triggerSelector: '.js-attachment-link',
    });

    window.ModalKit.bindById('resolve-ticket-modal', {
        openButtons: ['#open-resolve-ticket-modal'],
        closeButtons: ['#resolve-ticket-cancel'],
    });

    const closeModal = document.getElementById('close-ticket-modal');
    window.ModalKit.bindById('close-ticket-modal', {
        openButtons: ['#open-close-ticket-modal'],
        closeButtons: ['#close-ticket-cancel'],
        initialOpen: closeModal ? !closeModal.classList.contains('hidden') : false,
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const thread = document.getElementById('conversation-thread');
    const replyForm = document.getElementById('ticket-reply-form');
    const sendReplyButton = document.getElementById('send-reply-btn');
    const messageCount = document.getElementById('message-count');
    const replyError = document.getElementById('reply-error');
    const messageInput = document.getElementById('message');
    const attachmentsInput = document.getElementById('attachments');
    const attachmentCount = document.getElementById('attachment-count');
    const replyToInput = document.getElementById('reply_to_id');
    const replyTargetBanner = document.getElementById('reply-target-banner');
    const replyTargetText = document.getElementById('reply-target-text');
    const clearReplyTargetButton = document.getElementById('clear-reply-target');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const clientLogo = replyForm ? replyForm.dataset.clientLogo : '';
    const updateUrlTemplate = replyForm ? replyForm.dataset.updateUrlTemplate : '';
    const deleteUrlTemplate = replyForm ? replyForm.dataset.deleteUrlTemplate : '';
    const TIMESTAMP_BREAK_MINUTES = 15;
    const EDIT_DELETE_WINDOW_MS = 3 * 60 * 60 * 1000;

    const autoResize = function () {
        if (!messageInput) return;
        const maxHeight = 192;
        messageInput.style.height = 'auto';
        const nextHeight = Math.min(messageInput.scrollHeight, maxHeight);
        messageInput.style.height = nextHeight + 'px';
        messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };

    const updateAttachmentCount = function () {
        if (!attachmentsInput || !attachmentCount) return;
        const fileCount = attachmentsInput.files ? attachmentsInput.files.length : 0;
        attachmentCount.textContent = fileCount === 0
            ? 'No files selected'
            : fileCount + (fileCount === 1 ? ' file selected' : ' files selected');
    };

    const createAttachmentLink = function (attachment) {
        const link = document.createElement('a');
        link.href = attachment.download_url;
        link.className = 'js-attachment-link flex items-center rounded-xl border border-ione-blue-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50';
        link.dataset.fileUrl = attachment.preview_url;
        link.dataset.fileName = attachment.original_filename;
        link.dataset.fileMime = attachment.mime_type;

        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', 'mr-2 h-4 w-4 text-slate-500');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('viewBox', '0 0 24 24');

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('d', 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13');
        icon.appendChild(path);

        const name = document.createElement('span');
        name.className = 'truncate';
        name.textContent = attachment.original_filename;

        link.appendChild(icon);
        link.appendChild(name);
        return link;
    };

    const closeMenus = function () {
        thread.querySelectorAll('.js-more-menu').forEach(function (menu) {
            menu.classList.add('hidden');
        });
    };

    const setReplyTarget = function (replyId, message) {
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = String(replyId);
        replyTargetText.textContent = 'Replying to: ' + (message || '').slice(0, 120);
        replyTargetBanner.classList.remove('hidden');
        replyTargetBanner.classList.add('flex');
    };

    const clearReplyTarget = function () {
        if (!replyToInput || !replyTargetBanner || !replyTargetText) return;
        replyToInput.value = '';
        replyTargetText.textContent = '';
        replyTargetBanner.classList.remove('flex');
        replyTargetBanner.classList.add('hidden');
    };

    const createTimeSeparator = function (dateValue) {
        const date = new Date(dateValue);
        const separator = document.createElement('div');
        separator.className = 'js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400';
        separator.dataset.time = date.toISOString();
        separator.textContent = formatTimestampLabel(date);
        return separator;
    };

    const isWithinEditDeleteWindow = function (isoDate) {
        const createdAt = new Date(isoDate);
        if (Number.isNaN(createdAt.getTime())) {
            return false;
        }

        return (Date.now() - createdAt.getTime()) <= EDIT_DELETE_WINDOW_MS;
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

    const renderTimeSeparators = function () {
        if (!thread) return;

        thread.querySelectorAll('.js-time-separator').forEach(function (el) {
            el.remove();
        });

        const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
        let lastDate = null;

        rows.forEach(function (row) {
            const createdAt = row.dataset.createdAt;
            if (!createdAt) return;

            const currentDate = new Date(createdAt);
            if (!lastDate || ((currentDate - lastDate) / 60000) >= TIMESTAMP_BREAK_MINUTES) {
                row.parentNode.insertBefore(createTimeSeparator(createdAt), row);
            }
            lastDate = currentDate;
        });
    };

    const buildOwnMessageControls = function (isDeleted, createdAtIso) {
        const controls = document.createElement('div');
        controls.className = 'js-message-actions absolute -left-[4.75rem] top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100';

        const replyButton = document.createElement('button');
        replyButton.type = 'button';
        replyButton.className = 'js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]';
        replyButton.setAttribute('aria-label', 'Reply to this message');
        replyButton.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg>';

        if (!isDeleted && isWithinEditDeleteWindow(createdAtIso)) {
            const menuWrap = document.createElement('div');
            menuWrap.className = 'relative';

            const moreButton = document.createElement('button');
            moreButton.type = 'button';
            moreButton.className = 'js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]';
            moreButton.setAttribute('aria-label', 'More actions');
            moreButton.innerHTML = '<svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>';

            const menu = document.createElement('div');
            menu.className = 'js-more-menu absolute left-0 z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg';

            const editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.className = 'js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50';
            editButton.textContent = 'Edit';

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50';
            deleteButton.textContent = 'Delete';

            menu.appendChild(editButton);
            menu.appendChild(deleteButton);

            menuWrap.appendChild(moreButton);
            menuWrap.appendChild(menu);
            controls.appendChild(menuWrap);
        }

        controls.appendChild(replyButton);
        return controls;
    };

    const createReferenceBlock = function (replyToMessage, labelText) {
        const ref = document.createElement('div');
        ref.className = 'js-reply-reference mb-2';

        const label = document.createElement('p');
        label.className = 'js-reply-reference-label mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500';
        label.innerHTML = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg><span>' + (labelText || 'You replied') + '</span>';

        const text = document.createElement('div');
        text.className = 'js-reply-reference-text rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700';

        if (replyToMessage) {
            text.textContent = replyToMessage;
        } else {
            ref.classList.add('hidden');
        }

        ref.appendChild(label);
        ref.appendChild(text);
        return ref;
    };

    const appendClientReply = function (reply) {
        if (!thread) return;
        const wrap = document.createElement('div');
        wrap.className = 'js-chat-row flex justify-end';
        wrap.dataset.createdAt = reply.created_at_iso || new Date().toISOString();
        wrap.dataset.replyId = String(reply.id || '');
        wrap.dataset.isSupport = '0';
        wrap.dataset.canManage = '1';

        const rowContent = document.createElement('div');
        rowContent.className = 'flex w-full max-w-3xl items-start justify-end gap-2';

        const avatar = document.createElement('div');
        avatar.className = 'order-2 mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white';
        avatar.innerHTML = '<img src="' + clientLogo + '" alt="Client company logo" class="h-7 w-7 object-contain">';

        const bubble = document.createElement('div');
        bubble.className = 'js-chat-bubble group order-1 max-w-[82%] rounded-2xl border border-ione-blue-200 bg-ione-blue-50 px-4 py-3 shadow-sm';
        bubble.dataset.message = reply.message || '';
        bubble.dataset.deleted = reply.deleted ? '1' : '0';
        bubble.dataset.edited = reply.edited ? '1' : '0';

        const meta = document.createElement('div');
        meta.className = 'js-state-row mb-1 flex items-center gap-2 ' + ((reply.edited || reply.deleted) ? '' : 'hidden');
        meta.innerHTML = '<span class="js-edited-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 ' + (reply.edited ? '' : 'hidden') + '">Edited</span><span class="js-deleted-badge rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 ' + (reply.deleted ? '' : 'hidden') + '">Deleted</span>';
        meta.appendChild(buildOwnMessageControls(!!reply.deleted, wrap.dataset.createdAt));

        const reference = createReferenceBlock(reply.reply_to_message, 'You replied');

        const text = document.createElement('p');
        text.className = 'js-message-text whitespace-pre-wrap text-sm leading-6 ' + (reply.deleted ? 'italic text-slate-500' : 'text-slate-800');
        text.textContent = reply.message || '';

        bubble.appendChild(meta);
        bubble.appendChild(reference);
        bubble.appendChild(text);

        if (!reply.deleted && reply.attachments && reply.attachments.length > 0) {
            const attachmentGrid = document.createElement('div');
            attachmentGrid.className = 'js-attachments-wrap mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2';
            reply.attachments.forEach(function (attachment) {
                attachmentGrid.appendChild(createAttachmentLink(attachment));
            });
            bubble.appendChild(attachmentGrid);
        }

        rowContent.appendChild(avatar);
        rowContent.appendChild(bubble);
        wrap.appendChild(rowContent);
        thread.appendChild(wrap);
        renderTimeSeparators();
        thread.scrollTop = thread.scrollHeight;
    };

    const applyReplyState = function (row, reply) {
        const bubble = row.querySelector('.js-chat-bubble');
        if (!bubble) return;

        bubble.dataset.message = reply.message || '';
        bubble.dataset.deleted = reply.deleted ? '1' : '0';
        bubble.dataset.edited = reply.edited ? '1' : '0';

        const messageText = row.querySelector('.js-message-text');
        if (messageText) {
            messageText.textContent = reply.message || '';
            messageText.classList.toggle('italic', !!reply.deleted);
            messageText.classList.toggle('text-slate-500', !!reply.deleted);
            messageText.classList.toggle('text-slate-800', !reply.deleted);
        }

        const editedBadge = row.querySelector('.js-edited-badge');
        if (editedBadge) editedBadge.classList.toggle('hidden', !reply.edited);

        const deletedBadge = row.querySelector('.js-deleted-badge');
        if (deletedBadge) deletedBadge.classList.toggle('hidden', !reply.deleted);

        const stateRow = row.querySelector('.js-state-row');
        if (stateRow) stateRow.classList.toggle('hidden', !(reply.edited || reply.deleted));

        const reference = row.querySelector('.js-reply-reference');
        if (reference) {
            const referenceText = reference.querySelector('.js-reply-reference-text');
            if (referenceText) {
                referenceText.textContent = reply.reply_to_message || '';
            }
            reference.classList.toggle('hidden', !reply.reply_to_message);
        }

        const attachmentsWrap = row.querySelector('.js-attachments-wrap');
        if (attachmentsWrap && reply.deleted) attachmentsWrap.remove();

        if (reply.deleted) {
            const menu = row.querySelector('.js-more-menu');
            if (menu) menu.innerHTML = '';
        }
    };

    const incrementMessageCount = function () {
        if (!messageCount) return;
        const value = parseInt((messageCount.textContent || '').replace(/\D/g, ''), 10);
        if (!Number.isNaN(value)) {
            const next = value + 1;
            messageCount.textContent = next + (next === 1 ? ' message' : ' messages');
        }
    };

    const replyEndpoint = function (template, replyId) {
        return template ? template.replace('__REPLY__', String(replyId)) : '';
    };

    if (thread) {
        renderTimeSeparators();
        thread.scrollTop = thread.scrollHeight;
    }

    if (messageInput) {
        autoResize();
        messageInput.addEventListener('input', autoResize);

        messageInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && replyForm) {
                event.preventDefault();
                const hasText = messageInput.value.trim().length > 0;
                if (hasText) {
                    replyForm.requestSubmit();
                }
            }
        });
    }

    if (replyForm && sendReplyButton) {
        replyForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            closeMenus();
            if (replyError) {
                replyError.classList.add('hidden');
                replyError.textContent = '';
            }

            sendReplyButton.disabled = true;
            sendReplyButton.textContent = 'Sending...';

            try {
                const response = await fetch(replyForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(replyForm),
                });

                if (!response.ok) {
                    if (response.status === 422) {
                        const validationData = await response.json();
                        const firstError = validationData && validationData.errors
                            ? Object.values(validationData.errors)[0][0]
                            : 'Unable to send reply.';
                        if (replyError) {
                            replyError.textContent = firstError;
                            replyError.classList.remove('hidden');
                        }
                    } else {
                        if (replyError) {
                            replyError.textContent = 'Unable to send reply. Please try again.';
                            replyError.classList.remove('hidden');
                        }
                    }
                    return;
                }

                const data = await response.json();
                if (data && data.reply) {
                    appendClientReply(data.reply);
                    incrementMessageCount();
                    replyForm.reset();
                    clearReplyTarget();
                    updateAttachmentCount();
                    if (messageInput) {
                        messageInput.value = '';
                        autoResize();
                        messageInput.focus();
                    }
                }
            } catch (error) {
                if (replyError) {
                    replyError.textContent = 'Network error. Please try again.';
                    replyError.classList.remove('hidden');
                }
            } finally {
                sendReplyButton.disabled = false;
                sendReplyButton.textContent = 'Send';
            }
        });
    }

    if (clearReplyTargetButton) {
        clearReplyTargetButton.addEventListener('click', clearReplyTarget);
    }

    if (thread) {
    thread.addEventListener('click', async function (event) {
        const moreButton = event.target.closest('.js-more-btn');
        if (moreButton) {
            const row = moreButton.closest('.js-chat-row');
            if (row && !isWithinEditDeleteWindow(row.dataset.createdAt)) {
                if (replyError) {
                    replyError.textContent = 'You can only edit or delete messages within 3 hours.';
                    replyError.classList.remove('hidden');
                }
                return;
            }
            const menu = moreButton.parentElement.querySelector('.js-more-menu');
            closeMenus();
            if (menu) menu.classList.toggle('hidden');
            return;
        }

        const replyButton = event.target.closest('.js-reply-msg');
        if (replyButton) {
            const row = replyButton.closest('.js-chat-row');
            if (!row) return;
            const bubble = row.querySelector('.js-chat-bubble');
            const message = bubble ? bubble.dataset.message : '';
            const replyId = row.dataset.replyId;
            if (replyId) {
                setReplyTarget(replyId, message);
                if (messageInput) messageInput.focus();
            }
            closeMenus();
            return;
        }

        const editButton = event.target.closest('.js-edit-msg');
        if (editButton) {
            const row = editButton.closest('.js-chat-row');
            if (!row) return;
            if (row.dataset.canManage !== '1') {
                closeMenus();
                if (replyError) {
                    replyError.textContent = 'You can only edit your own messages.';
                    replyError.classList.remove('hidden');
                }
                return;
            }
            if (!isWithinEditDeleteWindow(row.dataset.createdAt)) {
                closeMenus();
                if (replyError) {
                    replyError.textContent = 'You can only edit or delete messages within 3 hours.';
                    replyError.classList.remove('hidden');
                }
                return;
            }
            const bubble = row.querySelector('.js-chat-bubble');
            const currentMessage = bubble ? (bubble.dataset.message || '') : '';
            const editedMessage = window.prompt('Edit message', currentMessage);
            closeMenus();

            if (editedMessage === null || editedMessage.trim() === '' || editedMessage === currentMessage) {
                return;
            }

            try {
                const response = await fetch(replyEndpoint(updateUrlTemplate, row.dataset.replyId), {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: editedMessage }),
                });

                if (!response.ok) {
                    let errorMessage = 'Unable to edit message right now.';
                    try {
                        const errorPayload = await response.json();
                        if (errorPayload && errorPayload.message) {
                            errorMessage = errorPayload.message;
                        }
                    } catch (parseError) {
                    }
                    throw new Error(errorMessage);
                }
                const data = await response.json();
                if (data.reply) {
                    applyReplyState(row, data.reply);
                }
            } catch (error) {
                if (replyError) {
                    replyError.textContent = error && error.message ? error.message : 'Unable to edit message right now.';
                    replyError.classList.remove('hidden');
                }
            }
            return;
        }

        const deleteButton = event.target.closest('.js-delete-msg');
        if (deleteButton) {
            const row = deleteButton.closest('.js-chat-row');
            if (!row) return;
            if (row.dataset.canManage !== '1') {
                closeMenus();
                if (replyError) {
                    replyError.textContent = 'You can only delete your own messages.';
                    replyError.classList.remove('hidden');
                }
                return;
            }
            if (!isWithinEditDeleteWindow(row.dataset.createdAt)) {
                closeMenus();
                if (replyError) {
                    replyError.textContent = 'You can only edit or delete messages within 3 hours.';
                    replyError.classList.remove('hidden');
                }
                return;
            }
            closeMenus();

            if (!window.confirm('Delete this message?')) {
                return;
            }

            try {
                const response = await fetch(replyEndpoint(deleteUrlTemplate, row.dataset.replyId), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (!response.ok) {
                    let errorMessage = 'Unable to delete message right now.';
                    try {
                        const errorPayload = await response.json();
                        if (errorPayload && errorPayload.message) {
                            errorMessage = errorPayload.message;
                        }
                    } catch (parseError) {
                    }
                    throw new Error(errorMessage);
                }
                const data = await response.json();
                if (data.reply) {
                    applyReplyState(row, data.reply);
                }
            } catch (error) {
                if (replyError) {
                    replyError.textContent = error && error.message ? error.message : 'Unable to delete message right now.';
                    replyError.classList.remove('hidden');
                }
            }
        }
    });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-more-btn') && !event.target.closest('.js-more-menu')) {
            closeMenus();
        }
    });

    if (attachmentsInput && attachmentCount) {
        attachmentsInput.addEventListener('change', updateAttachmentCount);
    }
});
</script>
@endsection


