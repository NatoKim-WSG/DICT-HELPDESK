import { bootPage } from './shared/boot-page';

const initAdminTicketCreatePage = () => {
    const pageRoot = document.querySelector('[data-admin-ticket-create-page]');
    if (!pageRoot) return;

    const form = document.getElementById('admin-ticket-create-form');
    const clientSelect = pageRoot.querySelector('[data-client-account-select]');
    const nameInput = document.getElementById('name');
    const contactInput = document.getElementById('contact_number');
    const emailInput = document.getElementById('email');
    if (!form) return;

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

    const syncSelectedClientDetails = ({ force = false } = {}) => {
        if (!(clientSelect instanceof HTMLSelectElement)) return;

        const option = clientSelect.options[clientSelect.selectedIndex];
        if (!option || option.value === '') return;

        const fieldPairs = [
            [nameInput, option.dataset.name || ''],
            [contactInput, option.dataset.phone || ''],
            [emailInput, option.dataset.email || ''],
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

        syncSelectedClientDetails();
    }

    form.addEventListener('submit', () => {
        fields.forEach(normalizeField);
    });
};

bootPage(initAdminTicketCreatePage);
