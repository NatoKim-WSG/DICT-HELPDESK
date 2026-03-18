export const registerModalKit = () => {
    const openModals = new Set();

    const resolveTransitionDuration = (customDuration) => {
        const parsedCustomDuration = Number(customDuration);
        if (Number.isFinite(parsedCustomDuration) && parsedCustomDuration > 0) {
            return parsedCustomDuration;
        }

        const durationToken = window.getComputedStyle(document.documentElement)
            .getPropertyValue('--motion-duration-base')
            .trim();
        const durationMatch = durationToken.match(/^([\d.]+)\s*(ms|s)?$/i);

        if (!durationMatch) return 220;

        const durationValue = Number.parseFloat(durationMatch[1]);
        if (!Number.isFinite(durationValue) || durationValue <= 0) return 220;

        const unit = (durationMatch[2] || 'ms').toLowerCase();
        return unit === 's' ? durationValue * 1000 : durationValue;
    };

    const syncBodyScroll = () => {
        if (openModals.size > 0) {
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    };

    const toNode = (value) => {
        if (!value) return null;
        if (value instanceof Element) return value;
        if (typeof value === 'string') return document.querySelector(value);
        return null;
    };

    const toNodes = (items) => {
        if (!Array.isArray(items)) return [];
        return items.map((item) => toNode(item)).filter(Boolean);
    };

    const bind = (modalRef, options = {}) => {
        const modal = toNode(modalRef);
        if (!modal) return null;

        const closeTriggerSelector = options.closeTriggerSelector
            || '[data-modal-close], [data-modal-overlay], [data-close-ticket-overlay], [data-resolve-ticket-overlay]';
        const closeButtons = toNodes(options.closeButtons || []);
        const openButtons = toNodes(options.openButtons || []);
        const overlay = toNode(options.overlay) || modal.querySelector(options.overlaySelector || '.app-modal-overlay');
        const panel = toNode(options.panel) || modal.querySelector(options.panelSelector || '.app-modal-panel');
        const transitionDuration = resolveTransitionDuration(options.transitionDuration);
        let closeTimer = null;

        modal.classList.add('app-modal-root');

        const isOpen = () => !modal.classList.contains('hidden');
        const clearCloseTimer = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        };

        const finalizeClose = () => {
            modal.classList.add('hidden');
            modal.classList.remove('is-open', 'is-closing');
            openModals.delete(modal);
            syncBodyScroll();
            if (typeof options.onClose === 'function') options.onClose();
        };

        const open = () => {
            clearCloseTimer();
            modal.classList.remove('hidden');
            modal.classList.remove('is-closing');
            openModals.add(modal);
            syncBodyScroll();

            if (overlay || panel) {
                requestAnimationFrame(() => {
                    modal.classList.add('is-open');
                });
            }

            if (typeof options.onOpen === 'function') options.onOpen();
        };

        const close = () => {
            clearCloseTimer();

            if (!(overlay || panel)) {
                finalizeClose();
                return;
            }

            modal.classList.remove('is-open');
            modal.classList.add('is-closing');
            closeTimer = window.setTimeout(finalizeClose, transitionDuration);
        };

        if (options.initialOpen || isOpen()) {
            openModals.add(modal);
            syncBodyScroll();
            modal.classList.add('is-open');
        }

        const modalClickHandler = (event) => {
            const trigger = event.target.closest(closeTriggerSelector);
            if (!trigger) return;
            close();
        };

        const keyHandler = (event) => {
            if (event.key === 'Escape' && isOpen()) {
                close();
            }
        };

        modal.addEventListener('click', modalClickHandler);
        document.addEventListener('keydown', keyHandler);

        openButtons.forEach((button) => {
            button.addEventListener('click', open);
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', close);
        });

        const destroy = () => {
            clearCloseTimer();
            modal.removeEventListener('click', modalClickHandler);
            document.removeEventListener('keydown', keyHandler);
        };

        return { modal, open, close, isOpen, destroy };
    };

    const bindById = (modalId, options = {}) => bind(document.getElementById(modalId), options);

    const bindAttachmentPreview = (options = {}) => {
        const modal = toNode(options.modal);
        const title = toNode(options.title);
        const image = toNode(options.image);
        const frame = toNode(options.frame);
        const closeButton = toNode(options.closeButton);
        const triggerSelector = options.triggerSelector || '.js-attachment-link';

        if (!modal || !image || !frame) return null;

        const controller = bind(modal, {
            closeButtons: closeButton ? [closeButton] : [],
            onClose: () => {
                image.classList.add('hidden');
                frame.classList.add('hidden');
                image.removeAttribute('src');
                frame.removeAttribute('src');
            },
        });

        const open = ({ url, fileName, mimeType }) => {
            if (title) title.textContent = fileName || 'Attachment Preview';

            if (mimeType && mimeType.startsWith('image/')) {
                image.src = url;
                image.classList.remove('hidden');
                frame.classList.add('hidden');
            } else {
                frame.src = url;
                frame.classList.remove('hidden');
                image.classList.add('hidden');
            }

            controller.open();
        };

        const openFromLink = (link) => {
            if (!link) return;
            open({
                url: link.dataset.fileUrl,
                fileName: link.dataset.fileName,
                mimeType: link.dataset.fileMime,
            });
        };

        if (triggerSelector) {
            document.addEventListener('click', (event) => {
                const link = event.target.closest(triggerSelector);
                if (!link) return;
                event.preventDefault();
                openFromLink(link);
            });
        }

        return { ...controller, open, openFromLink };
    };

    window.ModalKit = { bind, bindById, bindAttachmentPreview };
};
