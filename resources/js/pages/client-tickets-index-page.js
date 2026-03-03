const initClientTicketsIndexPage = () => {
    const pageRoot = document.querySelector('[data-client-tickets-index-page]');
    if (!pageRoot) return;

    const initialSnapshotToken = pageRoot.dataset.snapshotToken || '';
    if (!initialSnapshotToken) return;

    const routeBase = pageRoot.dataset.routeBase || window.location.pathname;
    let snapshotToken = initialSnapshotToken;
    let staleDetected = false;

    const showRefreshPrompt = () => {
        if (document.querySelector('.js-live-refresh-prompt')) return;

        const prompt = document.createElement('div');
        prompt.className = 'js-live-refresh-prompt mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800';
        prompt.innerHTML = 'New ticket updates are available. <button type="button" class="ml-2 font-semibold underline js-live-refresh-now">Refresh now</button>';
        pageRoot.prepend(prompt);

        const refreshButton = prompt.querySelector('.js-live-refresh-now');
        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                window.location.reload();
            });
        }
    };

    const pollTicketListSnapshot = async () => {
        if (document.hidden || staleDetected) return;

        const params = new URLSearchParams(window.location.search);
        params.set('heartbeat', '1');

        try {
            const response = await fetch(routeBase + '?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== snapshotToken) {
                staleDetected = true;
                showRefreshPrompt();
                return;
            }

            snapshotToken = payload.token;
        } catch (error) {
        }
    };

    window.setInterval(pollTicketListSnapshot, 30000);
};

const bootClientTicketsIndexPage = () => {
    window.setTimeout(initClientTicketsIndexPage, 0);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootClientTicketsIndexPage, { once: true });
} else {
    bootClientTicketsIndexPage();
}
