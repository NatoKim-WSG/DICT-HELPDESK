const INTERNAL_ROLES = new Set(['shadow', 'admin', 'super_user', 'technical']);

export const isInternalRole = (role) => INTERNAL_ROLES.has(String(role || '').trim());

export const syncDepartmentByRole = ({ roleSelect, departmentSelect, departmentHidden, hint, supportDepartment }) => {
    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const internalDepartment = String(supportDepartment || '').trim() || 'iOne';

    if (isInternalRole(roleSelect.value)) {
        departmentSelect.value = internalDepartment;
        departmentSelect.disabled = true;
        departmentHidden.value = internalDepartment;
        departmentHidden.disabled = false;
        if (hint) {
            hint.textContent = `Internal users are automatically assigned to ${internalDepartment}.`;
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
