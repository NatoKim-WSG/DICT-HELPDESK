const INTERNAL_ROLES = new Set(['shadow', 'admin', 'super_user', 'technical']);

export const isInternalRole = (role) => INTERNAL_ROLES.has(String(role || '').trim());

export const syncDepartmentByRole = ({ roleSelect, departmentSelect, departmentHidden, hint, supportDepartment }) => {
    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const internalDepartment = String(supportDepartment || '').trim() || 'iOne';

    if (isInternalRole(roleSelect.value)) {
        if (!String(departmentSelect.value || '').trim()) {
            departmentSelect.value = internalDepartment;
        }
        departmentSelect.disabled = false;
        departmentHidden.value = '';
        departmentHidden.disabled = true;
        if (hint) {
            hint.textContent = `Choose the department for this staff account. Default support department is ${internalDepartment}.`;
        }

        return;
    }

    departmentSelect.disabled = false;
    departmentHidden.value = '';
    departmentHidden.disabled = true;
    if (hint) {
        hint.textContent = 'Select the department for this client account.';
    }
};
