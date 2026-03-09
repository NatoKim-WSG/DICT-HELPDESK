import { bootPage } from './shared/boot-page';

const initAdminTicketsIndexPage = () => {
    const pageRoot = document.querySelector('[data-admin-tickets-index-page]');
    if (!pageRoot) return;

    const statusView = document.getElementById('admin-status-view');
    const selectAll = document.getElementById('select-all-tickets');
    const rowCheckboxes = Array.from(document.querySelectorAll('.js-ticket-checkbox'));
    const routeBase = pageRoot.dataset.routeBase || '';
    const initialSnapshotToken = pageRoot.dataset.snapshotToken || '';
    const assignForm = document.getElementById('assign-ticket-form');
    const assignModal = document.getElementById('assign-ticket-modal');
    const assignTicketText = document.getElementById('assign-modal-ticket');
    const assignSelect = document.getElementById('assign-modal-select');
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
    const editDeleteButton = document.getElementById('edit-modal-delete-btn');
    const deleteForm = document.getElementById('delete-ticket-form');
    const deleteModal = document.getElementById('delete-ticket-modal');
    const deleteTicketText = document.getElementById('delete-modal-ticket');
    const bulkDeleteConfirmModal = document.getElementById('bulk-delete-confirm-modal');
    const bulkDeleteConfirmCheckbox = document.getElementById('bulk-delete-confirm-checkbox');
    const bulkDeleteConfirmSubmit = document.getElementById('bulk-delete-confirm-submit');
    const assignRouteTemplate = pageRoot.dataset.assignRouteTemplate || '';
    const statusRouteTemplate = pageRoot.dataset.statusRouteTemplate || '';
    const quickUpdateRouteTemplate = pageRoot.dataset.quickUpdateRouteTemplate || '';
    const deleteRouteTemplate = pageRoot.dataset.deleteRouteTemplate || '';
    const bulkActionForm = document.getElementById('bulk-action-form');
    const bulkActionSelect = document.getElementById('bulk-action-select');
    const bulkAssignWrap = document.getElementById('bulk-assign-wrap');
    const bulkAssignedTo = document.getElementById('bulk-assigned-to');
    const bulkStatusWrap = document.getElementById('bulk-status-wrap');
    const bulkStatus = document.getElementById('bulk-status');
    const bulkPriorityWrap = document.getElementById('bulk-priority-wrap');
    const bulkPriority = document.getElementById('bulk-priority');
    const bulkCloseReason = document.getElementById('bulk-close-reason');
    const bulkSelectedIds = document.getElementById('bulk-selected-ids');
    const bulkSummary = document.getElementById('bulk-selection-summary');
    const bulkSubmitButton = document.getElementById('bulk-action-submit');
    const bulkClearButton = document.getElementById('bulk-clear-selection');
    const bulkDeleteButton = document.getElementById('bulk-delete-submit');
    const bulkActionFeedback = document.getElementById('bulk-action-feedback');
    const mergeConfirmModal = document.getElementById('merge-confirm-modal');
    const mergeConfirmCheckbox = document.getElementById('merge-confirm-checkbox');
    const mergeConfirmSubmit = document.getElementById('merge-confirm-submit');
    let snapshotToken = initialSnapshotToken;

    const selectedTicketIds = function () {
        return rowCheckboxes
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.value; });
    };

    const clearBulkActionFeedback = function () {
        if (!bulkActionFeedback) return;
        bulkActionFeedback.textContent = '';
        bulkActionFeedback.classList.add('hidden');
        bulkActionFeedback.classList.remove('border-amber-200', 'bg-amber-50', 'text-amber-800');
        bulkActionFeedback.classList.remove('border-rose-200', 'bg-rose-50', 'text-rose-700');
        bulkActionFeedback.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
    };

    const showBulkActionFeedback = function (message, tone) {
        if (!bulkActionFeedback) return;
        clearBulkActionFeedback();
        bulkActionFeedback.textContent = message;
        bulkActionFeedback.classList.remove('hidden');

        if (tone === 'error') {
            bulkActionFeedback.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
        } else if (tone === 'success') {
            bulkActionFeedback.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
        } else {
            bulkActionFeedback.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
        }
    };

    const syncBulkSelection = function () {
        const selectedCount = selectedTicketIds().length;
        if (bulkSummary) {
            bulkSummary.textContent = selectedCount + ' selected';
        }
        if (bulkSubmitButton) {
            bulkSubmitButton.disabled = selectedCount === 0;
        }
        if (bulkDeleteButton) {
            bulkDeleteButton.disabled = selectedCount === 0;
        }
    };

    const resetBulkFieldRequirements = function () {
        if (bulkAssignedTo) bulkAssignedTo.required = false;
        if (bulkStatus) bulkStatus.required = false;
        if (bulkPriority) bulkPriority.required = false;
        if (bulkCloseReason) bulkCloseReason.required = false;
    };

    const syncBulkActionFields = function () {
        if (!bulkActionSelect) return;

        const action = bulkActionSelect.value || '';
        if (bulkAssignWrap) bulkAssignWrap.classList.toggle('hidden', action !== 'assign');
        if (bulkStatusWrap) bulkStatusWrap.classList.toggle('hidden', action !== 'status');
        if (bulkPriorityWrap) bulkPriorityWrap.classList.toggle('hidden', action !== 'priority');

        resetBulkFieldRequirements();

        if (action === 'assign' && bulkAssignedTo) {
            bulkAssignedTo.required = true;
        }
        if (action === 'status' && bulkStatus) {
            bulkStatus.required = true;
        }
        if (action === 'priority' && bulkPriority) {
            bulkPriority.required = true;
        }

        const requiresCloseReason = action === 'status' && bulkStatus && bulkStatus.value === 'closed';
        if (bulkCloseReason) {
            bulkCloseReason.classList.toggle('hidden', !requiresCloseReason);
            bulkCloseReason.required = requiresCloseReason;
            if (!requiresCloseReason) {
                bulkCloseReason.value = '';
            }
        }
    };

    if (statusView) {
        statusView.addEventListener('change', function () {
            const params = new URLSearchParams(window.location.search);
            params.set('status', statusView.value);
            params.delete('page');
            window.location.href = routeBase + '?' + params.toString();
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            syncBulkSelection();
        });
    }

    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (!selectAll) return;
            selectAll.checked = rowCheckboxes.length > 0 && rowCheckboxes.every(function (item) { return item.checked; });
            syncBulkSelection();
        });
    });

    const assignModalController = window.ModalKit ? window.ModalKit.bind(assignModal) : null;
    const revertModalController = window.ModalKit && revertModal ? window.ModalKit.bind(revertModal) : null;
    const editModalController = window.ModalKit ? window.ModalKit.bind(editModal) : null;
    const deleteModalController = window.ModalKit && deleteModal ? window.ModalKit.bind(deleteModal) : null;
    const bulkDeleteConfirmModalController = window.ModalKit && bulkDeleteConfirmModal ? window.ModalKit.bind(bulkDeleteConfirmModal) : null;
    const mergeConfirmModalController = window.ModalKit && mergeConfirmModal ? window.ModalKit.bind(mergeConfirmModal) : null;
    let allowBulkDeleteSubmit = false;
    let allowMergeSubmit = false;

    const syncRevertSubmitState = function () {
        if (!revertSubmitButton || !revertConfirmCheckbox) return;
        revertSubmitButton.disabled = !revertConfirmCheckbox.checked;
    };

    if (revertConfirmCheckbox) {
        revertConfirmCheckbox.addEventListener('change', syncRevertSubmitState);
    }

    if (revertForm) {
        revertForm.addEventListener('submit', function (event) {
            if (!revertConfirmCheckbox || revertConfirmCheckbox.checked) {
                return;
            }

            event.preventDefault();
        });
    }

    const syncBulkDeleteConfirmState = function () {
        if (!bulkDeleteConfirmSubmit || !bulkDeleteConfirmCheckbox) return;
        bulkDeleteConfirmSubmit.disabled = !bulkDeleteConfirmCheckbox.checked;
    };

    if (bulkDeleteConfirmCheckbox) {
        bulkDeleteConfirmCheckbox.addEventListener('change', syncBulkDeleteConfirmState);
    }

    if (bulkDeleteConfirmSubmit && bulkActionForm && bulkActionSelect) {
        bulkDeleteConfirmSubmit.addEventListener('click', function () {
            if (!bulkDeleteConfirmCheckbox || !bulkDeleteConfirmCheckbox.checked) {
                return;
            }

            allowBulkDeleteSubmit = true;
            if (bulkDeleteConfirmModalController) {
                bulkDeleteConfirmModalController.close();
            }
            bulkActionSelect.value = 'delete';
            syncBulkActionFields();
            clearBulkActionFeedback();
            bulkActionForm.requestSubmit();
        });
    }

    const syncMergeConfirmState = function () {
        if (!mergeConfirmSubmit || !mergeConfirmCheckbox) return;
        mergeConfirmSubmit.disabled = !mergeConfirmCheckbox.checked;
    };

    if (mergeConfirmCheckbox) {
        mergeConfirmCheckbox.addEventListener('change', syncMergeConfirmState);
    }

    if (mergeConfirmSubmit && bulkActionForm && bulkActionSelect) {
        mergeConfirmSubmit.addEventListener('click', function () {
            if (!mergeConfirmCheckbox || !mergeConfirmCheckbox.checked) {
                return;
            }

            allowMergeSubmit = true;
            if (mergeConfirmModalController) {
                mergeConfirmModalController.close();
            }
            bulkActionSelect.value = 'merge';
            syncBulkActionFields();
            clearBulkActionFeedback();
            bulkActionForm.requestSubmit();
        });
    }

    const syncEditCloseReasonVisibility = function () {
        if (!editStatusSelect || !editCloseReasonWrap || !editCloseReasonInput) return;
        const isClosed = editStatusSelect.value === 'closed';
        editCloseReasonWrap.classList.toggle('hidden', !isClosed);
        editCloseReasonInput.required = isClosed;
        if (!isClosed) {
            editCloseReasonInput.value = '';
        }
    };

    if (editStatusSelect) {
        editStatusSelect.addEventListener('change', syncEditCloseReasonVisibility);
    }

    document.querySelectorAll('.js-open-assign-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const ticketId = button.dataset.ticketId;
            if (!ticketId || !assignForm) return;

            assignForm.action = assignRouteTemplate.replace('__TICKET__', ticketId);
            if (assignTicketText) {
                assignTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
            }
            if (assignSelect) {
                assignSelect.value = button.dataset.assignedTo || '';
            }
            if (assignModalController) assignModalController.open();
        });
    });

    document.querySelectorAll('.js-open-edit-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const ticketId = button.dataset.ticketId;
            if (!ticketId || !editForm) return;

            editForm.action = quickUpdateRouteTemplate.replace('__TICKET__', ticketId);
            if (editTicketText) {
                editTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '');
            }
            if (editAssignedSelect) editAssignedSelect.value = button.dataset.assignedTo || '';
            if (editStatusSelect) editStatusSelect.value = button.dataset.status || 'open';
            if (editCloseReasonInput) editCloseReasonInput.value = '';
            if (editPrioritySelect) editPrioritySelect.value = button.dataset.priority || 'medium';
            if (editStatusSelect) {
                const closeAllowed = button.dataset.canCloseNow === '1';
                const canRevert = button.dataset.canRevert === '1';
                const isClosedTicket = (button.dataset.status || '') === 'closed';
                const closedOption = editStatusSelect.querySelector('option[value="closed"]');
                ['open', 'in_progress', 'pending', 'resolved'].forEach(function (statusValue) {
                    const option = editStatusSelect.querySelector('option[value="' + statusValue + '"]');
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
            if (editModalController) editModalController.open();
        });
    });

    document.querySelectorAll('.js-open-revert-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const ticketId = button.dataset.ticketId;
            if (!ticketId || !revertForm) return;

            revertForm.action = statusRouteTemplate.replace('__TICKET__', ticketId);
            if (revertTicketText) {
                revertTicketText.textContent = 'Ticket #' + (button.dataset.ticketNumber || '') + ' will be reverted to In Progress.';
            }
            if (revertConfirmCheckbox) {
                revertConfirmCheckbox.checked = false;
            }
            syncRevertSubmitState();
            if (revertModalController) revertModalController.open();
        });
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

    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function () {
            clearBulkActionFeedback();
            syncBulkActionFields();
        });
    }

    if (bulkStatus) {
        bulkStatus.addEventListener('change', syncBulkActionFields);
    }

    if (bulkClearButton) {
        bulkClearButton.addEventListener('click', function () {
            if (selectAll) {
                selectAll.checked = false;
            }
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
            syncBulkSelection();
        });
    }

    if (bulkDeleteButton && bulkActionForm && bulkActionSelect) {
        bulkDeleteButton.addEventListener('click', function () {
            if (selectedTicketIds().length === 0) {
                showBulkActionFeedback('Select at least one ticket before deleting.', 'warning');
                return;
            }

            clearBulkActionFeedback();
            bulkActionSelect.value = 'delete';
            syncBulkActionFields();
            bulkActionForm.requestSubmit();
        });
    }

    if (bulkActionForm) {
        bulkActionForm.addEventListener('submit', function (event) {
            const selectedIds = selectedTicketIds();
            if (selectedIds.length === 0) {
                event.preventDefault();
                showBulkActionFeedback('Select at least one ticket before applying a bulk action.', 'warning');
                return;
            }

            clearBulkActionFeedback();

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

            const action = bulkActionSelect ? bulkActionSelect.value : '';
            if (action === 'delete') {
                if (!allowBulkDeleteSubmit) {
                    event.preventDefault();
                    if (bulkDeleteConfirmCheckbox) {
                        bulkDeleteConfirmCheckbox.checked = false;
                    }
                    syncBulkDeleteConfirmState();
                    if (bulkDeleteConfirmModalController) {
                        bulkDeleteConfirmModalController.open();
                    } else {
                        showBulkActionFeedback('Unable to open the delete confirmation modal.', 'error');
                    }
                    return;
                }

                allowBulkDeleteSubmit = false;
            }

            if (action === 'merge') {
                if (!allowMergeSubmit) {
                    event.preventDefault();
                    if (mergeConfirmCheckbox) {
                        mergeConfirmCheckbox.checked = false;
                    }
                    syncMergeConfirmState();
                    if (mergeConfirmModalController) {
                        mergeConfirmModalController.open();
                    } else {
                        showBulkActionFeedback('Unable to open the merge confirmation modal.', 'error');
                    }
                    return;
                }

                allowMergeSubmit = false;
            }
        });
    }

    const hasOpenModal = function () {
        return [assignModal, revertModal, editModal, deleteModal, bulkDeleteConfirmModal, mergeConfirmModal].some(function (modal) {
            return modal && !modal.classList.contains('hidden');
        });
    };

    const pollTicketListSnapshot = async function () {
        if (!snapshotToken || document.hidden || hasOpenModal()) return;

        const params = new URLSearchParams(window.location.search);
        params.set('heartbeat', '1');

        try {
            const response = await fetch(routeBase + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== snapshotToken) {
                window.location.reload();
                return;
            }

            snapshotToken = payload.token;
        } catch (error) {
        }
    };

    if (snapshotToken) {
        window.setInterval(pollTicketListSnapshot, 10000);
    }

    syncEditCloseReasonVisibility();
    syncBulkActionFields();
    syncBulkSelection();
    syncRevertSubmitState();
    syncBulkDeleteConfirmState();
    syncMergeConfirmState();
};

bootPage(initAdminTicketsIndexPage);

