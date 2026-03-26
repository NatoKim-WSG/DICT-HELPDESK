export const registerEnhancedSelects = () => {
    const canEnhanceSelect = (select) => {
        if (!(select instanceof HTMLSelectElement)) return false;
        if (select.dataset.nativeSelect !== undefined) return false;
        if (select.dataset.enhancedReady === '1') return false;
        if (select.closest('.app-select')) return false;

        if (select.multiple) {
            return select.dataset.enhancedMultiselect === '1';
        }

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
        const capitalizeDisplay = select.dataset.textTransform === 'capitalize';
        const isMultiSelect = select.multiple;
        const placeholder = select.dataset.placeholder || 'Select option';

        const minWidth = window.getComputedStyle(select).minWidth;
        const nativeArrow = select.nextElementSibling
            && select.nextElementSibling.tagName === 'SVG'
            && select.nextElementSibling.classList.contains('pointer-events-none')
            ? select.nextElementSibling
            : null;

        const wrapper = document.createElement('div');
        wrapper.className = 'app-select';
        wrapper.dataset.enhancedIndex = String(enhancedSelectIndex++);
        if (isMultiSelect) {
            wrapper.classList.add('app-select--multiple');
        }
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
        if (capitalizeDisplay) {
            label.classList.add('capitalize');
        }

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
        if (isMultiSelect) {
            menu.setAttribute('aria-multiselectable', 'true');
        }

        const getSelectedOptions = () => Array.from(select.options).filter((option) => option.selected);

        const syncLabel = () => {
            if (isMultiSelect) {
                const selectedLabels = getSelectedOptions()
                    .map((option) => option.textContent.trim())
                    .filter((text) => text !== '');

                label.textContent = selectedLabels.length > 0
                    ? selectedLabels.join(', ')
                    : placeholder;
            } else {
                const selectedOption = select.options[select.selectedIndex];
                label.textContent = selectedOption ? selectedOption.textContent.trim() : placeholder;
            }

            menu.querySelectorAll('.app-select-option').forEach((optionButton) => {
                const isSelected = isMultiSelect
                    ? getSelectedOptions().some((option) => option.value === optionButton.dataset.value)
                    : optionButton.dataset.value === select.value;

                optionButton.classList.toggle('is-active', isSelected);
                optionButton.setAttribute('aria-selected', isSelected ? 'true' : 'false');
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
                if (capitalizeDisplay) {
                    optionButton.classList.add('capitalize');
                }
                optionButton.dataset.value = option.value;
                optionButton.textContent = option.textContent.trim();
                optionButton.disabled = option.disabled;
                if (option.disabled) {
                    optionButton.classList.add('opacity-50', 'cursor-not-allowed');
                }

                optionButton.addEventListener('click', () => {
                    if (option.disabled) return;

                    if (isMultiSelect) {
                        option.selected = !option.selected;
                    } else {
                        select.value = option.value;
                    }

                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();

                    if (!isMultiSelect) {
                        closeDropdown(wrapper);
                    }
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
};
