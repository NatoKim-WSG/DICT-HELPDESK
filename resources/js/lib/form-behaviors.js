export const registerFormBehaviors = () => {
    const formNodes = document.querySelectorAll('form[data-submit-feedback]');
    formNodes.forEach((form) => {
        form.addEventListener('submit', (event) => {
            const submitter = event.submitter
                || form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitter || submitter.disabled) return;

            requestAnimationFrame(() => {
                if (event.defaultPrevented || submitter.disabled) return;

                const loadingText = submitter.dataset.loadingText || 'Loading...';
                const originalText = submitter.tagName === 'INPUT'
                    ? (submitter.value || '')
                    : (submitter.textContent || '');
                submitter.dataset.originalText = originalText;
                submitter.disabled = true;
                submitter.classList.add('app-submit-busy');

                if (submitter.tagName === 'INPUT') {
                    submitter.value = loadingText;
                } else {
                    submitter.textContent = loadingText;
                }
            });
        });
    });

    document.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
            return;
        }
        if (target.dataset.autoSubmitChange === undefined) return;

        const form = target.form || target.closest('form');
        if (!form) return;

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    });
};
