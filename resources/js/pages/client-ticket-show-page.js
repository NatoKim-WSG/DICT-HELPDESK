import { bootPage } from './shared/boot-page';
import { createReplyComposer } from './shared/reply-composer';
import { createTicketSeenSync } from './shared/ticket-seen-sync';
import {
    buildReplyFeedUrl,
    buildReplyEndpoint,
    canSubmitReply,
    EDIT_DELETE_WINDOW_MS,
    formatAttachmentCountLabel,
    isWithinReplyEditWindow,
    REPLY_POLL_INTERVAL_MS,
    syncThreadReplies,
} from './shared/ticket-thread-helpers';
import { parseIsoMs, resolveLatestThreadActivityIso } from './shared/ticket-thread-time';
import { createClientTicketThreadView } from './shared/client-ticket-thread-view';
import { createReplyPolling } from './shared/ticket-thread-polling';

const initClientTicketShowPage = () => {
    const pageRoot = document.querySelector('[data-client-ticket-show-page]');
    if (!pageRoot || !window.ModalKit) return;

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
        const syncResolveConfirmState = () => {
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

    if (pageRoot.dataset.resolveModalOpen === 'true' && resolveModalController) {
        resolveModalController.open();
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
    let repliesCursor = (thread ? thread.dataset.repliesCursor : '') || (replyForm ? replyForm.dataset.repliesCursor : '') || '';
    let editingReplyId = '';
    let pendingDeleteRow = null;

    const isEditingReply = () => editingReplyId !== '';
    const isWithinEditDeleteWindow = (isoDate) => isWithinReplyEditWindow(isoDate, Date.now(), EDIT_DELETE_WINDOW_MS);

    const autoResize = () => {
        if (!messageInput) return;
        const maxHeight = 192;
        messageInput.style.height = 'auto';
        const nextHeight = Math.min(messageInput.scrollHeight, maxHeight);
        messageInput.style.height = `${nextHeight}px`;
        messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };

    const setSubmitButtonLabel = (isSending) => {
        if (!sendReplyButton) return;
        if (isSending) {
            sendReplyButton.textContent = isEditingReply() ? 'Saving...' : 'Sending...';
            return;
        }
        sendReplyButton.textContent = isEditingReply() ? 'Save' : 'Send';
    };

    const setAttachmentsEnabled = (enabled) => {
        if (!attachmentsInput) return;
        attachmentsInput.disabled = !enabled;
    };

    const updateAttachmentCount = () => {
        if (!attachmentsInput || !attachmentCount) return;
        const fileCount = attachmentsInput.files ? attachmentsInput.files.length : 0;
        attachmentCount.textContent = formatAttachmentCountLabel({
            disabled: attachmentsInput.disabled,
            fileCount,
        });
    };

    setSubmitButtonLabel(false);

    const latestThreadActivityIso = () => resolveLatestThreadActivityIso(thread);
    const { queueSeenSync } = createTicketSeenSync({
        seenUrl,
        csrfToken,
        ticketId,
        getLatestActivityIso: latestThreadActivityIso,
        parseIsoMs,
    });

    const closeMenus = () => {
        if (!thread) return;

        thread.querySelectorAll('.js-more-menu').forEach((menu) => {
            menu.classList.add('hidden');
        });
    };

    const syncDeleteReplySubmitState = () => {
        if (!deleteReplySubmit || !deleteReplyConfirm) return;
        deleteReplySubmit.disabled = !deleteReplyConfirm.checked;
    };

    const deleteReply = async (row) => {
        if (!row) return;

        try {
            const response = await fetch(buildReplyEndpoint(deleteUrlTemplate, row.dataset.replyId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
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
                threadView.applyReplyState(row, data.reply);
            }
        } catch (error) {
            if (replyError) {
                replyError.textContent = error && error.message ? error.message : 'Unable to delete message right now.';
                replyError.classList.remove('hidden');
            }
        }
    };

    const composer = createReplyComposer({
        setEditingReplyId: (value) => {
            editingReplyId = value;
        },
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
    const { clearEditingTarget, clearReplyTarget, setEditingTarget, setReplyTarget } = composer;

    const threadView = createClientTicketThreadView({
        thread,
        clientLogo,
        supportLogo,
        messageCount,
        queueSeenSync,
        getEditingReplyId: () => editingReplyId,
        clearEditingTarget,
        isWithinEditDeleteWindow,
    });

    const deleteReplyModalController = deleteReplyModal
        ? window.ModalKit.bind(deleteReplyModal, {
            onClose: () => {
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
        deleteReplySubmit.addEventListener('click', async () => {
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
        threadView.renderTimeSeparators();
        thread.scrollTop = thread.scrollHeight;
    }

    if (messageInput) {
        autoResize();
        messageInput.addEventListener('input', autoResize);
        messageInput.addEventListener('keydown', (event) => {
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
        replyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            closeMenus();
            const messageBody = messageInput ? messageInput.value.trim() : '';
            const attachmentCountValue = attachmentsInput && attachmentsInput.files ? attachmentsInput.files.length : 0;
            if (!canSubmitReply({
                isEditing: isEditingReply(),
                message: messageBody,
                attachmentCount: attachmentCountValue,
            })) {
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
                    const row = thread ? thread.querySelector(`.js-chat-row[data-reply-id="${editingReplyId}"]`) : null;
                    if (!row) {
                        throw new Error('Message not found for editing.');
                    }

                    const updateResponse = await fetch(buildReplyEndpoint(updateUrlTemplate, editingReplyId), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ message: messageBody }),
                    });

                    const updateData = await updateResponse.json().catch(() => ({}));
                    if (!updateResponse.ok) {
                        throw new Error(updateData.message || 'Unable to edit message right now.');
                    }

                    if (updateData.reply) {
                        threadView.applyReplyState(row, updateData.reply);
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
                    } else if (replyError) {
                        replyError.textContent = 'Unable to send reply. Please try again.';
                        replyError.classList.remove('hidden');
                    }
                    return;
                }

                const data = await response.json();
                if (data && data.reply) {
                    threadView.appendReplyAndIncrement(data.reply);
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
        cancelEditTargetButton.addEventListener('click', () => {
            clearEditingTarget({ resetInput: true });
            if (messageInput) messageInput.focus();
        });
    }

    if (thread) {
        thread.addEventListener('click', async (event) => {
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

    const polling = createReplyPolling({
        repliesUrl: (cursor) => buildReplyFeedUrl(repliesUrl, cursor),
        getCursor: () => repliesCursor,
        setCursor: (cursor) => {
            repliesCursor = cursor;
        },
        syncReplies: (replies) => {
            syncThreadReplies({
                thread,
                replies,
                appendReply: threadView.appendReplyAndIncrement,
                applyReplyState: threadView.applyReplyState,
            });
        },
        queueSeenSync,
        intervalMs: REPLY_POLL_INTERVAL_MS,
    });

    queueSeenSync();
    polling.bind();
    polling.schedule();

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.js-more-btn') && !event.target.closest('.js-more-menu')) {
            closeMenus();
        }
    });

    if (attachmentsInput && attachmentCount) {
        attachmentsInput.addEventListener('change', updateAttachmentCount);
    }
};

bootPage(initClientTicketShowPage);
