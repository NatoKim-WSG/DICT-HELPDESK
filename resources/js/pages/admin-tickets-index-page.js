import { bootPage } from './shared/boot-page';

const DEFAULT_STATUS_VIEW = 'all';

const initAdminTicketsIndexPage = () => {
    const pageRoot = document.querySelector('[data-admin-tickets-index-page]');
    if (!pageRoot) return;

    const filterForm = pageRoot.querySelector('form[data-search-history-form]');
    const statusView = document.getElementById('admin-status-view');
    const routeBase = pageRoot.dataset.routeBase || window.location.pathname;
    const assignForm = document.getElementById('assign-ticket-form');
    const assignModal = document.getElementById('assign-ticket-modal');
    const assignTicketText = document.getElementById('assign-modal-ticket');
    const assignAssigneeSelect = document.getElementById('assign-modal-select');
    const revertForm = document.getElementById('revert-ticket-form');
    const revertModal = document.getElementById('revert-ticket-modal');
    const revertTicketText = document.getElementById('revert-modal-ticket');
    const revertConfirmCheckbox = document.getElementById('revert-confirm-checkbox');
    const revertSubmitButton = document.getElementById('revert-submit-btn');
    const editForm = document.getElementById('edit-ticket-form');
    const editModal = document.getElementById('edit-ticket-modal');
    const editTicketText = document.getElementById('edit-modal-ticket');
    const editAssignedSelect = document.getElementById('edit-modal-assigned');
    const editStatusSelect = document.getElementById('edit-modal-status');
    const editCloseReasonWrap = document.getElementById('edit-modal-close-reason-wrap');
    const editCloseReasonInput = document.getElementById('edit-modal-close-reason');
    const editCloseHint = document.getElementById('edit-modal-close-hint');
    const editPrioritySelect = document.getElementById('edit-modal-priority');
    const editTicketTypeSelect = document.getElementById('edit-modal-ticket-type');
    const editDeleteButton = document.getElementById('edit-modal-delete-btn');
    const deleteForm = document.getElementById('delete-ticket-form');
    const deleteModal = document.getElementById('delete-ticket-modal');
    const deleteTicketText = document.getElementById('delete-modal-ticket');
    const bulkDeleteForm = document.getElementById('bulk-ticket-delete-form');
    const bulkDeleteButton = document.getElementById('bulk-delete-submit');
    const bulkSelectedIds = document.getElementById('bulk-selected-ids');
    const bulkDeleteConfirmModal = document.getElementById('bulk-delete-confirm-modal');
    const bulkDeleteConfirmCheckbox = document.getElementById('bulk-delete-confirm-checkbox');
    const bulkDeleteConfirmSubmit = document.getElementById('bulk-delete-confirm-submit');
    const assignRouteTemplate = pageRoot.dataset.assignRouteTemplate || '';
    const statusRouteTemplate = pageRoot.dataset.statusRouteTemplate || '';
    const quickUpdateRouteTemplate = pageRoot.dataset.quickUpdateRouteTemplate || '';
    const deleteRouteTemplate = pageRoot.dataset.deleteRouteTemplate || '';
    const filterFieldSelectors = [
        'select[name="priority"]',
        'select[name="category"]',
        'select[name="province"]',
        'select[name="municipality"]',
        'select[name="month"]',
        'select[name="assigned_to"]',
        'select[name="account_id"]',
    ];

    const assignModalController = window.ModalKit ? window.ModalKit.bind(assignModal) : null;
    const revertModalController = window.ModalKit && revertModal ? window.ModalKit.bind(revertModal) : null;
    const editModalController = window.ModalKit ? window.ModalKit.bind(editModal) : null;
    const deleteModalController = window.ModalKit && deleteModal ? window.ModalKit.bind(deleteModal) : null;
    const bulkDeleteConfirmModalController = window.ModalKit && bulkDeleteConfirmModal ? window.ModalKit.bind(bulkDeleteConfirmModal) : null;

    let snapshotToken = pageRoot.dataset.snapshotToken || '';
    let filterSubmitTimeout = null;
    let activeRequestId = 0;
    let activeRequestController = null;
    let isResultsLoading = false;
    let isSyncingFilters = false;
    let allowBulkDeleteSubmit = false;

    const getResultsContainer = () => pageRoot.querySelector('[data-admin-tickets-results]');
    const getRowCheckboxes = () => Array.from(pageRoot.querySelectorAll('.js-ticket-checkbox'));
    const getSelectAllCheckbox = () => pageRoot.querySelector('#select-all-tickets');

    const selectedTicketIds = function () {
        return getRowCheckboxes()
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.value; });
    };

    const relativePathForUrl = function (url) {
        return `${url.pathname}${url.search}`;
    };

    const parseAssignedIds = function (rawValue) {
        if (!rawValue) {
            return [];
        }

        try {
            const parsed = JSON.parse(rawValue);
            if (Array.isArray(parsed)) {
                return parsed.map(function (value) {
                    return String(value);
                });
            }
        } catch (error) {
        }

        return String(rawValue)
            .split(',')
            .map(function (value) { return value.trim(); })
            .filter(function (value) { return value !== ''; });
    };

    const setMultiSelectValues = function (select, values) {
        if (!(select instanceof HTMLSelectElement)) return;
        const selectedValues = new Set(values);
        Array.from(select.options).forEach(function (option) {
            option.selected = selectedValues.has(option.value);
        });
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const normalizeResultsUrl = function (url) {
        const normalized = new URL(url, window.location.origin);
        normalized.searchParams.delete('partial');
        normalized.searchParams.delete('heartbeat');

        return normalized;
    };

    const updateReturnPathInputs = function (path) {
        document.querySelectorAll('input[name="return_to"]').forEach(function (input) {
            input.value = path;
        });
    };

    const syncBulkSelection = function () {
        const selectedCount = selectedTicketIds().length;
        const selectAllCheckbox = getSelectAllCheckbox();
        const rowCheckboxes = getRowCheckboxes();

        if (bulkDeleteButton) {
            bulkDeleteButton.disabled = selectedCount === 0;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = rowCheckboxes.length > 0 && rowCheckboxes.every(function (checkbox) { return checkbox.checked; });
            selectAllCheckbox.indeterminate = selectedCount > 0 && !selectAllCheckbox.checked;
        }
    };

    const resetBulkSelection = function () {
        const selectAllCheckbox = getSelectAllCheckbox();
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }

        getRowCheckboxes().forEach(function (checkbox) {
            checkbox.checked = false;
        });

        if (bulkSelectedIds) {
            bulkSelectedIds.innerHTML = '';
        }

        syncBulkSelection();
    };

    const syncBulkDeleteConfirmState = function () {
        if (!bulkDeleteConfirmSubmit || !bulkDeleteConfirmCheckbox) return;

        bulkDeleteConfirmSubmit.disabled = !bulkDeleteConfirmCheckbox.checked;
    };

    const resetValueForField = function (fieldName, params) {
        const paramValue = params.get(fieldName);
        if (paramValue !== null) {
            return paramValue;
        }

        if (fieldName === 'tab') {
            return 'tickets';
        }

        if (['search', 'month', 'created_from', 'created_to', 'report_scope'].includes(fieldName)) {
            return '';
        }

        return 'all';
    };

    const syncFilterFormFromUrl = function (url) {
        if (!filterForm) return;

        const params = url.searchParams;
        isSyncingFilters = true;

        filterForm.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
            if (field.disabled) return;

            const tagName = field.tagName.toLowerCase();
            const type = (field.getAttribute('type') || '').toLowerCase();
            if (tagName === 'button' || ['button', 'submit', 'reset', 'checkbox', 'radio', 'file'].includes(type)) {
                return;
            }

            const nextValue = resetValueForField(field.name, params);
            const valueChanged = field.value !== nextValue;
            field.value = nextValue;

            if (valueChanged && tagName === 'select') {
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        if (statusView) {
            statusView.value = params.get('status') ?? DEFAULT_STATUS_VIEW;
        }

        isSyncingFilters = false;
    };

    const applyLoadingState = function () {
        const resultsContainer = getResultsContainer();
        if (!resultsContainer) return;

        resultsContainer.classList.toggle('opacity-60', isResultsLoading);
        resultsContainer.classList.toggle('transition-opacity', true);
        resultsContainer.setAttribute('aria-busy', isResultsLoading ? 'true' : 'false');
    };

    const buildFilterUrl = function () {
        const targetUrl = new URL(routeBase, window.location.origin);
        const selectedMonth = filterForm?.querySelector('select[name="month"]')?.value?.trim() || '';
        const formData = filterForm ? new FormData(filterForm) : new FormData();

        for (const [key, rawValue] of formData.entries()) {
            const value = String(rawValue).trim();
            if (value === '') {
                continue;
            }

            if (key !== 'tab' && value === 'all') {
                continue;
            }

            if (selectedMonth !== '' && ['created_from', 'created_to', 'report_scope'].includes(key)) {
                continue;
            }

            targetUrl.searchParams.append(key, value);
        }

        if (statusView && statusView.value !== DEFAULT_STATUS_VIEW) {
            targetUrl.searchParams.set('status', statusView.value);
        }

        return targetUrl;
    };

    const loadTicketResults = async function (url, { history = 'replace' } = {}) {
        const normalizedUrl = normalizeResultsUrl(url);
        const requestUrl = new URL(normalizedUrl.toString());
        requestUrl.searchParams.set('partial', '1');

        const requestId = ++activeRequestId;
        if (activeRequestController) {
            activeRequestController.abort();
        }

        activeRequestController = new AbortController();
        isResultsLoading = true;
        applyLoadingState();

        try {
            const response = await fetch(requestUrl.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: activeRequestController.signal,
            });

            if (!response.ok) {
                throw new Error(`Ticket results request failed with status ${response.status}`);
            }

            const payload = await response.json();
            if (activeRequestId !== requestId) {
                return;
            }

            const resultsContainer = getResultsContainer();
            if (!resultsContainer || typeof payload?.html !== 'string') {
                throw new Error('Ticket results payload was incomplete.');
            }

            resultsContainer.outerHTML = payload.html;
            snapshotToken = payload.token || '';
            pageRoot.dataset.snapshotToken = snapshotToken;

            if (history === 'push') {
                window.history.pushState({ adminTickets: true }, '', relativePathForUrl(normalizedUrl));
            } else if (history === 'replace') {
                window.history.replaceState({ adminTickets: true }, '', relativePathForUrl(normalizedUrl));
            }

            syncFilterFormFromUrl(normalizedUrl);
            updateReturnPathInputs(relativePathForUrl(normalizedUrl));
            resetBulkSelection();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            window.location.assign(relativePathForUrl(normalizedUrl));
        } finally {
            if (activeRequestId === requestId) {
                activeRequestController = null;
                isResultsLoading = false;
                applyLoadingState();
            }
        }
    };

    const submitFilters = function () {
        if (!filterForm) return;
        if (isSyncingFilters) return;

        if (filterSubmitTimeout) {
            window.clearTimeout(filterSubmitTimeout);
            filterSubmitTimeout = null;
        }

        const targetUrl = buildFilterUrl();
        targetUrl.searchParams.delete('page');
        void loadTicketResults(targetUrl, { history: 'replace' });
    };

    const syncRevertSubmitState = function () {
        if (!revertSubmitButton || !revertConfirmCheckbox) return;
        revertSubmitButton.disabled = !revertConfirmCheckbox.checked;
    };

    const syncEditCloseReasonVisibility = function () {
        if (!editStatusSelect || !editCloseReasonWrap || !editCloseReasonInput) return;

        const isClosed = editStatusSelect.value === 'closed';
        editCloseReasonWrap.classList.toggle('hidden', !isClosed);
        editCloseReasonInput.required = isClosed;
        if (!isClosed) {
            editCloseReasonInput.value = '';
        }
    };

    const populateEditModal = function (button) {
        const ticketId = button.dataset.ticketId;
        if (!ticketId || !editForm) return;

        editForm.action = quickUpdateRouteTemplate.replace('__TICKET__', ticketId);
        if (editTicketText) {
            editTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
        }
        setMultiSelectValues(editAssignedSelect, parseAssignedIds(button.dataset.assignedTo || '[]'));
        if (editStatusSelect) editStatusSelect.value = button.dataset.status || 'open';
        if (editCloseReasonInput) editCloseReasonInput.value = '';
        if (editPrioritySelect) editPrioritySelect.value = button.dataset.priority || '';
        if (editTicketTypeSelect) editTicketTypeSelect.value = button.dataset.ticketType || 'external';

        if (editStatusSelect) {
            const closeAllowed = button.dataset.canCloseNow === '1';
            const canRevert = button.dataset.canRevert === '1';
            const isClosedTicket = (button.dataset.status || '') === 'closed';
            const closedOption = editStatusSelect.querySelector('option[value="closed"]');

            ['open', 'in_progress', 'pending', 'resolved'].forEach(function (statusValue) {
                const option = editStatusSelect.querySelector(`option[value="${statusValue}"]`);
                if (option) {
                    option.disabled = isClosedTicket && !canRevert;
                }
            });

            if (closedOption) {
                closedOption.disabled = !closeAllowed;
                closedOption.textContent = closeAllowed ? 'Closed' : 'Closed (after 24h)';
            }

            if (isClosedTicket && !canRevert) {
                editStatusSelect.value = 'closed';
            }

            if (!closeAllowed && editStatusSelect.value === 'closed') {
                editStatusSelect.value = button.dataset.status || 'resolved';
            }

            if (editCloseHint) {
                if (isClosedTicket && !canRevert) {
                    editCloseHint.classList.remove('hidden');
                    editCloseHint.textContent = 'Closed tickets cannot be reverted after 7 days.';
                } else if (closeAllowed) {
                    editCloseHint.classList.add('hidden');
                    editCloseHint.textContent = '';
                } else {
                    const closeAvailableAt = button.dataset.closeAvailableAt || 'the 24-hour window after resolution';
                    editCloseHint.classList.remove('hidden');
                    editCloseHint.textContent = 'Close is available on ' + closeAvailableAt + '.';
                }
            }
        }

        syncEditCloseReasonVisibility();

        if (editDeleteButton) {
            editDeleteButton.dataset.ticketId = ticketId;
            editDeleteButton.dataset.ticketNumber = button.dataset.ticketNumber || '';
        }
    };

    const hasOpenModal = function () {
        return [assignModal, revertModal, editModal, deleteModal, bulkDeleteConfirmModal].some(function (modal) {
            return modal && !modal.classList.contains('hidden');
        });
    };

    const pollTicketListSnapshot = async function () {
        if (!snapshotToken || document.hidden || hasOpenModal() || isResultsLoading) return;

        const heartbeatUrl = normalizeResultsUrl(window.location.href);
        heartbeatUrl.searchParams.set('heartbeat', '1');

        try {
            const response = await fetch(heartbeatUrl.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== snapshotToken) {
                await loadTicketResults(window.location.href, { history: 'none' });
                return;
            }

            snapshotToken = payload.token;
        } catch (error) {
        }
    };

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitFilters();
        });

        filterFieldSelectors.forEach(function (selector) {
            const field = filterForm.querySelector(selector);
            if (!field) return;

            field.addEventListener('change', submitFilters);
        });

        const searchInput = filterForm.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                if (filterSubmitTimeout) {
                    window.clearTimeout(filterSubmitTimeout);
                }

                filterSubmitTimeout = window.setTimeout(function () {
                    submitFilters();
                }, 350);
            });
        }
    }

    if (statusView) {
        statusView.addEventListener('change', function () {
            if (isSyncingFilters) return;

            const targetUrl = buildFilterUrl();
            targetUrl.searchParams.delete('page');
            void loadTicketResults(targetUrl, { history: 'replace' });
        });
    }

    if (revertConfirmCheckbox) {
        revertConfirmCheckbox.addEventListener('change', syncRevertSubmitState);
    }

    if (bulkDeleteConfirmCheckbox) {
        bulkDeleteConfirmCheckbox.addEventListener('change', syncBulkDeleteConfirmState);
    }

    if (revertForm) {
        revertForm.addEventListener('submit', function (event) {
            if (!revertConfirmCheckbox || revertConfirmCheckbox.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    if (editStatusSelect) {
        editStatusSelect.addEventListener('change', syncEditCloseReasonVisibility);
    }

    pageRoot.addEventListener('click', function (event) {
        const clearLink = event.target.closest('[data-admin-ticket-clear]');
        if (clearLink) {
            event.preventDefault();
            void loadTicketResults(clearLink.href, { history: 'replace' });
            return;
        }

        const selectAllCheckbox = event.target.closest('#select-all-tickets');
        if (selectAllCheckbox) {
            getRowCheckboxes().forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            syncBulkSelection();
            return;
        }

        const ticketCheckbox = event.target.closest('.js-ticket-checkbox');
        if (ticketCheckbox) {
            syncBulkSelection();
            return;
        }

        const paginationLink = event.target.closest('[data-admin-ticket-pagination] a');
        if (paginationLink) {
            event.preventDefault();
            void loadTicketResults(paginationLink.href, { history: 'push' });
            return;
        }

        const assignButton = event.target.closest('.js-open-assign-modal');
        if (assignButton) {
            const ticketId = assignButton.dataset.ticketId;
            if (!ticketId || !assignForm) return;

            assignForm.action = assignRouteTemplate.replace('__TICKET__', ticketId);
            if (assignTicketText) {
                assignTicketText.textContent = 'Ticket #' + (assignButton.dataset.ticketNumber || '');
            }
            setMultiSelectValues(assignAssigneeSelect, parseAssignedIds(assignButton.dataset.assignedTo || '[]'));
            if (assignModalController) assignModalController.open();

            return;
        }

        const editButton = event.target.closest('.js-open-edit-modal');
        if (editButton) {
            populateEditModal(editButton);
            if (editModalController) editModalController.open();

            return;
        }

        const revertButton = event.target.closest('.js-open-revert-modal');
        if (revertButton) {
            const ticketId = revertButton.dataset.ticketId;
            if (!ticketId || !revertForm) return;

            revertForm.action = statusRouteTemplate.replace('__TICKET__', ticketId);
            if (revertTicketText) {
                revertTicketText.textContent = 'Ticket #' + (revertButton.dataset.ticketNumber || '') + ' will be reverted to In Progress.';
            }
            if (revertConfirmCheckbox) {
                revertConfirmCheckbox.checked = false;
            }
            syncRevertSubmitState();
            if (revertModalController) revertModalController.open();
        }
    });

    if (editDeleteButton) {
        editDeleteButton.addEventListener('click', function () {
            const ticketId = editDeleteButton.dataset.ticketId;
            if (!ticketId || !deleteForm) return;

            deleteForm.action = deleteRouteTemplate.replace('__TICKET__', ticketId);
            if (deleteTicketText) {
                deleteTicketText.textContent = 'Ticket #' + (editDeleteButton.dataset.ticketNumber || '');
            }
            if (editModalController) editModalController.close();
            if (deleteModalController) deleteModalController.open();
        });
    }

    if (bulkDeleteButton) {
        bulkDeleteButton.addEventListener('click', function () {
            if (selectedTicketIds().length === 0) {
                return;
            }

            allowBulkDeleteSubmit = false;
            if (bulkDeleteConfirmCheckbox) {
                bulkDeleteConfirmCheckbox.checked = false;
            }
            syncBulkDeleteConfirmState();

            if (bulkDeleteConfirmModalController) {
                bulkDeleteConfirmModalController.open();
                return;
            }
        });
    }

    if (bulkDeleteConfirmSubmit && bulkDeleteForm) {
        bulkDeleteConfirmSubmit.addEventListener('click', function () {
            if (!bulkDeleteConfirmCheckbox || !bulkDeleteConfirmCheckbox.checked) {
                return;
            }

            allowBulkDeleteSubmit = true;
            if (bulkDeleteConfirmModalController) {
                bulkDeleteConfirmModalController.close();
            }
            bulkDeleteForm.requestSubmit();
        });
    }

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function (event) {
            const selectedIds = selectedTicketIds();
            if (selectedIds.length === 0) {
                event.preventDefault();
                return;
            }

            if (!allowBulkDeleteSubmit) {
                event.preventDefault();
                return;
            }

            allowBulkDeleteSubmit = false;

            if (bulkSelectedIds) {
                bulkSelectedIds.innerHTML = '';
                selectedIds.forEach(function (id) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selected_ids[]';
                    hiddenInput.value = id;
                    bulkSelectedIds.appendChild(hiddenInput);
                });
            }
        });
    }

    window.addEventListener('popstate', function () {
        syncFilterFormFromUrl(normalizeResultsUrl(window.location.href));
        void loadTicketResults(window.location.href, { history: 'none' });
    });

    updateReturnPathInputs(relativePathForUrl(normalizeResultsUrl(window.location.href)));
    syncFilterFormFromUrl(normalizeResultsUrl(window.location.href));
    syncEditCloseReasonVisibility();
    syncRevertSubmitState();
    syncBulkDeleteConfirmState();
    syncBulkSelection();

    if (snapshotToken) {
        window.setInterval(pollTicketListSnapshot, 10000);
    }
};

bootPage(initAdminTicketsIndexPage);
