import { createAttachmentLink } from './ticket-thread-attachments';
import { applyReplyStateToRow, incrementMessageCount, replyReferenceText } from './ticket-thread-helpers';
import { appendThreadSeparatorIfNeeded } from './ticket-thread-time';

const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const nl2br = (value) => escapeHtml(value).replace(/\n/g, '<br>');

const attachmentLinkHtml = (attachment) => createAttachmentLink(attachment).outerHTML;

export const createAdminTicketThreadView = ({
    thread,
    supportLogo,
    defaultClientLogo,
    messageCountNode,
    queueSeenSync,
    getEditingReplyId,
    clearEditingTarget,
}) => {
    const appendReply = (payload) => {
        if (!thread || !payload) return;

        appendThreadSeparatorIfNeeded(thread, payload.created_at_iso, 15);

        const fromSupport = Boolean(payload.from_support);
        const canManage = Boolean(payload.can_manage);
        const row = document.createElement('div');
        row.className = `js-chat-row flex ${fromSupport ? 'justify-end' : 'justify-start'}`;
        row.dataset.createdAt = payload.created_at_iso;
        row.dataset.replyId = payload.id;
        row.dataset.canManage = canManage ? '1' : '0';
        row.dataset.isInternal = payload.is_internal ? '1' : '0';
        const avatarLogo = payload.avatar_logo || (fromSupport ? supportLogo : defaultClientLogo);
        const isDeleted = Boolean(payload.deleted);
        const isEdited = Boolean(payload.edited);
        const showEditedBadge = isEdited && !isDeleted;
        const internalBadge = payload.is_internal
            ? '<div class="mb-1 flex items-center gap-2"><span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">Internal</span></div>'
            : '';
        const replyRefText = replyReferenceText(payload);
        const replyReference = replyRefText
            ? `<div class="js-reply-reference mb-2"><p class="mb-1 flex items-center gap-1 text-[11px] font-semibold text-slate-500"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg>${fromSupport ? 'Support replied' : 'Client replied'}</p><div class="js-reply-reference-text rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700">${escapeHtml(replyRefText)}</div></div>`
            : '';
        const attachmentsHtml = !isDeleted && Array.isArray(payload.attachments) && payload.attachments.length
            ? `<div class="js-attachments-wrap mt-4 flex flex-wrap gap-2">${payload.attachments.map((attachment) => attachmentLinkHtml(attachment)).join('')}</div>`
            : '';
        const stateRow = `<div class="js-state-row mb-1 flex items-center gap-2 ${(showEditedBadge || isDeleted) ? '' : 'hidden'}"><span class="js-edited-badge chat-meta-badge ${showEditedBadge ? '' : 'hidden'}">Edited</span><span class="js-deleted-badge chat-meta-badge chat-meta-badge--deleted ${isDeleted ? '' : 'hidden'}">Deleted</span></div>`;
        const moreActions = canManage
            ? `<div class="relative"><button type="button" class="js-more-btn inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="More actions"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg></button><div class="js-more-menu absolute ${fromSupport ? 'left-0' : 'right-0'} z-20 mt-1 hidden min-w-[110px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">${isDeleted ? '' : '<button type="button" class="js-edit-msg block w-full px-3 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button><button type="button" class="js-delete-msg block w-full px-3 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50">Delete</button>'}</div></div>`
            : '';

        row.innerHTML =
            `<div class="flex w-full max-w-3xl items-start gap-2 ${fromSupport ? 'justify-end' : ''}">` +
                `<div class="mt-1 flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white ${fromSupport ? 'order-2' : ''}">` +
                    `<img src="${avatarLogo}" alt="${fromSupport ? 'Support' : 'Client'} company logo" class="avatar-logo">` +
                '</div>' +
                `<div class="js-chat-bubble relative group w-fit max-w-[82%] rounded-2xl border px-4 py-3 shadow-sm ${fromSupport ? 'order-1 border-ione-blue-200 bg-ione-blue-50' : 'border-slate-200 bg-white'}${isDeleted ? ' chat-bubble-deleted' : ''}" data-message="${escapeHtml(payload.message || '')}" data-deleted="${isDeleted ? '1' : '0'}" data-edited="${isEdited ? '1' : '0'}">` +
                    stateRow +
                    internalBadge +
                    replyReference +
                    `<p class="js-message-text whitespace-pre-wrap text-sm leading-6 ${isDeleted ? 'italic text-slate-500' : 'text-slate-800'}">${nl2br(payload.message)}</p>` +
                    attachmentsHtml +
                    `<div class="js-message-actions absolute ${fromSupport ? '-left-[4.75rem]' : '-right-10'} top-1.5 flex items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-sm opacity-0 transition group-hover:opacity-100">` +
                        moreActions +
                        '<button type="button" class="js-reply-msg inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5f4b8b] text-white hover:bg-[#4f3b76]" aria-label="Reply to this message"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h11a4 4 0 014 4v5m0 0 3-3m-3 3-3-3M3 10l4-4m-4 4 4 4"/></svg></button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        thread.appendChild(row);
        thread.scrollTop = thread.scrollHeight;
        queueSeenSync(payload.created_at_iso || '');
        incrementMessageCount(messageCountNode);
    };

    const applyReplyState = (row, reply) => {
        applyReplyStateToRow({
            row,
            reply,
            onDeleted: (currentRow) => {
                currentRow.querySelectorAll('.js-edit-msg, .js-delete-msg').forEach((btn) => {
                    btn.remove();
                });

                if (getEditingReplyId() && String(currentRow.dataset.replyId) === String(getEditingReplyId())) {
                    clearEditingTarget({ resetInput: true });
                }
            },
        });
    };

    return {
        appendReply,
        applyReplyState,
    };
};
