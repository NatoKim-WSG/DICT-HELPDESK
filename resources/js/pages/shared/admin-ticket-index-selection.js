export const createAdminTicketBulkSelection = ({ pageRoot, bulkDeleteButton, bulkSelectedIds }) => {
    const getRowCheckboxes = () => Array.from(pageRoot.querySelectorAll('.js-ticket-checkbox'));
    const getSelectAllCheckbox = () => pageRoot.querySelector('#select-all-tickets');

    const selectedTicketIds = () => {
        return getRowCheckboxes()
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);
    };

    const syncBulkSelection = () => {
        const selectedCount = selectedTicketIds().length;
        const selectAllCheckbox = getSelectAllCheckbox();
        const rowCheckboxes = getRowCheckboxes();

        if (bulkDeleteButton) {
            bulkDeleteButton.disabled = selectedCount === 0;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = rowCheckboxes.length > 0 && rowCheckboxes.every((checkbox) => checkbox.checked);
            selectAllCheckbox.indeterminate = selectedCount > 0 && !selectAllCheckbox.checked;
        }
    };

    const resetBulkSelection = () => {
        const selectAllCheckbox = getSelectAllCheckbox();
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }

        getRowCheckboxes().forEach((checkbox) => {
            checkbox.checked = false;
        });

        if (bulkSelectedIds) {
            bulkSelectedIds.innerHTML = '';
        }

        syncBulkSelection();
    };

    const populateSelectedIds = (selectedIds) => {
        if (!bulkSelectedIds) {
            return;
        }

        bulkSelectedIds.innerHTML = '';
        selectedIds.forEach((id) => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_ids[]';
            hiddenInput.value = id;
            bulkSelectedIds.appendChild(hiddenInput);
        });
    };

    return {
        getRowCheckboxes,
        getSelectAllCheckbox,
        selectedTicketIds,
        syncBulkSelection,
        resetBulkSelection,
        populateSelectedIds,
    };
};
