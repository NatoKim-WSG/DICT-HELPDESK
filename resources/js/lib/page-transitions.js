export const registerPageTransitions = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (prefersReducedMotion.matches) return;

    const shouldSkipLink = (link, event) => {
        if (!link || event.defaultPrevented) return true;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return true;
        if (link.target && link.target !== '_self') return true;
        if (link.classList.contains('js-attachment-link') || link.dataset.fileUrl !== undefined) return true;
        if (link.hasAttribute('download') || link.hasAttribute('onclick') || link.dataset.noPageTransition !== undefined) return true;
        if (!link.href) return true;

        const rawHref = link.getAttribute('href') || '';
        if (rawHref.startsWith('#') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:') || rawHref.startsWith('javascript:')) {
            return true;
        }

        const currentUrl = new URL(window.location.href);
        const destinationUrl = new URL(link.href, window.location.href);
        if (destinationUrl.origin !== currentUrl.origin) return true;
        if (destinationUrl.pathname === currentUrl.pathname && destinationUrl.search === currentUrl.search && destinationUrl.hash !== currentUrl.hash) {
            return true;
        }

        return false;
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (shouldSkipLink(link, event)) return;

        event.preventDefault();
        document.documentElement.classList.add('is-page-transitioning');
        window.setTimeout(() => {
            window.location.assign(link.href);
        }, 90);
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
            return;
        }

        document.documentElement.classList.remove('is-page-transitioning');
    });
};
