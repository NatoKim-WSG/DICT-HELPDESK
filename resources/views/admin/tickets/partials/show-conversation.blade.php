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
                $showEditedBadge = (bool) $reply->edited_at && ! $isDeleted;
                $showTimestamp = $reply->created_at->diffInMinutes($lastTimestamp) >= 15;
                $canManageReply = (int) ($reply->user_id === auth()->id());
                $lastTimestamp = $reply->created_at;
                $avatarLogo = $departmentLogo(data_get($reply, 'user.department'), $fromSupport);
            @endphp
            @if($showTimestamp)
                <div class="js-time-separator py-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-400" data-time="{{ $reply->created_at->toIso8601String() }}">
                    {{ $reply->created_at->greaterThan(now()->subDay()) ? $reply->created_at->format('g:i A') : $reply->created_at->format('M j, Y') }}
                </div>
            @endif
            <div class="js-chat-row flex {{ $fromSupport ? 'justify-end' : 'justify-start' }}" data-created-at="{{ $reply->created_at->toIso8601String() }}" data-reply-id="{{ $reply->id }}" data-can-manage="{{ $canManageReply }}" data-is-internal="{{ $isInternal ? '1' : '0' }}">
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

                        @if($reply->attachments && $reply->attachments->count() > 0 && ! $reply->deleted_at)
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
                        <input type="checkbox" name="is_internal" id="is_internal" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
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
