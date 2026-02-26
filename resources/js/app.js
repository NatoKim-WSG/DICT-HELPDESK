import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

(() => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (prefersReducedMotion.matches) return;

    const shouldSkipLink = (link, event) => {
        if (!link || event.defaultPrevented) return true;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return true;
        if (link.target && link.target !== '_self') return true;
        if (link.classList.contains('js-attachment-link') || link.dataset.fileUrl !== undefined) return true;
        if (link.hasAttribute('download') || link.hasAttribute('onclick') || link.dataset.noPageTransition !== undefined) return true;
        if (!link.href) return true;

        const rawHref = link.getAttribute('href') || '';
        if (rawHref.startsWith('#') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:') || rawHref.startsWith('javascript:')) {
            return true;
        }

        const currentUrl = new URL(window.location.href);
        const destinationUrl = new URL(link.href, window.location.href);
        if (destinationUrl.origin !== currentUrl.origin) return true;
        if (destinationUrl.pathname === currentUrl.pathname && destinationUrl.search === currentUrl.search && destinationUrl.hash !== currentUrl.hash) {
            return true;
        }

        return false;
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (shouldSkipLink(link, event)) return;

        event.preventDefault();
        document.documentElement.classList.add('is-page-transitioning');
        window.setTimeout(() => {
            window.location.assign(link.href);
        }, 90);
    });

    window.addEventListener('pageshow', () => {
        document.documentElement.classList.remove('is-page-transitioning');
    });
})();

(() => {
    const formNodes = document.querySelectorAll('form[data-submit-feedback]');
    formNodes.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter
                || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitter || submitter.disabled) return;

            requestAnimationFrame(() => {
                if (event.defaultPrevented || submitter.disabled) return;

                const loadingText = submitter.dataset.loadingText || 'Loading...';
                const originalText = submitter.tagName === 'INPUT'
                    ? (submitter.value || '')
                    : (submitter.textContent || '');
                submitter.dataset.originalText = originalText;
                submitter.disabled = true;
                submitter.classList.add('app-submit-busy');

                if (submitter.tagName === 'INPUT') {
                    submitter.value = loadingText;
                } else {
                    submitter.textContent = loadingText;
                }
            });
        });
    });
})();

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
        const overlay = toNode(options.overlay) || modal.querySelector(options.overlaySelector || '.app-modal-overlay');
        const panel = toNode(options.panel) || modal.querySelector(options.panelSelector || '.app-modal-panel');
        const transitionDuration = Number(options.transitionDuration || 210);
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
})();

(() => {
    const canEnhanceSelect = (select) => {
        if (!(select instanceof HTMLSelectElement)) return false;
        if (select.dataset.nativeSelect !== undefined) return false;
        if (select.dataset.enhancedReady === '1') return false;
        if (select.closest('.app-select')) return false;
        if (select.multiple) return false;

        const sizeAttr = Number(select.getAttribute('size') || '1');
        if (!Number.isNaN(sizeAttr) && sizeAttr > 1) return false;

        return true;
    };

    const enhancedSelects = new Set();
    let enhancedSelectIndex = 0;
    let openDropdown = null;

    const closeDropdown = (dropdown) => {
        if (!dropdown) return;
        dropdown.classList.remove('is-open');
        const toggle = dropdown.querySelector('.app-select-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
        if (openDropdown === dropdown) {
            openDropdown = null;
        }
    };

    const closeAllDropdowns = (except = null) => {
        enhancedSelects.forEach((select) => {
            const wrapper = select.nextElementSibling;
            if (!wrapper || !wrapper.classList.contains('app-select') || wrapper === except) return;
            closeDropdown(wrapper);
        });
    };

    const enhanceSelect = (select) => {
        if (!canEnhanceSelect(select)) return;
        select.dataset.enhancedReady = '1';
        enhancedSelects.add(select);

        const minWidth = window.getComputedStyle(select).minWidth;
        const nativeArrow = select.nextElementSibling
            && select.nextElementSibling.tagName === 'SVG'
            && select.nextElementSibling.classList.contains('pointer-events-none')
            ? select.nextElementSibling
            : null;

        const wrapper = document.createElement('div');
        wrapper.className = 'app-select';
        wrapper.dataset.enhancedIndex = String(enhancedSelectIndex++);
        if (select.classList.contains('w-full') || select.classList.contains('form-input')) {
            wrapper.classList.add('w-full');
        }
        if (select.classList.contains('form-input')) {
            wrapper.classList.add('app-select-form-field');
        }
        if (minWidth && minWidth !== '0px') {
            wrapper.style.minWidth = minWidth;
        }

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'app-select-toggle';
        toggle.setAttribute('aria-haspopup', 'listbox');
        toggle.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'truncate';

        const caret = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        caret.setAttribute('viewBox', '0 0 24 24');
        caret.setAttribute('fill', 'none');
        caret.setAttribute('stroke', 'currentColor');
        caret.classList.add('app-select-caret');
        const caretPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        caretPath.setAttribute('stroke-linecap', 'round');
        caretPath.setAttribute('stroke-linejoin', 'round');
        caretPath.setAttribute('stroke-width', '2');
        caretPath.setAttribute('d', 'M19 9l-7 7-7-7');
        caret.appendChild(caretPath);

        toggle.appendChild(label);
        toggle.appendChild(caret);

        const menu = document.createElement('div');
        menu.className = 'app-select-menu';
        menu.setAttribute('role', 'listbox');

        const syncLabel = () => {
            const selectedOption = select.options[select.selectedIndex];
            label.textContent = selectedOption ? selectedOption.textContent.trim() : 'Select option';

            menu.querySelectorAll('.app-select-option').forEach((optionButton) => {
                optionButton.classList.toggle('is-active', optionButton.dataset.value === select.value);
            });
        };

        const syncDisabledState = () => {
            const disabled = select.disabled;
            toggle.disabled = disabled;
            wrapper.classList.toggle('opacity-60', disabled);
            wrapper.classList.toggle('pointer-events-none', disabled);
            if (disabled) {
                closeDropdown(wrapper);
            }
        };

        const buildOptions = () => {
            menu.innerHTML = '';
            Array.from(select.options).forEach((option) => {
                const optionButton = document.createElement('button');
                optionButton.type = 'button';
                optionButton.className = 'app-select-option';
                optionButton.dataset.value = option.value;
                optionButton.textContent = option.textContent.trim();
                optionButton.disabled = option.disabled;
                if (option.disabled) {
                    optionButton.classList.add('opacity-50', 'cursor-not-allowed');
                }

                optionButton.addEventListener('click', () => {
                    if (option.disabled) return;
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    closeDropdown(wrapper);
                });

                menu.appendChild(optionButton);
            });

            syncLabel();
        };

        toggle.addEventListener('click', () => {
            if (toggle.disabled) return;

            const willOpen = !wrapper.classList.contains('is-open');
            closeAllDropdowns(wrapper);
            wrapper.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            openDropdown = willOpen ? wrapper : null;
        });

        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDropdown(wrapper);
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        select.addEventListener('change', syncLabel);
        const optionObserver = new MutationObserver(() => {
            buildOptions();
            syncLabel();
            syncDisabledState();
        });
        optionObserver.observe(select, {
            childList: true,
            subtree: false,
            attributes: true,
            attributeFilter: ['disabled'],
        });

        wrapper.appendChild(toggle);
        wrapper.appendChild(menu);
        select.insertAdjacentElement('afterend', wrapper);
        select.classList.add('app-native-select-hidden');
        if (nativeArrow) {
            nativeArrow.classList.add('hidden');
        }

        buildOptions();
        syncDisabledState();
    };

    Array.from(document.querySelectorAll('select')).forEach((select) => {
        enhanceSelect(select);
    });

    if (enhancedSelects.size === 0) return;

    const selectObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('select')) {
                    enhanceSelect(node);
                }
                node.querySelectorAll('select').forEach((select) => {
                    enhanceSelect(select);
                });
            });
        });
    });
    selectObserver.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('click', (event) => {
        if (!openDropdown) return;
        if (openDropdown.contains(event.target)) return;

        const toggle = openDropdown.querySelector('.app-select-toggle');
        closeDropdown(openDropdown);
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !openDropdown) return;

        const toggle = openDropdown.querySelector('.app-select-toggle');
        closeDropdown(openDropdown);
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
})();

(() => {
    const storagePrefix = 'ione_recent_searches_';
    const maxHistoryItems = 5;
    const panelTransitionMs = 170;

    const normalizeTerm = (value) => value.trim().replace(/\s+/g, ' ');
    const buildKeySegment = (value) => String(value || '')
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 80);

    const resolveSearchForms = () => {
        const explicitForms = Array.from(document.querySelectorAll('form[data-search-history-form]'));
        const inferredForms = Array.from(document.querySelectorAll('form')).filter((form) => (
            form.dataset.searchHistoryDisabled === undefined
            && form.querySelector('input[name="search"]:not([type="hidden"])')
        ));
        const merged = [];
        [...explicitForms, ...inferredForms].forEach((form) => {
            if (!merged.includes(form)) {
                merged.push(form);
            }
        });
        return merged;
    };

    const readHistory = (form) => {
        try {
            const raw = window.localStorage.getItem(form.dataset.searchHistoryStorageKey || '');
            const parsed = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map((item) => normalizeTerm(String(item)))
                .filter((item) => item.length > 0)
                .slice(0, maxHistoryItems);
        } catch (error) {
            return [];
        }
    };

    const writeHistory = (form, items) => {
        const normalized = items
            .map((item) => normalizeTerm(String(item)))
            .filter((item) => item.length > 0)
            .slice(0, maxHistoryItems);
        try {
            window.localStorage.setItem(form.dataset.searchHistoryStorageKey || '', JSON.stringify(normalized));
        } catch (error) {
        }
        return normalized;
    };

    const escapeHtml = (value) => value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const searchForms = resolveSearchForms();
    if (searchForms.length === 0) return;

    searchForms.forEach((form) => {
        const input = form.querySelector('[data-search-history-input]')
            || form.querySelector('input[name="search"]:not([type="hidden"])');
        if (!input) return;

        input.dataset.searchHistoryInput = '1';
        input.setAttribute('autocomplete', 'off');

        let panel = form.querySelector('[data-search-history-panel]');
        if (!panel) {
            const anchor = input.closest('[data-search-history-anchor]') || input.parentElement;
            if (!anchor) return;

            const anchorStyle = window.getComputedStyle(anchor);
            if (anchorStyle.position === 'static') {
                anchor.style.position = 'relative';
            }

            panel = document.createElement('div');
            panel.className = 'search-history-panel hidden';
            panel.dataset.searchHistoryPanel = '1';
            anchor.appendChild(panel);
        }

        const configuredKey = form.dataset.searchHistoryKey;
        if (!configuredKey) {
            const actionSegment = buildKeySegment(form.getAttribute('action') || window.location.pathname || 'current');
            const pageSegment = buildKeySegment(window.location.pathname || 'app');
            const fieldSegment = buildKeySegment(input.id || input.name || 'search');
            form.dataset.searchHistoryKey = [pageSegment, actionSegment, fieldSegment].filter(Boolean).join('_');
        }
        form.dataset.searchHistoryStorageKey = storagePrefix + form.dataset.searchHistoryKey;

        let historyItems = readHistory(form);
        let closeTimer = null;

        const closePanel = () => {
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
            panel.classList.remove('is-open');
            closeTimer = window.setTimeout(() => {
                panel.classList.add('hidden');
            }, panelTransitionMs);
        };

        const openPanel = () => {
            if (panel.innerHTML.trim() === '') return;
            if (closeTimer !== null) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
            panel.classList.remove('hidden');
            requestAnimationFrame(() => {
                panel.classList.add('is-open');
            });
        };

        const renderPanel = (filter = '') => {
            const normalizedFilter = normalizeTerm(filter).toLowerCase();
            const filteredItems = normalizedFilter
                ? historyItems.filter((item) => item.toLowerCase().includes(normalizedFilter))
                : historyItems;

            if (historyItems.length === 0) {
                panel.innerHTML = '<p class="search-history-empty">No recent searches yet.</p>';
                return;
            }

            if (filteredItems.length === 0) {
                panel.innerHTML = '<p class="search-history-empty">No matches in recent searches.</p>';
                return;
            }

            const itemButtons = filteredItems.map((item) => (
                '<button type="button" class="search-history-item" data-history-value="'
                + escapeHtml(item)
                + '"><strong>'
                + escapeHtml(item)
                + '</strong><span class="search-history-remove" data-history-remove="'
                + escapeHtml(item)
                + '" aria-label="Remove search">&times;</span></button>'
            )).join('');

            panel.innerHTML = '<div class="search-history-head"><span>Recent searches</span><button type="button" class="search-history-clear" data-history-clear>Clear</button></div>' + itemButtons;
        };

        input.addEventListener('focus', () => {
            renderPanel(input.value);
            if (historyItems.length > 0) {
                openPanel();
            }
        });

        input.addEventListener('input', () => {
            renderPanel(input.value);
            if (historyItems.length > 0) {
                openPanel();
            } else {
                closePanel();
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePanel();
            }
        });

        panel.addEventListener('mousedown', (event) => {
            event.preventDefault();
        });

        panel.addEventListener('click', (event) => {
            const clearButton = event.target.closest('[data-history-clear]');
            if (clearButton) {
                historyItems = writeHistory(form, []);
                renderPanel(input.value);
                closePanel();
                return;
            }

            const removeButton = event.target.closest('[data-history-remove]');
            if (removeButton) {
                const valueToRemove = normalizeTerm(removeButton.dataset.historyRemove || '');
                historyItems = writeHistory(
                    form,
                    historyItems.filter((item) => item.toLowerCase() !== valueToRemove.toLowerCase()),
                );
                renderPanel(input.value);
                if (historyItems.length === 0 || panel.querySelector('.search-history-item') === null) {
                    closePanel();
                }
                return;
            }

            const itemButton = event.target.closest('[data-history-value]');
            if (!itemButton) return;

            const selectedValue = normalizeTerm(itemButton.dataset.historyValue || '');
            input.value = selectedValue;
            closePanel();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });

        form.addEventListener('submit', () => {
            const term = normalizeTerm(input.value || '');
            if (!term) return;

            historyItems = writeHistory(form, [
                term,
                ...historyItems.filter((item) => item.toLowerCase() !== term.toLowerCase()),
            ]);
        });

        document.addEventListener('click', (event) => {
            if (form.contains(event.target)) return;
            closePanel();
        });
    });
})();
