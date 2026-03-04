const initAdminReportsPage = () => {
    const pageRoot = document.querySelector('[data-admin-reports-page]');
    if (!pageRoot) return;
    if (!window.ModalKit) return;

    const modal = document.getElementById('volume-chart-modal');
    const modalTitle = document.getElementById('volume-chart-modal-title');
    const modalContent = document.getElementById('volume-chart-modal-content');
    const openButtons = document.querySelectorAll('.js-open-volume-chart');

    if (!modal || !modalTitle || !modalContent || openButtons.length === 0) {
        return;
    }

    // Keep modal fixed to viewport even if the page container is transformed.
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const sourceChartHeight = 192; // h-48
    const enlargedChartHeight = 416; // h-[26rem]
    const barScale = enlargedChartHeight / sourceChartHeight;
    const clearModalContent = function () {
        modalContent.innerHTML = '';
    };

    const modalController = window.ModalKit.bind(modal, {
        onClose: clearModalContent,
    });

    if (!modalController) return;

    const tuneModalChartLayout = function (clone) {
        const chartArea = clone.querySelector('.h-48');
        if (chartArea) {
            chartArea.classList.remove('h-48', 'overflow-x-auto');
            chartArea.classList.add('h-[26rem]', 'overflow-hidden');
        }

        const pieCharts = clone.querySelectorAll('.js-total-pie-chart');
        pieCharts.forEach(function (pie) {
            pie.classList.remove('h-40', 'w-40', 'h-44', 'w-44');
            pie.classList.add('h-72', 'w-72');
        });

        const charts = clone.querySelectorAll('.js-volume-bars');
        charts.forEach(function (chart) {
            chart.classList.remove('min-w-[760px]', 'min-w-[720px]', 'pb-6');
            chart.classList.add('w-full', 'min-w-0', 'justify-between', 'pb-10');

            const groups = chart.querySelectorAll('.group');
            groups.forEach(function (group) {
                group.classList.remove('min-w-[16px]', 'min-w-[34px]');
                group.classList.add('flex-1', 'min-w-0');
            });

            const bars = chart.querySelectorAll('.js-volume-bar');
            bars.forEach(function (bar) {
                const originalHeight = Number.parseFloat(bar.style.height || '0');
                if (!Number.isFinite(originalHeight) || originalHeight <= 0) {
                    return;
                }
                const scaledHeight = Math.round(originalHeight * barScale);
                bar.style.height = `${Math.min(340, Math.max(8, scaledHeight))}px`;
            });

            const labels = chart.querySelectorAll('span.text-\\[10px\\]');
            labels.forEach(function (label) {
                label.classList.add('text-xs');
            });
        });
    };

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const sourceId = button.dataset.chartSource || '';
            const source = document.getElementById(sourceId);
            if (!source) {
                return;
            }

            modalTitle.textContent = button.dataset.chartTitle || 'Volume Chart';
            clearModalContent();

            const clone = source.cloneNode(true);
            clone.removeAttribute('id');
            tuneModalChartLayout(clone);

            modalContent.appendChild(clone);
            modalController.open();
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminReportsPage, { once: true });
} else {
    initAdminReportsPage();
}
