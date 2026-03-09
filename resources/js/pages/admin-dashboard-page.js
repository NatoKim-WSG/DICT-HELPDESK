import { bootPage } from './shared/boot-page';

const initAdminDashboardPage = () => {
    const pageRoot = document.querySelector('[data-admin-dashboard-page]');
    if (!pageRoot) return;

    const initialToken = pageRoot.dataset.snapshotToken || '';
    if (!initialToken) return;

    const heartbeatUrl = new URL(pageRoot.dataset.heartbeatUrl || window.location.href, window.location.origin);
    heartbeatUrl.searchParams.set('heartbeat', '1');
    let activeToken = initialToken;
    let checking = false;

    const pollSnapshot = async function () {
        if (checking || document.hidden) return;
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
                window.location.reload();
                return;
            }

            activeToken = payload.token;
        } catch (error) {
        } finally {
            checking = false;
        }
    };

    window.setInterval(pollSnapshot, 10000);
};

bootPage(initAdminDashboardPage);

