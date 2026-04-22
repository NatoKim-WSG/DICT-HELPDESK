import { bootPage } from './shared/boot-page';

const initAdminTicketCreatePage = () => {
    const pageRoot = document.querySelector('[data-admin-ticket-create-page]');
    if (!pageRoot) return;

    const form = document.getElementById('admin-ticket-create-form');
    const clientSelect = pageRoot.querySelector('[data-client-account-select]');
    const ticketTypeSelect = document.getElementById('ticket_type');
    const requesterAccountLabel = pageRoot.querySelector('[data-requester-account-label]');
    const requesterAccountHelp = pageRoot.querySelector('[data-requester-account-help]');
    const requesterSnapshotNote = pageRoot.querySelector('[data-requester-snapshot-note]');
    const descriptionCopy = pageRoot.querySelector('[data-ticket-create-description]');
    const nameInput = document.getElementById('name');
    const contactInput = document.getElementById('contact_number');
    const emailInput = document.getElementById('email');
    if (!form) return;

    const currentSupportUserId = String(pageRoot.dataset.currentSupportUserId || '').trim();
    const accountOptions = clientSelect instanceof HTMLSelectElement
        ? Array.from(clientSelect.querySelectorAll('option'))
            .filter((option) => option.value !== '')
            .map((option) => ({
                value: option.value,
                label: option.textContent || '',
                name: option.dataset.name || '',
                email: option.dataset.email || '',
                phone: option.dataset.phone || '',
                group: option.dataset.accountGroup || 'client',
                role: option.dataset.role || '',
            }))
        : [];

    const fields = [
        document.getElementById('province'),
        document.getElementById('municipality'),
        document.getElementById('subject'),
    ].filter(Boolean);

    const normalizeLeadingUppercase = (value) => {
        const trimmed = String(value || '').trim();
        if (trimmed.length === 0) return '';
        return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
    };

    const normalizeField = (field) => {
        field.value = normalizeLeadingUppercase(field.value);
    };

    const activeRequesterGroup = () => (
        ticketTypeSelect instanceof HTMLSelectElement && ticketTypeSelect.value === 'internal'
            ? 'support'
            : 'client'
    );

    const selectedAccountRecord = () => {
        if (!(clientSelect instanceof HTMLSelectElement)) return null;
        const selectedValue = String(clientSelect.value || '').trim();
        return accountOptions.find((option) => option.value === selectedValue) || null;
    };

    const updateRequesterCopy = () => {
        const isInternal = activeRequesterGroup() === 'support';

        if (requesterAccountLabel) {
            requesterAccountLabel.innerHTML = `${isInternal ? 'Support Staff Account' : 'Client Account'} <span class="text-red-600">*</span>`;
        }

        if (requesterAccountHelp) {
            requesterAccountHelp.textContent = isInternal
                ? 'Internal tickets use support staff accounts and default to your account for faster staff-to-staff requests.'
                : 'External tickets use client accounts.';
        }

        if (requesterSnapshotNote) {
            requesterSnapshotNote.textContent = isInternal
                ? 'This internal ticket will be linked to the selected support staff account, while the contact details below capture the request snapshot used for follow-up.'
                : 'This ticket will be linked to the selected client account, while the contact details below capture the request snapshot used for support follow-up.';
        }

        if (descriptionCopy) {
            descriptionCopy.textContent = isInternal
                ? 'Use this form to log an internal support request on behalf of a support staff requester.'
                : 'Use this form when a client contacts support directly and the ticket needs to be logged by a support user.';
        }

        if (nameInput) {
            nameInput.placeholder = isInternal ? 'Support staff full name' : 'Client full name';
        }

        if (emailInput) {
            emailInput.placeholder = isInternal ? 'staff@example.com' : 'client@example.com';
        }
    };

    const rebuildRequesterOptions = ({ forceSelection = false } = {}) => {
        if (!(clientSelect instanceof HTMLSelectElement)) return;

        const isInternal = activeRequesterGroup() === 'support';
        const group = isInternal ? 'support' : 'client';
        const previousValue = String(clientSelect.value || '').trim();
        const availableOptions = accountOptions.filter((option) => option.group === group);
        let nextValue = previousValue;

        if (!availableOptions.some((option) => option.value === nextValue) || forceSelection) {
            nextValue = '';
        }

        if (nextValue === '' && isInternal && currentSupportUserId !== '') {
            nextValue = availableOptions.some((option) => option.value === currentSupportUserId)
                ? currentSupportUserId
                : '';
        }

        if (nextValue === '' && availableOptions.length === 1) {
            nextValue = availableOptions[0].value;
        }

        clientSelect.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = isInternal
            ? 'Select a support staff account'
            : 'Select a client account';
        clientSelect.appendChild(placeholderOption);

        availableOptions.forEach((account) => {
            const option = document.createElement('option');
            option.value = account.value;
            option.textContent = account.label;
            option.dataset.name = account.name;
            option.dataset.email = account.email;
            option.dataset.phone = account.phone;
            option.dataset.accountGroup = account.group;
            option.dataset.role = account.role;
            if (account.value === nextValue) {
                option.selected = true;
            }
            clientSelect.appendChild(option);
        });

        clientSelect.value = nextValue;
    };

    const syncSelectedClientDetails = ({ force = false } = {}) => {
        if (!(clientSelect instanceof HTMLSelectElement)) return;

        const option = selectedAccountRecord();
        if (!option || option.value === '') return;

        const fieldPairs = [
            [nameInput, option.name || ''],
            [contactInput, option.phone || ''],
            [emailInput, option.email || ''],
        ];

        fieldPairs.forEach(([field, value]) => {
            if (!field) return;
            if (!force && String(field.value || '').trim() !== '') return;
            field.value = value;
        });
    };

    fields.forEach((field) => {
        field.addEventListener('blur', () => {
            normalizeField(field);
        });
    });

    if (clientSelect instanceof HTMLSelectElement) {
        clientSelect.addEventListener('change', () => {
            syncSelectedClientDetails({ force: true });
        });

        rebuildRequesterOptions();
        updateRequesterCopy();
        syncSelectedClientDetails();
    }

    if (ticketTypeSelect instanceof HTMLSelectElement) {
        ticketTypeSelect.addEventListener('change', () => {
            rebuildRequesterOptions({ forceSelection: true });
            updateRequesterCopy();
            syncSelectedClientDetails({ force: true });
        });
    }

    form.addEventListener('submit', () => {
        fields.forEach(normalizeField);
    });
};

bootPage(initAdminTicketCreatePage);
