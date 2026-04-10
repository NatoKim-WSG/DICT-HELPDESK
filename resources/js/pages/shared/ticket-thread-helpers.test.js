import { describe, expect, it } from 'vitest';

import {
    canSubmitReply,
    formatAttachmentCountLabel,
    isWithinReplyEditWindow,
    nextMessageCountLabel,
} from './ticket-thread-helpers';

describe('formatAttachmentCountLabel', function () {
    it('shows the editing lock message when disabled', function () {
        expect(formatAttachmentCountLabel({ disabled: true, fileCount: 3 })).toBe('Attachments disabled while editing');
    });

    it('formats zero and plural file counts', function () {
        expect(formatAttachmentCountLabel({ fileCount: 0 })).toBe('No files selected');
        expect(formatAttachmentCountLabel({ fileCount: 2 })).toBe('2 files selected');
    });
});

describe('canSubmitReply', function () {
    it('requires message text while editing', function () {
        expect(canSubmitReply({ isEditing: true, message: '   ', attachmentCount: 2 })).toBe(false);
        expect(canSubmitReply({ isEditing: true, message: 'Updated text', attachmentCount: 0 })).toBe(true);
    });

    it('allows new replies with text or attachments', function () {
        expect(canSubmitReply({ message: 'Hello', attachmentCount: 0 })).toBe(true);
        expect(canSubmitReply({ message: '   ', attachmentCount: 1 })).toBe(true);
        expect(canSubmitReply({ message: '   ', attachmentCount: 0 })).toBe(false);
    });
});

describe('isWithinReplyEditWindow', function () {
    it('accepts timestamps inside the edit window and rejects stale or invalid ones', function () {
        const nowMs = Date.parse('2026-04-10T12:00:00.000Z');

        expect(isWithinReplyEditWindow('2026-04-10T10:30:00.000Z', nowMs)).toBe(true);
        expect(isWithinReplyEditWindow('2026-04-10T08:59:59.000Z', nowMs)).toBe(false);
        expect(isWithinReplyEditWindow('not-a-date', nowMs)).toBe(false);
    });
});

describe('nextMessageCountLabel', function () {
    it('increments message counts and preserves singular/plural', function () {
        expect(nextMessageCountLabel('0 messages')).toBe('1 message');
        expect(nextMessageCountLabel('1 message')).toBe('2 messages');
    });

    it('returns null when the current label has no leading count', function () {
        expect(nextMessageCountLabel('messages')).toBe(null);
    });
});
