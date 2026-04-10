export const EDIT_DELETE_WINDOW_MS = 3 * 60 * 60 * 1000;

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
