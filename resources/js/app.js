import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

(() => {
    const openModals = new Set();

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

        const isOpen = () => !modal.classList.contains('hidden');

        const open = () => {
            modal.classList.remove('hidden');
            openModals.add(modal);
            syncBodyScroll();
            if (typeof options.onOpen === 'function') options.onOpen();
        };

        const close = () => {
            modal.classList.add('hidden');
            openModals.delete(modal);
            syncBodyScroll();
            if (typeof options.onClose === 'function') options.onClose();
        };

        if (options.initialOpen || isOpen()) {
            openModals.add(modal);
            syncBodyScroll();
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
})();
