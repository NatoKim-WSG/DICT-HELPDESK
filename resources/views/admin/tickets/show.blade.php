@extends('layouts.app')

@section('title', 'Ticket #' . $ticket->ticket_number . ' - iOne Resources Inc.')

@section('content')
@php
    $departmentLogo = static function (?string $department, bool $isSupport = false): string {
        if ($isSupport) return asset('images/iOne Logo.png');
        return \App\Models\User::departmentBrandAssets($department)['logo_url'];
    };
    $clientCompanyLogo = $departmentLogo(data_get($ticket, 'user.department'));
    $supportCompanyLogo = asset('images/iOne Logo.png');
    $actor = auth()->user();
    $requiresDelayedClose = $actor && in_array($actor->normalizedRole(), [
        \App\Models\User::ROLE_TECHNICAL,
        \App\Models\User::ROLE_SUPER_USER,
    ], true);
    $closeAvailableAt = $ticket->resolved_at ? $ticket->resolved_at->copy()->addDay() : null;
    $canCloseNow = ! $requiresDelayedClose || ($closeAvailableAt && now()->gte($closeAvailableAt));
    $showDelayedCloseAction = $requiresDelayedClose && $ticket->status !== 'closed';
    $closedRevertWindowDays = 7;
    $revertDeadline = $ticket->closed_at ? $ticket->closed_at->copy()->addDays($closedRevertWindowDays) : null;
    $canRevertTicket = $ticket->status === 'resolved'
        || ($ticket->status === 'closed' && (! $revertDeadline || now()->lte($revertDeadline)));
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-admin-ticket-show-page>
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
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                                <span class="font-medium">{{ $ticket->ticket_number }}</span>
                                <span class="hidden text-gray-300 sm:inline">&bull;</span>
                                <span>Created {{ $ticket->created_at->format('M j, Y \a\t g:i A') }}</span>
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
                            <p class="mt-1 text-sm text-slate-500">Message thread between client and support.</p>
                        </div>
                        <span id="admin-message-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ $ticket->replies->count() + 1 }} messages
                        </span>
                    </div>
                </div>

                <div id="admin-conversation-thread" class="h-[560px] space-y-4 overflow-y-auto bg-gradient-to-b from-slate-50/80 to-white px-4 py-5 sm:px-6" data-ticket-id="{{ (int) $ticket->id }}" data-replies-url="{{ route('admin.tickets.replies.feed', $ticket) }}" data-seen-url="{{ route('admin.notifications.seen', $ticket) }}">
                    <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $ticket->created_at->toIso8601String() }}">
                        {{ $ticket->created_at->greaterThan(now()->subDay()) ? $ticket->created_at->format('g:i A') : $ticket->created_at->format('M j, Y') }}
                    </div>
                    <div class="js-chat-row flex justify-start" data-created-at="{{ $ticket->created_at->toIso8601String() }}">
                        <div class="flex w-full max-w-3xl items-start gap-2">
                            <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                <img src="{{ $clientCompanyLogo }}" alt="Client company logo" class="avatar-logo">
                            </div>
                            <div class="w-fit max-w-[82%] rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                <p class="js-message-text whitespace-pre-wrap text-sm leading-6 text-slate-800">{!! nl2br(e($ticket->description)) !!}</p>

                                @if($ticket->attachments->count() > 0)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach($ticket->attachments as $attachment)
                                            @if(str_starts_with((string) $attachment->mime_type, 'image/'))
                                                <a href="{{ $attachment->download_url }}"
                                                   class="js-attachment-link block w-[240px] max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-2 text-sm hover:bg-slate-50"
                                                   data-file-url="{{ $attachment->preview_url }}"
                                                   data-file-name="{{ $attachment->original_filename }}"
                                                   data-file-mime="{{ $attachment->mime_type }}">
                                                    <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_filename }}" class="h-36 w-full rounded-lg object-cover">
                                                    <span class="mt-2 block truncate text-xs text-slate-600">{{ $attachment->original_filename }}</span>
                                                </a>
                                            @else
                                                <a href="{{ $attachment->download_url }}"
                                                   class="{{ $attachment->preview_url ? 'js-attachment-link ' : '' }}flex max-w-full items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
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
                    @foreach($ticket->replies->sortBy('created_at') as $reply)
                        @php
                            $isInternal = (bool) $reply->is_internal;
                            $fromSupport = $reply->user->canAccessAdminTickets();
                            $isDeleted = (bool) $reply->deleted_at;
                            $showEditedBadge = (bool) $reply->edited_at && !$isDeleted;
                            $showTimestamp = $reply->created_at->diffInMinutes($lastTimestamp) >= 15;
                            $canManageReply = (int) ($reply->user_id === auth()->id());
                            $lastTimestamp = $reply->created_at;
                        @endphp
                        @if($showTimestamp)
                            <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $reply->created_at->toIso8601String() }}">
                                {{ $reply->created_at->greaterThan(now()->subDay()) ? $reply->created_at->format('g:i A') : $reply->created_at->format('M j, Y') }}
                            </div>
                        @endif
                        <div class="js-chat-row flex {{ $fromSupport ? 'justify-end' : 'justify-start' }}" data-created-at="{{ $reply->created_at->toIso8601String() }}" data-reply-id="{{ $reply->id }}" data-can-manage="{{ $canManageReply }}" data-is-internal="{{ $isInternal ? '1' : '0' }}">
                            @php $avatarLogo = $departmentLogo(data_get($reply, 'user.department'), $fromSupport); @endphp
                            <div class="flex w-full max-w-3xl items-start gap-2 {{ $fromSupport ? 'justify-end' : '' }}">
                                <div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white {{ $fromSupport ? 'order-2' : '' }}">
                                    <img src="{{ $avatarLogo }}" alt="{{ $fromSupport ? 'Support' : 'Client' }} company logo" class="avatar-logo">
                                </div>
                                <div class="js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm {{ $fromSupport ? 'order-1 border-ione-blue-200 bg-ione-blue-50' : 'border-slate-200 bg-white' }} {{ $isDeleted ? 'chat-bubble-deleted' : '' }}" data-message="{{ e($reply->message) }}" data-deleted="{{ $reply->deleted_at ? '1' : '0' }}" data-edited="{{ $reply->edited_at ? '1' : '0' }}">
                                    <div class="js-state-row mb-1 flex items-center gap-2 {{ $showEditedBadge || $isDeleted ? '' : 'hidden' }}">
                                        <span class="js-edited-badge chat-meta-badge {{ $showEditedBadge ? '' : 'hidden' }}">Edited</span>
                                        <span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted {{ $isDeleted ? '' : 'hidden' }}">Deleted</span>
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
                                        <div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">
                                            @foreach($reply->attachments as $attachment)
                                                @if(str_starts_with((string) $attachment->mime_type, 'image/'))
                                                    <a href="{{ $attachment->download_url }}"
                                                       class="js-attachment-link block w-[240px] max-w-full overflow-hidden rounded-xl border border-slate-200 bg-white p-2 text-sm hover:bg-slate-50"
                                                       data-file-url="{{ $attachment->preview_url }}"
                                                       data-file-name="{{ $attachment->original_filename }}"
                                                       data-file-mime="{{ $attachment->mime_type }}">
                                                        <img src="{{ $attachment->preview_url }}" alt="{{ $attachment->original_filename }}" class="h-36 w-full rounded-lg object-cover">
                                                        <span class="mt-2 block truncate text-xs text-slate-600">{{ $attachment->original_filename }}</span>
                                                    </a>
                                                @else
                                                    <a href="{{ $attachment->download_url }}"
                                                       class="{{ $attachment->preview_url ? 'js-attachment-link ' : '' }}flex max-w-full items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
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
                    <form id="admin-ticket-reply-form" action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" enctype="multipart/form-data" class="space-y-3" data-ticket-id="{{ (int) $ticket->id }}" data-client-logo="{{ $clientCompanyLogo }}" data-support-logo="{{ $supportCompanyLogo }}" data-replies-url="{{ route('admin.tickets.replies.feed', $ticket) }}" data-seen-url="{{ route('admin.notifications.seen', $ticket) }}" data-update-url-template="{{ route('admin.tickets.replies.update', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}" data-delete-url-template="{{ route('admin.tickets.replies.delete', ['ticket' => $ticket, 'reply' => '__REPLY__']) }}">
                        @csrf
                        <p id="admin-reply-error" class="hidden text-xs font-medium text-rose-600"></p>
                        <input type="hidden" id="admin_reply_to_id" name="reply_to_id" value="">
                        <div id="admin-reply-target-banner" class="hidden items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 shadow-sm">
                            <span id="admin-reply-target-text" class="truncate pr-3 font-medium"></span>
                            <button type="button" id="admin-clear-reply-target" class="rounded-md px-2 py-0.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">Cancel</button>
                        </div>
                        <div id="admin-edit-target-banner" class="hidden items-center justify-between rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 shadow-sm">
                            <span id="admin-edit-target-text" class="truncate pr-3 font-medium"></span>
                            <button type="button" id="admin-cancel-edit-target" class="rounded-md px-2 py-0.5 text-amber-700 transition hover:bg-amber-100 hover:text-amber-900">Cancel edit</button>
                        </div>
                        <div class="px-0 py-0">
                            <textarea name="message" id="message" rows="1" class="block max-h-48 min-h-[44px] w-full resize-none overflow-hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20" placeholder="Write your reply..."></textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <label for="attachments" class="inline-flex cursor-pointer items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-100">Attach</label>
                                <input type="file" name="attachments[]" id="attachments" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.xls,.xlsx">
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
                        <p class="text-xs text-slate-500">Add a message or at least one attachment. Max 10MB per file.</p>
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
                                {{ $ticket->assignedUser ? $ticket->assignedUser->publicDisplayName() : 'Unassigned' }}
                            </dd>
                        </div>
                        @if(in_array($ticket->status, ['resolved', 'closed'], true))
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Resolved/Reviewed By</dt>
                                <dd class="text-sm text-gray-900">{{ $ticket->assignedUser ? $ticket->assignedUser->publicDisplayName() : 'Unassigned' }}</dd>
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
                    @if(in_array($ticket->status, ['resolved', 'closed'], true))
                        @if($canRevertTicket)
                            <button type="button" id="open-revert-modal-btn" class="btn-secondary w-full">Revert to In Progress</button>
                        @else
                            <button type="button" class="btn-secondary w-full opacity-60 cursor-not-allowed" disabled>Revert expired</button>
                            <p class="text-xs text-slate-500">
                                This ticket can no longer be reverted because it has been closed for more than {{ $closedRevertWindowDays }} days.
                            </p>
                        @endif
                    @endif

                    <!-- Assign Ticket -->
                    <form action="{{ route('admin.tickets.assign', $ticket) }}" method="POST">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="assigned_to" class="form-label">Assign To</label>
                            <select name="assigned_to" id="assigned_to" class="form-input">
                                <option value="">Select Support User</option>
                                @foreach($assignees as $assignee)
                                    <option value="{{ $assignee->id }}"
                                            {{ $ticket->assigned_to == $assignee->id ? 'selected' : '' }}>
                                        {{ $assignee->publicDisplayName() }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Assignment</button>
                        </div>
                    </form>

                    <!-- Update Status -->
                    <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        @php $selectedStatus = old('status', $ticket->status); @endphp
                        @php $isClosedRevertLocked = $ticket->status === 'closed' && ! $canRevertTicket; @endphp
                        <div>
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-input">
                                <option value="open" {{ $selectedStatus === 'open' ? 'selected' : '' }} {{ $isClosedRevertLocked ? 'disabled' : '' }}>Open</option>
                                <option value="in_progress" {{ $selectedStatus === 'in_progress' ? 'selected' : '' }} {{ $isClosedRevertLocked ? 'disabled' : '' }}>In Progress</option>
                                <option value="pending" {{ $selectedStatus === 'pending' ? 'selected' : '' }} {{ $isClosedRevertLocked ? 'disabled' : '' }}>Pending</option>
                                <option value="resolved" {{ $selectedStatus === 'resolved' ? 'selected' : '' }} {{ $isClosedRevertLocked ? 'disabled' : '' }}>Resolved</option>
                                <option value="closed" {{ $selectedStatus === 'closed' ? 'selected' : '' }} {{ $requiresDelayedClose && !$canCloseNow ? 'disabled' : '' }}>
                                    Closed{{ $requiresDelayedClose && !$canCloseNow ? ' (after 24h)' : '' }}
                                </option>
                            </select>
                            <div id="status-close-reason-wrap" class="mt-2 hidden">
                                <label for="status_close_reason" class="form-label">Close Reason <span class="text-rose-500">*</span></label>
                                <textarea
                                    name="close_reason"
                                    id="status_close_reason"
                                    rows="3"
                                    class="form-input @error('close_reason') border-rose-300 focus:border-rose-400 focus:ring-rose-200 @enderror"
                                    placeholder="Provide a reason for closing this ticket..."
                                >{{ old('close_reason') }}</textarea>
                                @error('close_reason')
                                    <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                            @if($requiresDelayedClose && !$canCloseNow)
                                <p class="mt-2 text-xs text-amber-700">
                                    Close is available on {{ $closeAvailableAt ? $closeAvailableAt->format('M j, Y \a\t g:i A') : 'the 24-hour window after resolution' }}.
                                </p>
                            @endif
                            @if($isClosedRevertLocked)
                                <p class="mt-2 text-xs text-rose-700">
                                    Closed tickets cannot be reverted after {{ $closedRevertWindowDays }} days.
                                </p>
                            @endif
                            <button type="submit" class="mt-2 btn-secondary w-full">Update Status</button>
                        </div>
                    </form>

                    @if($showDelayedCloseAction)
                        <form action="{{ route('admin.tickets.status', $ticket) }}" method="POST">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                            <input type="hidden" name="status" value="closed">
                            <div>
                                <label for="timed_close_reason" class="form-label">Close Ticket (24h Rule)</label>
                                <textarea
                                    id="timed_close_reason"
                                    name="close_reason"
                                    rows="3"
                                    class="form-input"
                                    placeholder="Provide a reason for closing this ticket..."
                                    required
                                >{{ old('status') === 'closed' ? old('close_reason') : '' }}</textarea>
                                @if($canCloseNow)
                                    <button type="submit" class="mt-2 btn-danger w-full">Close Ticket</button>
                                @else
                                    <button type="submit" class="mt-2 btn-danger w-full opacity-60 cursor-not-allowed" disabled>Close Ticket</button>
                                    <p class="mt-2 text-xs text-slate-500">
                                        You can close this ticket on {{ $closeAvailableAt ? $closeAvailableAt->format('M j, Y \a\t g:i A') : 'the next allowed schedule' }}.
                                    </p>
                                @endif
                            </div>
                        </form>
                    @endif

                    <!-- Update Priority -->
                    <form action="{{ route('admin.tickets.priority', $ticket) }}" method="POST">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
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
                    <form action="{{ route('admin.tickets.due-date', $ticket) }}" method="POST" data-submit-feedback>
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                        <div>
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="datetime-local" name="due_date" id="due_date"
                                   value="{{ $ticket->due_date ? $ticket->due_date->format('Y-m-d\TH:i') : '' }}"
                                   class="form-input">
                            <button type="submit" class="mt-2 btn-secondary w-full" data-loading-text="Saving...">Set Due Date</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="revert-ticket-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-900/35 backdrop-blur-[1px]" data-modal-overlay="revert"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-md rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="text-base font-semibold text-slate-900">Confirm Revert</h3>
            </div>
            <form id="revert-ticket-form" action="{{ route('admin.tickets.status', $ticket) }}" method="POST" class="space-y-4 px-5 py-4">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
                <input type="hidden" name="status" value="in_progress">
                <p class="text-sm text-slate-600">This will move ticket <strong>#{{ $ticket->ticket_number }}</strong> back to <strong>In Progress</strong>.</p>
                <label for="revert_confirm" class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                    <input id="revert_confirm" type="checkbox" class="ticket-checkbox mt-0.5" required>
                    <span>I confirm that this ticket should be reverted.</span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" data-modal-close="revert">Cancel</button>
                    <button id="revert_submit" type="submit" class="btn-primary disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100" disabled>Confirm Revert</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
