const initClientTicketCreatePage = () => {
    const pageRoot = document.querySelector('[data-client-ticket-create-page]');
    if (!pageRoot) return;

    const form = document.getElementById('ticket-create-form');
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

    fields.forEach((field) => {
        field.addEventListener('blur', () => {
            normalizeField(field);
        });
    });

    form.addEventListener('submit', () => {
        fields.forEach(normalizeField);
    });
};

const bootClientTicketCreatePage = () => {
    window.setTimeout(initClientTicketCreatePage, 0);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootClientTicketCreatePage, { once: true });
} else {
    bootClientTicketCreatePage();
}
