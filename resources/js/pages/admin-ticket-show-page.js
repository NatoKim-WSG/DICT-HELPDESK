import { bootPage } from './shared/boot-page';
import { createReplyComposer } from './shared/reply-composer';
import { createTicketSeenSync } from './shared/ticket-seen-sync';
import {
    buildReplyFeedUrl,
    buildReplyEndpoint,
    canSubmitReply,
    formatAttachmentCountLabel,
    REPLY_POLL_INTERVAL_MS,
    syncThreadReplies,
} from './shared/ticket-thread-helpers';
import { parseIsoMs, resolveLatestThreadActivityIso } from './shared/ticket-thread-time';
import { createAdminTicketThreadView } from './shared/admin-ticket-thread-view';
import { createReplyPolling } from './shared/ticket-thread-polling';

const initAdminTicketShowPage = () => {
    const pageRoot = document.querySelector('[data-admin-ticket-show-page]');
    if (!pageRoot) return;

    const thread = document.getElementById('admin-conversation-thread');
    const replyForm = document.getElementById('admin-ticket-reply-form');
    const messageInput = document.getElementById('message');
    const sendReplyButton = document.getElementById('admin-send-reply-btn');
    const attachmentInput = document.getElementById('attachments');
    const attachmentCount = document.getElementById('admin-attachment-count');
    const messageCountNode = document.getElementById('admin-message-count');
    const replyError = document.getElementById('admin-reply-error');
    const replyToInput = document.getElementById('admin_reply_to_id');
    const internalNoteInput = document.getElementById('is_internal');
    const replyTargetBanner = document.getElementById('admin-reply-target-banner');
    const replyTargetText = document.getElementById('admin-reply-target-text');
    const clearReplyTargetButton = document.getElementById('admin-clear-reply-target');
    const editTargetBanner = document.getElementById('admin-edit-target-banner');
    const editTargetText = document.getElementById('admin-edit-target-text');
    const cancelEditTargetButton = document.getElementById('admin-cancel-edit-target');
    const deleteReplyModal = document.getElementById('delete-reply-modal');
    const deleteReplyConfirm = document.getElementById('delete-reply-confirm');
    const deleteReplySubmit = document.getElementById('delete-reply-submit');
    const repliesUrl = (replyForm ? replyForm.dataset.repliesUrl : '') || (thread ? thread.dataset.repliesUrl : '');
    const seenUrl = (replyForm ? replyForm.dataset.seenUrl : '') || (thread ? thread.dataset.seenUrl : '');
    const ticketId = Number((replyForm ? replyForm.dataset.ticketId : '') || (thread ? thread.dataset.ticketId : '') || 0);
    const updateUrlTemplate = replyForm ? replyForm.dataset.updateUrlTemplate : '';
    const deleteUrlTemplate = replyForm ? replyForm.dataset.deleteUrlTemplate : '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const defaultClientLogo = replyForm ? (replyForm.dataset.clientLogo || '') : '';
    const supportLogo = replyForm ? (replyForm.dataset.supportLogo || '') : '';
    let repliesCursor = (thread ? thread.dataset.repliesCursor : '') || (replyForm ? replyForm.dataset.repliesCursor : '') || '';
    let editingReplyId = '';
    let pendingDeleteRow = null;

    const isEditingReply = () => editingReplyId !== '';

    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    const autoResize = () => {
        if (!messageInput) return;
        const maxHeight = 192;
        messageInput.style.height = 'auto';
        const nextHeight = Math.min(messageInput.scrollHeight, maxHeight);
        messageInput.style.height = `${nextHeight}px`;
        messageInput.style.overflowY = messageInput.scrollHeight > maxHeight ? 'auto' : 'hidden';
    };

    if (messageInput) {
        autoResize();
        messageInput.addEventListener('input', autoResize);
        messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && replyForm) {
                event.preventDefault();
                if (messageInput.value.trim().length > 0) {
                    replyForm.requestSubmit();
                }
            }
        });
    }

    const refreshSubmitButtonLabel = () => {
        if (!sendReplyButton) return;
        sendReplyButton.textContent = isEditingReply() ? 'Save' : 'Send';
    };

    const setSendState = (isSending) => {
        if (!sendReplyButton) return;
        sendReplyButton.disabled = isSending;
        if (isSending) {
            sendReplyButton.textContent = isEditingReply() ? 'Saving...' : 'Sending...';
            return;
        }
        refreshSubmitButtonLabel();
    };

    const setAttachmentsEnabled = (enabled) => {
        if (!attachmentInput) return;
        attachmentInput.disabled = !enabled;
    };

    const updateAttachmentCount = () => {
        if (!attachmentInput || !attachmentCount) return;
        const totalFiles = attachmentInput.files ? attachmentInput.files.length : 0;
        attachmentCount.textContent = formatAttachmentCountLabel({
            disabled: attachmentInput.disabled,
            fileCount: totalFiles,
        });
    };

    if (attachmentInput) {
        attachmentInput.addEventListener('change', updateAttachmentCount);
        updateAttachmentCount();
    }

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
        attachmentInput,
        setAttachmentsEnabled,
        updateAttachmentCount,
        refreshSubmitButtonLabel,
        autoResize,
        focusOnReplyTarget: true,
        onSetReplyTarget: ({ isInternalTarget }) => {
            if (isInternalTarget && internalNoteInput) {
                internalNoteInput.checked = true;
            }
        },
    });
    const { clearEditingTarget, clearReplyTarget, setEditingTarget, setReplyTarget } = composer;

    const closeMenus = () => {
        document.querySelectorAll('.js-more-menu').forEach((menu) => {
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
                throw new Error('Unable to delete message.');
            }

            const data = await response.json();
            if (!data || !data.reply) return;
            threadView.applyReplyState(row, data.reply);
        } catch (error) {
            if (!replyError) return;
            replyError.textContent = error.message || 'Unable to delete message.';
            replyError.classList.remove('hidden');
        }
    };

    const latestThreadActivityIso = () => resolveLatestThreadActivityIso(thread);
    const { queueSeenSync } = createTicketSeenSync({
        seenUrl,
        csrfToken,
        ticketId,
        getLatestActivityIso: latestThreadActivityIso,
        parseIsoMs,
    });

    const threadView = createAdminTicketThreadView({
        thread,
        supportLogo,
        defaultClientLogo,
        messageCountNode,
        queueSeenSync,
        getEditingReplyId: () => editingReplyId,
        clearEditingTarget,
    });

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
        thread.addEventListener('click', (event) => {
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
                const isInternalTarget = row.dataset.isInternal === '1';
                const messageNode = row.querySelector('.js-message-text');
                const message = messageNode ? messageNode.textContent.trim() : '';
                if (replyId) {
                    setReplyTarget(replyId, message, { isInternalTarget });
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
                setEditingTarget(row);
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

                pendingDeleteRow = row;
                if (deleteReplyConfirm) {
                    deleteReplyConfirm.checked = false;
                }
                syncDeleteReplySubmitState();
                if (deleteReplyModalController) {
                    deleteReplyModalController.open();
                } else {
                    deleteReply(row);
                }
            }
        });
    }

    if (replyForm) {
        replyForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const messageBody = messageInput ? messageInput.value.trim() : '';
            const attachmentCountValue = attachmentInput && attachmentInput.files ? attachmentInput.files.length : 0;

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

            setSendState(true);

            try {
                if (isEditingReply()) {
                    const row = thread ? thread.querySelector(`.js-chat-row[data-reply-id="${editingReplyId}"]`) : null;
                    if (!row) {
                        throw new Error('Message not found for editing.');
                    }

                    const editResponse = await fetch(buildReplyEndpoint(updateUrlTemplate, editingReplyId), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ message: messageBody }),
                        credentials: 'same-origin',
                    });

                    const editData = await editResponse.json().catch(() => ({}));
                    if (!editResponse.ok) {
                        throw new Error(editData.message || 'Unable to edit message.');
                    }

                    if (editData.reply) {
                        threadView.applyReplyState(row, editData.reply);
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
                    credentials: 'same-origin',
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    window.location.reload();
                    return;
                }

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Unable to send reply.');
                }

                if (!data || !data.reply) return;
                threadView.appendReply(data.reply);
                replyForm.reset();
                clearReplyTarget();
                if (messageInput) {
                    messageInput.value = '';
                    autoResize();
                    messageInput.focus();
                }
                updateAttachmentCount();
            } catch (error) {
                if (!replyError) return;
                replyError.textContent = error.message || 'Unable to send reply.';
                replyError.classList.remove('hidden');
            } finally {
                setSendState(false);
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
                appendReply: threadView.appendReply,
                applyReplyState: threadView.applyReplyState,
            });
        },
        queueSeenSync,
        intervalMs: REPLY_POLL_INTERVAL_MS,
    });

    queueSeenSync();
    polling.bind();
    polling.schedule();

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
    const deleteReplyModalController = window.ModalKit && deleteReplyModal
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

    document.addEventListener('click', (event) => {
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

    const statusSelect = document.getElementById('status');
    const closeReasonWrap = document.getElementById('status-close-reason-wrap');
    const closeReasonInput = document.getElementById('status_close_reason');
    const openRevertButton = document.getElementById('open-revert-modal-btn');
    const revertModal = document.getElementById('revert-ticket-modal');
    const revertForm = document.getElementById('revert-ticket-form');
    const revertConfirm = document.getElementById('revert_confirm');
    const revertSubmit = document.getElementById('revert_submit');

    if (statusSelect && closeReasonWrap && closeReasonInput) {
        const syncCloseReasonVisibility = () => {
            const isClosed = statusSelect.value === 'closed';
            closeReasonWrap.classList.toggle('hidden', !isClosed);
            closeReasonInput.required = isClosed;
            if (!isClosed) {
                closeReasonInput.value = '';
            }
        };

        statusSelect.addEventListener('change', syncCloseReasonVisibility);
        syncCloseReasonVisibility();
    }

    const revertModalController = window.ModalKit && revertModal ? window.ModalKit.bind(revertModal) : null;
    const syncRevertSubmitState = () => {
        if (!revertConfirm || !revertSubmit) return;
        revertSubmit.disabled = !revertConfirm.checked;
    };

    if (revertConfirm) {
        revertConfirm.addEventListener('change', syncRevertSubmitState);
    }

    if (openRevertButton) {
        openRevertButton.addEventListener('click', () => {
            if (revertConfirm) {
                revertConfirm.checked = false;
            }
            syncRevertSubmitState();
            if (revertModalController) {
                revertModalController.open();
            }
        });
    }

    if (revertForm) {
        revertForm.addEventListener('submit', (event) => {
            if (!revertConfirm || revertConfirm.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    syncRevertSubmitState();
};

bootPage(initAdminTicketShowPage);
