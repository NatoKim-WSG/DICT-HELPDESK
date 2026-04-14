import { createAttachmentLink } from './ticket-thread-attachments';
import { applyReplyStateToRow, incrementMessageCount, replyReferenceText } from './ticket-thread-helpers';
import { createThreadTimeSeparator, shouldInsertTimeSeparator } from './ticket-thread-time';

export const createClientTicketThreadView = ({
    thread,
    clientLogo,
    supportLogo,
    messageCount,
    queueSeenSync,
    getEditingReplyId,
    clearEditingTarget,
    isWithinEditDeleteWindow,
    timestampBreakMinutes = 15,
}) => {
    const renderTimeSeparators = () => {
        if (!thread) return;

        thread.querySelectorAll('.js-time-separator').forEach((el) => {
            el.remove();
        });

        const rows = Array.from(thread.querySelectorAll('.js-chat-row'));
        let lastCreatedAtIso = '';

        rows.forEach((row) => {
            const createdAt = row.dataset.createdAt;
            if (!createdAt) return;

            if (!lastCreatedAtIso || shouldInsertTimeSeparator(lastCreatedAtIso, createdAt, timestampBreakMinutes)) {
                const separator = createThreadTimeSeparator(createdAt);
                if (separator) {
                    row.parentNode.insertBefore(separator, row);
                }
            }

            lastCreatedAtIso = createdAt;
        });
    };

    const buildOwnMessageControls = (isDeleted, createdAtIso) => {
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

    const createReferenceBlock = (replyToMessage, labelText) => {
        const ref = document.createElement('div');
        ref.className = 'js-reply-reference mb-2';

        const label = document.createElement('p');
        label.className = 'js-reply-reference-label mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500';
        label.innerHTML = `<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg><span>${labelText || 'You replied'}</span>`;

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

    const appendReplyToThread = (reply) => {
        if (!thread || !reply) return;

        const fromSupport = Boolean(reply.from_support);
        const canManage = Boolean(reply.can_manage);
        const createdAt = reply.created_at_iso || new Date().toISOString();
        const replyId = String(reply.id || '');

        const wrap = document.createElement('div');
        wrap.className = `js-chat-row flex ${fromSupport ? 'justify-start' : 'justify-end'}`;
        wrap.dataset.createdAt = createdAt;
        wrap.dataset.replyId = replyId;
        wrap.dataset.isSupport = fromSupport ? '1' : '0';
        wrap.dataset.canManage = canManage ? '1' : '0';

        const rowContent = document.createElement('div');
        rowContent.className = `flex w-full max-w-3xl items-start gap-2 ${fromSupport ? '' : 'justify-end'}`;

        const avatar = document.createElement('div');
        avatar.className = `mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white ${fromSupport ? '' : 'order-2'}`;
        avatar.innerHTML = `<img src="${reply.avatar_logo || (fromSupport ? supportLogo : clientLogo)}" alt="${fromSupport ? 'Support' : 'Client'} company logo" class="avatar-logo">`;

        const bubble = document.createElement('div');
        bubble.className = `js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm ${fromSupport ? 'border-slate-200 bg-white' : 'order-1 border-ione-blue-200 bg-ione-blue-50'}${reply.deleted ? ' chat-bubble-deleted' : ''}`;
        bubble.dataset.message = reply.message || '';
        bubble.dataset.deleted = reply.deleted ? '1' : '0';
        bubble.dataset.edited = reply.edited ? '1' : '0';
        const showEditedBadge = Boolean(reply.edited) && !reply.deleted;

        const meta = document.createElement('div');
        meta.className = `js-state-row mb-1 flex items-center gap-2 ${(showEditedBadge || reply.deleted) ? '' : 'hidden'}`;
        meta.innerHTML = `<span class="js-edited-badge chat-meta-badge ${showEditedBadge ? '' : 'hidden'}">Edited</span><span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted ${reply.deleted ? '' : 'hidden'}">Deleted</span>`;

        if (!fromSupport && canManage) {
            meta.appendChild(buildOwnMessageControls(Boolean(reply.deleted), createdAt));
        } else {
            const controls = document.createElement('div');
            controls.className = 'js-message-actions absolute -right-10 top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100';
            controls.innerHTML = '<button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="Reply to this message"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg></button>';
            meta.appendChild(controls);
        }

        const reference = createReferenceBlock(replyReferenceText(reply), fromSupport ? 'Support replied' : 'You replied');

        const text = document.createElement('p');
        text.className = `js-message-text whitespace-pre-wrap text-sm leading-6 ${reply.deleted ? 'italic text-slate-500' : 'text-slate-800'}`;
        text.textContent = reply.message || '';

        bubble.appendChild(meta);
        bubble.appendChild(reference);
        bubble.appendChild(text);

        if (!reply.deleted && Array.isArray(reply.attachments) && reply.attachments.length > 0) {
            const attachmentGrid = document.createElement('div');
            attachmentGrid.className = 'js-attachments-wrap mt-4 flex flex-wrap gap-2';
            reply.attachments.forEach((attachment) => {
                attachmentGrid.appendChild(createAttachmentLink(attachment, { borderClass: 'border-ione-blue-200' }));
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

    const applyReplyState = (row, reply) => {
        applyReplyStateToRow({
            row,
            reply,
            onDeleted: (currentRow) => {
                const menu = currentRow.querySelector('.js-more-menu');
                if (menu) menu.innerHTML = '';

                if (getEditingReplyId() && String(currentRow.dataset.replyId) === String(getEditingReplyId())) {
                    clearEditingTarget({ resetInput: true });
                }
            },
        });
    };

    const appendReplyAndIncrement = (reply) => {
        appendReplyToThread(reply);
        incrementMessageCount(messageCount);
    };

    return {
        appendReplyAndIncrement,
        applyReplyState,
        renderTimeSeparators,
    };
};
