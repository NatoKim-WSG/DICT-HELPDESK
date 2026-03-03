const initAdminUsersCreatePage = () => {
    const pageRoot = document.querySelector('[data-admin-users-create-page]');
    if (!pageRoot) return;

    const roleSelect = document.getElementById('role');
    const departmentSelect = document.getElementById('department');
    const departmentHidden = document.getElementById('department_hidden');
    const hint = document.getElementById('department-role-hint');
    const clientNotesWrap = document.getElementById('client-notes-wrap');
    const clientNotesField = document.getElementById('client_notes');

    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const syncDepartmentByRole = () => {
        const role = roleSelect.value;
        const isInternal = role === 'shadow'
            || role === 'admin'
            || role === 'super_user'
            || role === 'technical';

        if (isInternal) {
            departmentSelect.value = 'iOne';
            departmentSelect.disabled = true;
            departmentHidden.value = 'iOne';
            departmentHidden.disabled = false;
            if (hint) {
                hint.textContent = 'Internal users are automatically assigned to iOne.';
            }
            return;
        }

        departmentSelect.disabled = false;
        departmentHidden.value = '';
        departmentHidden.disabled = true;
        if (hint) {
            hint.textContent = 'Select the client department.';
        }
    };

    const syncClientNotesByRole = () => {
        if (!clientNotesWrap || !clientNotesField) return;

        const isClient = roleSelect.value === 'client';
        clientNotesWrap.classList.toggle('hidden', !isClient);
        clientNotesField.disabled = !isClient;
    };

    roleSelect.addEventListener('change', () => {
        syncDepartmentByRole();
        syncClientNotesByRole();
    });

    syncDepartmentByRole();
    syncClientNotesByRole();
};

const bootAdminUsersCreatePage = () => {
    window.setTimeout(initAdminUsersCreatePage, 0);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAdminUsersCreatePage, { once: true });
} else {
    bootAdminUsersCreatePage();
}
