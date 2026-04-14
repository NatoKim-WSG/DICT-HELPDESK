export const createReplyPolling = ({
    repliesUrl,
    getCursor,
    setCursor,
    syncReplies,
    queueSeenSync,
    intervalMs,
}) => {
    let isPolling = false;
    let timeoutId = 0;

    const stop = () => {
        if (timeoutId) {
            window.clearTimeout(timeoutId);
            timeoutId = 0;
        }
    };

    const schedule = () => {
        stop();
        if (!repliesUrl || document.visibilityState !== 'visible') return;

        timeoutId = window.setTimeout(() => {
            poll();
        }, intervalMs);
    };

    const poll = async () => {
        if (!repliesUrl || isPolling || document.visibilityState !== 'visible') return;
        isPolling = true;

        try {
            const response = await fetch(repliesUrl(getCursor()), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            if (typeof data.cursor === 'string' && data.cursor !== '') {
                setCursor(data.cursor);
            }

            const replies = Array.isArray(data && data.replies) ? data.replies : [];
            syncReplies(replies);
            queueSeenSync();
        } catch (error) {
        } finally {
            isPolling = false;
            schedule();
        }
    };

    const bind = () => {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                queueSeenSync();
                poll();
                return;
            }

            stop();
        });

        window.addEventListener('focus', () => {
            queueSeenSync();
            if (document.visibilityState === 'visible') {
                poll();
            }
        });
    };

    return {
        bind,
        poll,
        schedule,
        stop,
    };
};
