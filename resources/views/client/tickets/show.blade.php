@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources')

@section('content')
@php
    $departmentLogo = static function (?string $department, bool $isSupport = false): string {
        if ($isSupport) return asset('images/iOne Logo.png');
        return \App\Models\User::departmentBrandAssets($department)['logo_url'];
    };
    $clientCompanyLogo = $departmentLogo(auth()->user()->department);
    $supportCompanyLogo = asset('images/iOne Logo.png');
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-client-ticket-show-page>
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
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                            <span class="font-medium">{{ $ticket->ticket_number }}</span>
                            <span class="hidden text-gray-300 sm:inline">&bull;</span>
                            <span>Created {{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</span>
                            @if($ticket->assignedUser)
                                <span class="hidden text-gray-300 sm:inline">&bull;</span>
                                <span>Assigned to {{ $ticket->assignedUser->publicDisplayName() }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
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

                <div id="conversation-thread" class="h-[560px] space-y-4 overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white px-4 py-5 sm:px-6" data-ticket-id="{{ (int) $ticket->id }}" data-replies-url="{{ route('client.tickets.replies.feed', $ticket) }}" data-seen-url="{{ route('client.notifications.seen', $ticket) }}">
                    <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $ticket->created_at->toIso8601String() }}">
                        {{ $ticket->created_at->greaterThan(now()->subDay()) ? $ticket->created_at->format('g:i A') : $ticket->created_at->format('M j, Y') }}
                    </div>

                    <div class="js-chat-row flex justify-end" data-created-at="{{ $ticket->created_at->toIso8601String() }}">
                        <div class="flex w-full max-w-3xl items-start justify-end gap-2">
                            <div class="order-2 mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                <img src="{{ $clientCompanyLogo }}" alt="Client company logo" class="avatar-logo">
                            </div>
                            <div class="order-1 w-fit max-w-[82%] rounded-2xl border border-ione-blue-200 bg-ione-blue-50 px-4 py-3 shadow-sm">
                                <p class="js-message-text whitespace-pre-wrap text-sm leading-6 text-slate-800">{!! nl2br(e($ticket->description)) !!}</p>

                                @if($ticket->attachments->count() > 0)
                                    <div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">
                                        @foreach($ticket->attachments as $attachment)
                                            @if(str_starts_with((string) $attachment->mime_type, 'image/'))
                                                <a href="{{ $attachment->download_url }}"
                                                   class="js-attachment-link block w-[240px] max-w-full overflow-hidden rounded-xl border border-ione-blue-200 bg-white p-2 text-sm transition hover:bg-slate-50"
                                                   data-file-url="{{ $attachment->preview_url }}"
                                                   data-file-name="{{ $attachment->original_filename }}"
                                                   data-file-mime="{{ $attachment->mime_type }}">
                                                    <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_filename }}" class="h-36 w-full rounded-lg object-cover">
                                                    <span class="mt-2 block truncate text-xs text-slate-600">{{ $attachment->original_filename }}</span>
                                                </a>
                                            @else
                                                <a href="{{ $attachment->download_url }}"
                                                   class="{{ $attachment->preview_url ? 'js-attachment-link ' : '' }}flex max-w-full items-center rounded-xl border border-ione-blue-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50"
                                                   @if($attachment->preview_url)
                                                       data-file-url="{{ $attachment->preview_url }}"
                                                       data-file-name="{{ $attachment->original_filename }}"
                                                       data-file-mime="{{ $attachment->mime_type }}"
                                                   @endif>
                                                    <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                    </svg>
                                                    <span class="truncate">{{ $attachment->original_filename }}</span>
                                                </a>
                                            @endif
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
                            $isDeleted = (bool) $reply->deleted_at;
                            $showEditedBadge = (bool) $reply->edited_at && !$isDeleted;
                            $avatarLogo = $departmentLogo(data_get($reply, 'user.department'), $fromSupport);
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
                                    <img src="{{ $avatarLogo }}" alt="{{ $fromSupport ? 'Support' : 'Client' }} company logo" class="avatar-logo">
                                </div>
                                <div
                                    class="js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm {{ $fromSupport ? 'border-slate-200 bg-white' : 'order-1 border-ione-blue-200 bg-ione-blue-50' }} {{ $isDeleted ? 'chat-bubble-deleted' : '' }}"
                                    data-message="{{ e($reply->message) }}"
                                    data-deleted="{{ $reply->deleted_at ? '1' : '0' }}"
                                    data-edited="{{ $reply->edited_at ? '1' : '0' }}"
                                >
                                    <div class="js-state-row mb-1 flex items-center gap-2 {{ $showEditedBadge || $isDeleted ? '' : 'hidden' }}">
                                        <span class="js-edited-badge chat-meta-badge {{ $showEditedBadge ? '' : 'hidden' }}">Edited</span>
                                        <span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted {{ $isDeleted ? '' : 'hidden' }}">Deleted</span>
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
                                        <div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">
                                            @foreach($reply->attachments as $attachment)
                                                @if(str_starts_with((string) $attachment->mime_type, 'image/'))
                                                    <a href="{{ $attachment->download_url }}"
                                                       class="js-attachment-link block w-[240px] max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-2 text-sm transition hover:bg-slate-50"
                                                       data-file-url="{{ $attachment->preview_url }}"
                                                       data-file-name="{{ $attachment->original_filename }}"
                                                       data-file-mime="{{ $attachment->mime_type }}">
                                                        <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_filename }}" class="h-36 w-full rounded-lg object-cover">
                                                        <span class="mt-2 block truncate text-xs text-slate-600">{{ $attachment->original_filename }}</span>
                                                    </a>
                                                @else
                                                    <a href="{{ $attachment->download_url }}"
                                                       class="{{ $attachment->preview_url ? 'js-attachment-link ' : '' }}flex max-w-full items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50"
                                                       @if($attachment->preview_url)
                                                           data-file-url="{{ $attachment->preview_url }}"
                                                           data-file-name="{{ $attachment->original_filename }}"
                                                           data-file-mime="{{ $attachment->mime_type }}"
                                                       @endif>
                                                        <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                        </svg>
                                                        <span class="truncate">{{ $attachment->original_filename }}</span>
                                                    </a>
                                                @endif
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
                            data-ticket-id="{{ (int) $ticket->id }}"
                            data-client-logo="{{ $clientCompanyLogo }}"
                            data-support-logo="{{ $supportCompanyLogo }}"
                            data-replies-url="{{ route('client.tickets.replies.feed', $ticket) }}"
                            data-seen-url="{{ route('client.notifications.seen', $ticket) }}"
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
                            <div id="edit-target-banner" class="hidden items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 shadow-sm">
                                <span id="edit-target-text" class="truncate pr-3 font-medium"></span>
                                <button type="button" id="cancel-edit-target" class="rounded-md px-2 py-0.5 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900">Cancel edit</button>
                            </div>
                            <div class="px-0 py-0">
                                <textarea
                                    name="message"
                                    id="message"
                                    rows="1"
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
                                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.xls,.xlsx"
                                        >
                                        <p id="attachment-count" class="text-xs text-slate-500">No files selected</p>
                                    </div>
                                    <button id="send-reply-btn" type="submit" class="inline-flex items-center rounded-full bg-[#033b3d] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#022a2c] disabled:cursor-not-allowed disabled:opacity-70">
                                        Send
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Add a message or at least one attachment. Max 10MB per file.</p>
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
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Assigned To</dt>
                            <dd class="text-sm text-gray-900">{{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}</dd>
                        </div>
                        @if(in_array($ticket->status, ['resolved', 'closed'], true))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Resolved/Reviewed By</dt>
                                <dd class="text-sm text-gray-900">{{ $ticket->assignedUser?->publicDisplayName() ?? 'Unassigned' }}</dd>
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

                </div>
            </div>
        </div>
    </div>
</div>

@if(!in_array($ticket->status, ['resolved', 'closed']))
<div id="resolve-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-resolve-ticket-overlay="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="px-5 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Mark Ticket as Resolved</h3>
                <p class="mt-1 text-sm text-slate-600">Confirm this ticket is resolved before proceeding.</p>
            </div>
            <form action="{{ route('client.tickets.resolve', $ticket) }}" method="POST" class="space-y-4 p-5" data-submit-feedback>
                @csrf
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                    <input id="resolve_confirm_checkbox" type="checkbox" required class="ticket-checkbox mt-0.5">
                    <span class="leading-5">I confirm this ticket has been resolved and can be marked as closed for support follow-up.</span>
                </label>
                <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                    <button type="button" id="resolve-ticket-cancel" class="btn-secondary sm:min-w-[110px]">Cancel</button>
                    <button id="resolve-confirm-submit" type="submit" class="btn-success sm:min-w-[160px] disabled:cursor-not-allowed disabled:opacity-60" data-loading-text="Resolving..." disabled>Confirm Resolve</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div id="attachment-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-close="true"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-5xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="attachment-modal-title" class="text-sm font-medium text-slate-900">Attachment Preview</h3>
                <button type="button" id="attachment-modal-close" class="text-xl leading-none text-slate-500 hover:text-slate-700">&times;</button>
            </div>
            <div class="bg-slate-50 p-4">
                <img id="attachment-modal-image" class="hidden w-auto max-w-full h-auto max-h-[75vh] mx-auto" alt="Attachment preview">
                <iframe id="attachment-modal-frame" class="hidden w-full h-[75vh] border-0 bg-white"></iframe>
            </div>
        </div>
    </div>
</div>

<div id="delete-reply-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="delete-reply"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Delete Message</h3>
                <p class="mt-1 text-sm text-slate-500">This will mark the selected message as deleted.</p>
            </div>
            <div class="space-y-4 px-5 py-4">
                <label for="delete-reply-confirm" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input id="delete-reply-confirm" type="checkbox" class="ticket-checkbox mt-0.5">
                    <span>I confirm that this message should be deleted.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="delete-reply">Cancel</button>
                    <button id="delete-reply-submit" type="button" class="btn-danger disabled:cursor-not-allowed disabled:opacity-60" disabled>Delete Message</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
