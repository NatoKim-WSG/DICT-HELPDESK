export const EDIT_DELETE_WINDOW_MS = 3 * 60 * 60 * 1000;
export const REPLY_POLL_INTERVAL_MS = 5000;

export const formatAttachmentCountLabel = function ({ disabled = false, fileCount = 0 }) {
    if (disabled) {
        return 'Attachments disabled while editing';
    }

    return fileCount === 0
        ? 'No files selected'
        : fileCount + (fileCount === 1 ? ' file selected' : ' files selected');
};

export const canSubmitReply = function ({ isEditing = false, message = '', attachmentCount = 0 }) {
    const hasMessage = String(message).trim().length > 0;

    if (isEditing) {
        return hasMessage;
    }

    return hasMessage || attachmentCount > 0;
};

export const isWithinReplyEditWindow = function (
    isoDate,
    nowMs = Date.now(),
    windowMs = EDIT_DELETE_WINDOW_MS
) {
    const createdAt = new Date(isoDate);
    if (Number.isNaN(createdAt.getTime())) {
        return false;
    }

    return (nowMs - createdAt.getTime()) <= windowMs;
};

export const nextMessageCountLabel = function (currentLabel) {
    const value = parseInt(String(currentLabel || '').replace(/\D/g, ''), 10);
    if (Number.isNaN(value)) {
        return null;
    }

    const next = value + 1;

    return next + (next === 1 ? ' message' : ' messages');
};

export const buildReplyEndpoint = function (template, replyId) {
    return template ? template.replace('__REPLY__', String(replyId)) : '';
};

export const buildReplyFeedUrl = function (baseUrl, updatedAfter = '') {
    if (!baseUrl) return '';

    const url = new URL(baseUrl, window.location.origin);
    if (updatedAfter) {
        url.searchParams.set('updated_after', updatedAfter);
    }

    return url.toString();
};

export const replyReferenceText = function (reply) {
    return String(reply && reply.reply_to_text ? reply.reply_to_text : '');
};

export const incrementMessageCount = function (messageCountNode) {
    if (!messageCountNode) return;

    const nextLabel = nextMessageCountLabel(messageCountNode.textContent || '');
    if (nextLabel) {
        messageCountNode.textContent = nextLabel;
    }
};

export const syncReplyReference = function (reference, reply) {
    if (!reference) return;

    const text = replyReferenceText(reply);
    const referenceTextNode = reference.querySelector('.js-reply-reference-text');
    if (referenceTextNode) {
        referenceTextNode.textContent = text;
    }
    reference.classList.toggle('hidden', text === '');
};

export const applyReplyStateToRow = function ({ row, reply, onDeleted }) {
    const bubble = row ? row.querySelector('.js-chat-bubble') : null;
    if (!bubble || !reply) return;

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

    syncReplyReference(row.querySelector('.js-reply-reference'), reply);

    const attachmentsWrap = row.querySelector('.js-attachments-wrap');
    if (attachmentsWrap && reply.deleted) {
        attachmentsWrap.remove();
    }

    if (reply.deleted && typeof onDeleted === 'function') {
        onDeleted(row, reply);
    }
};

export const syncThreadReplies = function ({
    thread,
    replies,
    appendReply,
    applyReplyState,
    onReplyAdded,
}) {
    if (!thread || !Array.isArray(replies)) return;

    replies.forEach(function (reply) {
        const replyId = String((reply && reply.id) || '');
        if (!replyId) return;

        const existingRow = thread.querySelector('.js-chat-row[data-reply-id="' + replyId + '"]');
        if (existingRow) {
            applyReplyState(existingRow, reply);
            return;
        }

        appendReply(reply);
        if (typeof onReplyAdded === 'function') {
            onReplyAdded(reply);
        }
    });
};
