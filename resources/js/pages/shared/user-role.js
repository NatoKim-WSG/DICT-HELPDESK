const INTERNAL_ROLES = new Set(['shadow', 'admin', 'super_user', 'technical']);

export const isInternalRole = (role) => INTERNAL_ROLES.has(String(role || '').trim());

export const syncDepartmentByRole = ({ roleSelect, departmentSelect, departmentHidden, hint }) => {
    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    if (isInternalRole(roleSelect.value)) {
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
