import { bootPage } from './shared/boot-page';
import { createReplyComposer } from './shared/reply-composer';
import { createTicketSeenSync } from './shared/ticket-seen-sync';
import {
    createThreadTimeSeparator,
    parseIsoMs,
    resolveLatestThreadActivityIso,
    shouldInsertTimeSeparator,
} from './shared/ticket-thread-time';

const initClientTicketShowPage = () => {
    const pageRoot = document.querySelector('[data-client-ticket-show-page]');
    if (!pageRoot) return;

    if (!window.ModalKit) return;

    window.ModalKit.bindAttachmentPreview({
        modal: '#attachment-modal',
        title: '#attachment-modal-title',
        image: '#attachment-modal-image',
        frame: '#attachment-modal-frame',
        closeButton: '#attachment-modal-close',
        triggerSelector: '.js-attachment-link',
    });

    const resolveModalController = window.ModalKit.bindById('resolve-ticket-modal', {
        openButtons: ['#open-resolve-ticket-modal'],
        closeButtons: ['#resolve-ticket-cancel'],
    });

    const resolveConfirmCheckbox = document.getElementById('resolve_confirm_checkbox');
    const resolveRatingSelect = document.getElementById('resolve_rating');
    const resolveCommentField = document.getElementById('resolve_comment');
    const resolveConfirmSubmit = document.getElementById('resolve-confirm-submit');
    if (resolveConfirmCheckbox && resolveConfirmSubmit) {
        const syncResolveConfirmState = function () {
            const hasRating = !resolveRatingSelect || resolveRatingSelect.value !== '';
            const hasComment = !resolveCommentField || resolveCommentField.value.trim() !== '';
            resolveConfirmSubmit.disabled = !resolveConfirmCheckbox.checked || !hasRating || !hasComment;
        };

        resolveConfirmCheckbox.addEventListener('change', syncResolveConfirmState);
        if (resolveRatingSelect) {
            resolveRatingSelect.addEventListener('change', syncResolveConfirmState);
        }
        if (resolveCommentField) {
            resolveCommentField.addEventListener('input', syncResolveConfirmState);
        }
        syncResolveConfirmState();
    }

    if (pageRoot.dataset.resolveModalOpen === 'true') {
        if (resolveModalController) {
            resolveModalController.open();
        }
    }


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
    const editTargetBanner = document.getElementById('edit-target-banner');
    const editTargetText = document.getElementById('edit-target-text');
    const cancelEditTargetButton = document.getElementById('cancel-edit-target');
    const deleteReplyModal = document.getElementById('delete-reply-modal');
    const deleteReplyConfirm = document.getElementById('delete-reply-confirm');
    const deleteReplySubmit = document.getElementById('delete-reply-submit');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const clientLogo = replyForm ? replyForm.dataset.clientLogo : '';
    const supportLogo = replyForm ? replyForm.dataset.supportLogo : '';
    const repliesUrl = (replyForm ? replyForm.dataset.repliesUrl : '') || (thread ? thread.dataset.repliesUrl : '');
    const seenUrl = (replyForm ? replyForm.dataset.seenUrl : '') || (thread ? thread.dataset.seenUrl : '');
    const ticketId = Number((replyForm ? replyForm.dataset.ticketId : '') || (thread ? thread.dataset.ticketId : '') || 0);
    const updateUrlTemplate = replyForm ? replyForm.dataset.updateUrlTemplate : '';
    const deleteUrlTemplate = replyForm ? replyForm.dataset.deleteUrlTemplate : '';
    const TIMESTAMP_BREAK_MINUTES = 15;
    const EDIT_DELETE_WINDOW_MS = 3 * 60 * 60 * 1000;
    const knownReplyIds = new Set();
    let isPollingReplies = false;
    let editingReplyId = '';
    let pendingDeleteRow = null;

    const isEditingReply = function () {
        return editingReplyId !== '';
    };

    if (thread) {
        thread.querySelectorAll('.js-chat-row[data-reply-id]').forEach(function (row) {
            if (row.dataset.replyId) knownReplyIds.add(String(row.dataset.replyId));
        });
    }

    const autoResize = function () {
        if (!messageInput) return;
        const maxHeight = 192;
        messageInput.style.height = 'auto';
        const nextHeight = Math.min(messageInput.scrollHeight, maxHeight);
        messageInput.style.height = nextHeight + 'px';
        messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };

    const setSubmitButtonLabel = function (isSending) {
        if (!sendReplyButton) return;
        if (isSending) {
            sendReplyButton.textContent = isEditingReply() ? 'Saving...' : 'Sending...';
            return;
        }
        sendReplyButton.textContent = isEditingReply() ? 'Save' : 'Send';
    };

    const setAttachmentsEnabled = function (enabled) {
        if (!attachmentsInput) return;
        attachmentsInput.disabled = !enabled;
    };

    const updateAttachmentCount = function () {
        if (!attachmentsInput || !attachmentCount) return;
        if (attachmentsInput.disabled) {
            attachmentCount.textContent = 'Attachments disabled while editing';
            return;
        }
        const fileCount = attachmentsInput.files ? attachmentsInput.files.length : 0;
        attachmentCount.textContent = fileCount === 0
            ? 'No files selected'
            : fileCount + (fileCount === 1 ? ' file selected' : ' files selected');
    };

    setSubmitButtonLabel(false);

    const latestThreadActivityIso = function () {
        return resolveLatestThreadActivityIso(thread);
    };

    const { queueSeenSync } = createTicketSeenSync({
        seenUrl,
        csrfToken,
        ticketId,
        getLatestActivityIso: latestThreadActivityIso,
        parseIsoMs,
    });

    const createAttachmentLink = function (attachment) {
        const link = document.createElement('a');
        link.href = attachment.download_url;
        const canPreview = Boolean(attachment.can_preview && attachment.preview_url);
        if (canPreview) {
            link.dataset.fileUrl = attachment.preview_url;
            link.dataset.fileName = attachment.original_filename;
            link.dataset.fileMime = attachment.mime_type;
        }

        if (attachment.is_image) {
            link.className = (canPreview ? 'js-attachment-link ' : '') + 'block w-[240px] max-w-full overflow-hidden rounded-xl border border-ione-blue-200 bg-white p-2 text-sm transition hover:bg-slate-50';

            const image = document.createElement('img');
            image.src = canPreview ? attachment.preview_url : attachment.download_url;
            image.alt = attachment.original_filename || 'Attachment image';
            image.className = 'h-36 w-full rounded-lg object-cover';

            const caption = document.createElement('span');
            caption.className = 'mt-2 block truncate text-xs text-slate-600';
            caption.textContent = attachment.original_filename;

            link.appendChild(image);
            link.appendChild(caption);
            return link;
        }

        link.className = (canPreview ? 'js-attachment-link ' : '') + 'flex max-w-full items-center rounded-xl border border-ione-blue-200 bg-white px-3 py-2 text-sm transition hover:bg-slate-50';

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

    const syncDeleteReplySubmitState = function () {
        if (!deleteReplySubmit || !deleteReplyConfirm) return;
        deleteReplySubmit.disabled = !deleteReplyConfirm.checked;
    };

    const deleteReply = async function (row) {
        if (!row) return;

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
    };

    const setEditingReplyId = function (value) {
        editingReplyId = value;
    };

    const composer = createReplyComposer({
        setEditingReplyId,
        replyToInput,
        replyTargetBanner,
        replyTargetText,
        editTargetBanner,
        editTargetText,
        messageInput,
        attachmentInput: attachmentsInput,
        setAttachmentsEnabled,
        updateAttachmentCount,
        refreshSubmitButtonLabel: () => setSubmitButtonLabel(false),
        autoResize,
    });
    const clearReplyTarget = composer.clearReplyTarget;
    const clearEditingTarget = composer.clearEditingTarget;
    const setReplyTarget = composer.setReplyTarget;
    const setEditingTarget = composer.setEditingTarget;

    const isWithinEditDeleteWindow = function (isoDate) {
        const createdAt = new Date(isoDate);
        if (Number.isNaN(createdAt.getTime())) {
            return false;
        }

        return (Date.now() - createdAt.getTime()) <= EDIT_DELETE_WINDOW_MS;
    };

    const renderTimeSeparators = function () {
        if (!thread) return;

        thread.querySelectorAll('.js-time-separator').forEach(function (el) {
            el.remove();
        });

        const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
        let lastCreatedAtIso = '';

        rows.forEach(function (row) {
            const createdAt = row.dataset.createdAt;
            if (!createdAt) return;

            if (
                !lastCreatedAtIso
                || shouldInsertTimeSeparator(lastCreatedAtIso, createdAt, TIMESTAMP_BREAK_MINUTES)
            ) {
                const separator = createThreadTimeSeparator(createdAt);
                if (separator) {
                    row.parentNode.insertBefore(separator, row);
                }
            }

            lastCreatedAtIso = createdAt;
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

    const appendReplyToThread = function (reply) {
        if (!thread || !reply) return;

        const fromSupport = Boolean(reply.from_support);
        const canManage = Boolean(reply.can_manage);
        const createdAt = reply.created_at_iso || new Date().toISOString();
        const replyId = String(reply.id || '');
        if (replyId) {
            knownReplyIds.add(replyId);
        }

        const wrap = document.createElement('div');
        wrap.className = 'js-chat-row flex ' + (fromSupport ? 'justify-start' : 'justify-end');
        wrap.dataset.createdAt = createdAt;
        wrap.dataset.replyId = replyId;
        wrap.dataset.isSupport = fromSupport ? '1' : '0';
        wrap.dataset.canManage = canManage ? '1' : '0';

        const rowContent = document.createElement('div');
        rowContent.className = 'flex w-full max-w-3xl items-start gap-2 ' + (fromSupport ? '' : 'justify-end');

        const avatar = document.createElement('div');
        avatar.className = 'mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white ' + (fromSupport ? '' : 'order-2');
        avatar.innerHTML = '<img src="' + (reply.avatar_logo || (fromSupport ? supportLogo : clientLogo)) + '" alt="' + (fromSupport ? 'Support' : 'Client') + ' company logo" class="avatar-logo">';

        const bubble = document.createElement('div');
        bubble.className = 'js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm ' + (fromSupport ? 'border-slate-200 bg-white' : 'order-1 border-ione-blue-200 bg-ione-blue-50') + (reply.deleted ? ' chat-bubble-deleted' : '');
        bubble.dataset.message = reply.message || '';
        bubble.dataset.deleted = reply.deleted ? '1' : '0';
        bubble.dataset.edited = reply.edited ? '1' : '0';
        const showEditedBadge = !!reply.edited && !reply.deleted;

        const meta = document.createElement('div');
        meta.className = 'js-state-row mb-1 flex items-center gap-2 ' + ((showEditedBadge || reply.deleted) ? '' : 'hidden');
        meta.innerHTML = '<span class="js-edited-badge chat-meta-badge ' + (showEditedBadge ? '' : 'hidden') + '">Edited</span><span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted ' + (reply.deleted ? '' : 'hidden') + '">Deleted</span>';

        if (!fromSupport && canManage) {
            meta.appendChild(buildOwnMessageControls(!!reply.deleted, createdAt));
        } else {
            const controls = document.createElement('div');
            controls.className = 'js-message-actions absolute -right-10 top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100';
            controls.innerHTML = '<button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="Reply to this message"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg></button>';
            meta.appendChild(controls);
        }

        const reference = createReferenceBlock(reply.reply_to_message, fromSupport ? 'Support replied' : 'You replied');

        const text = document.createElement('p');
        text.className = 'js-message-text whitespace-pre-wrap text-sm leading-6 ' + (reply.deleted ? 'italic text-slate-500' : 'text-slate-800');
        text.textContent = reply.message || '';

        bubble.appendChild(meta);
        bubble.appendChild(reference);
        bubble.appendChild(text);

        if (!reply.deleted && reply.attachments && reply.attachments.length > 0) {
            const attachmentGrid = document.createElement('div');
            attachmentGrid.className = 'js-attachments-wrap mt-4 flex flex-wrap gap-2';
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
        queueSeenSync(createdAt);
    };

    const applyReplyState = function (row, reply) {
        const bubble = row.querySelector('.js-chat-bubble');
        if (!bubble) return;

        bubble.dataset.message = reply.message || '';
        bubble.dataset.deleted = reply.deleted ? '1' : '0';
        bubble.dataset.edited = reply.edited ? '1' : '0';
        bubble.classList.toggle('chat-bubble-deleted', !!reply.deleted);
        const showEditedBadge = !!reply.edited && !reply.deleted;

        const messageText = row.querySelector('.js-message-text');
        if (messageText) {
            messageText.textContent = reply.message || '';
            messageText.classList.toggle('italic', !!reply.deleted);
            messageText.classList.toggle('text-slate-500', !!reply.deleted);
            messageText.classList.toggle('text-slate-800', !reply.deleted);
        }

        const editedBadge = row.querySelector('.js-edited-badge');
        if (editedBadge) editedBadge.classList.toggle('hidden', !showEditedBadge);

        const deletedBadge = row.querySelector('.js-deleted-badge');
        if (deletedBadge) deletedBadge.classList.toggle('hidden', !reply.deleted);

        const stateRow = row.querySelector('.js-state-row');
        if (stateRow) stateRow.classList.toggle('hidden', !(showEditedBadge || reply.deleted));

        const reference = row.querySelector('.js-reply-reference');
        if (reference) {
            const referenceText = reference.querySelector('.js-reply-reference-text');
            if (referenceText) {
                referenceText.textContent = reply.reply_to_message || reply.reply_to_excerpt || '';
            }
            reference.classList.toggle('hidden', !(reply.reply_to_message || reply.reply_to_excerpt));
        }

        const attachmentsWrap = row.querySelector('.js-attachments-wrap');
        if (attachmentsWrap && reply.deleted) attachmentsWrap.remove();

        if (reply.deleted) {
            const menu = row.querySelector('.js-more-menu');
            if (menu) menu.innerHTML = '';

            if (editingReplyId && String(row.dataset.replyId) === String(editingReplyId)) {
                clearEditingTarget({ resetInput: true });
            }
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

    const deleteReplyModalController = window.ModalKit && deleteReplyModal
        ? window.ModalKit.bind(deleteReplyModal, {
            onClose: function () {
                pendingDeleteRow = null;
                if (deleteReplyConfirm) {
                    deleteReplyConfirm.checked = false;
                }
                syncDeleteReplySubmitState();
            },
        })
        : null;

    if (deleteReplyConfirm) {
        deleteReplyConfirm.addEventListener('change', syncDeleteReplySubmitState);
    }

    if (deleteReplySubmit) {
        deleteReplySubmit.addEventListener('click', async function () {
            if (!deleteReplyConfirm || !deleteReplyConfirm.checked || !pendingDeleteRow) {
                return;
            }

            const rowToDelete = pendingDeleteRow;
            if (deleteReplyModalController) {
                deleteReplyModalController.close();
            }
            await deleteReply(rowToDelete);
        });
    }
    syncDeleteReplySubmitState();

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
            const messageBody = messageInput ? messageInput.value.trim() : '';
            const hasMessage = messageBody.length > 0;
            const hasAttachments = !!(attachmentsInput && attachmentsInput.files && attachmentsInput.files.length > 0);
            if (isEditingReply()) {
                if (!hasMessage) {
                    return;
                }
            } else if (!hasMessage && !hasAttachments) {
                return;
            }

            if (replyError) {
                replyError.classList.add('hidden');
                replyError.textContent = '';
            }

            sendReplyButton.disabled = true;
            setSubmitButtonLabel(true);

            try {
                if (isEditingReply()) {
                    const row = thread ? thread.querySelector('.js-chat-row[data-reply-id="' + editingReplyId + '"]') : null;
                    if (!row) {
                        throw new Error('Message not found for editing.');
                    }

                    const updateResponse = await fetch(replyEndpoint(updateUrlTemplate, editingReplyId), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ message: messageBody }),
                    });

                    const updateData = await updateResponse.json().catch(function () { return {}; });
                    if (!updateResponse.ok) {
                        throw new Error(updateData.message || 'Unable to edit message right now.');
                    }

                    if (updateData.reply) {
                        applyReplyState(row, updateData.reply);
                    }
                    clearEditingTarget({ resetInput: true });
                    if (messageInput) messageInput.focus();
                    return;
                }

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
                    appendReplyToThread(data.reply);
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
                    replyError.textContent = error && error.message ? error.message : 'Network error. Please try again.';
                    replyError.classList.remove('hidden');
                }
            } finally {
                sendReplyButton.disabled = false;
                setSubmitButtonLabel(false);
            }
        });
    }

    if (clearReplyTargetButton) {
        clearReplyTargetButton.addEventListener('click', clearReplyTarget);
    }

    if (cancelEditTargetButton) {
        cancelEditTargetButton.addEventListener('click', function () {
            clearEditingTarget({ resetInput: true });
            if (messageInput) messageInput.focus();
        });
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
            closeMenus();
            setEditingTarget(row);
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

            pendingDeleteRow = row;
            if (deleteReplyConfirm) {
                deleteReplyConfirm.checked = false;
            }
            syncDeleteReplySubmitState();
            if (deleteReplyModalController) {
                deleteReplyModalController.open();
            } else {
                await deleteReply(row);
            }
        }
    });
    }

    const pollReplies = async function () {
        if (!thread || !repliesUrl || isPollingReplies) return;
        isPollingReplies = true;

        try {
            const response = await fetch(repliesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) return;
            const data = await response.json();
            const replies = Array.isArray(data && data.replies) ? data.replies : [];
            replies.forEach(function (reply) {
                const replyId = String(reply.id || '');
                if (!replyId) return;

                const existingRow = thread.querySelector('.js-chat-row[data-reply-id="' + replyId + '"]');
                if (existingRow) {
                    applyReplyState(existingRow, reply);
                    return;
                }

                appendReplyToThread(reply);
                incrementMessageCount();
            });
            queueSeenSync();
        } catch (error) {
        } finally {
            isPollingReplies = false;
        }
    };

    if (repliesUrl) {
        window.setInterval(pollReplies, 5000);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            queueSeenSync();
        }
    });

    window.addEventListener('focus', function () {
        queueSeenSync();
    });

    queueSeenSync();

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-more-btn') && !event.target.closest('.js-more-menu')) {
            closeMenus();
        }
    });

    if (attachmentsInput && attachmentCount) {
        attachmentsInput.addEventListener('change', updateAttachmentCount);
    }
};

bootPage(initClientTicketShowPage);

