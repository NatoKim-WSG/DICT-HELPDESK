import { bootPage } from './shared/boot-page';

const initClientDashboardPage = () => {
    const pageRoot = document.querySelector('[data-client-dashboard-page]');
    if (!pageRoot) return;

    const initialToken = pageRoot.dataset.snapshotToken || '';
    if (!initialToken) return;

    const heartbeatUrl = new URL(pageRoot.dataset.heartbeatUrl || window.location.href, window.location.origin);
    heartbeatUrl.searchParams.set('heartbeat', '1');
    let activeToken = initialToken;
    let checking = false;
    let staleDetected = false;

    const showRefreshPrompt = function () {
        if (document.querySelector('.js-live-refresh-prompt')) return;

        const prompt = document.createElement('div');
        prompt.className = 'js-live-refresh-prompt mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800';
        prompt.innerHTML = 'New ticket updates are available. <button type="button" class="ml-2 font-semibold underline js-live-refresh-now">Refresh now</button>';
        pageRoot.prepend(prompt);

        const refreshButton = prompt.querySelector('.js-live-refresh-now');
        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                window.location.reload();
            });
        }
    };

    const pollSnapshot = async function () {
        if (checking || document.hidden || staleDetected) return;
        checking = true;

        try {
            const response = await fetch(heartbeatUrl.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const payload = await response.json();
            if (!payload || !payload.token) return;

            if (payload.token !== activeToken) {
                staleDetected = true;
                showRefreshPrompt();
                return;
            }

            activeToken = payload.token;
        } catch (error) {
        } finally {
            checking = false;
        }
    };

    window.setInterval(pollSnapshot, 30000);
};

bootPage(initClientDashboardPage);

