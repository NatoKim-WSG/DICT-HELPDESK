import { bootPage } from './shared/boot-page';
import { syncDepartmentByRole } from './shared/user-role';

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

    const syncClientNotesByRole = () => {
        if (!clientNotesWrap || !clientNotesField) return;

        const isClient = roleSelect.value === 'client';
        clientNotesWrap.classList.toggle('hidden', !isClient);
        clientNotesField.disabled = !isClient;
    };

    roleSelect.addEventListener('change', () => {
        syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, hint });
        syncClientNotesByRole();
    });

    syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, hint });
    syncClientNotesByRole();
};

bootPage(initAdminUsersCreatePage);
