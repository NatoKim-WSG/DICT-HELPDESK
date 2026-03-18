export const registerThemeGlobals = () => {
    window.AppTheme = (() => {
        const storageKey = 'ione_theme';
        const root = document.documentElement;

        const hasReducedMotionPreference = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const isDark = () => root.classList.contains('theme-dark');

        const persistMode = (isDarkMode) => {
            try {
                window.localStorage.setItem(storageKey, isDarkMode ? 'dark' : 'light');
            } catch (error) {
            }
        };

        const apply = (isDarkMode, options = {}) => {
            const persist = options.persist !== false;
            const shouldAnimate = options.animate !== false && !hasReducedMotionPreference();

            if (shouldAnimate) {
                root.classList.add('theme-switching');
                requestAnimationFrame(() => {
                    root.classList.toggle('theme-dark', isDarkMode);
                    if (persist) persistMode(isDarkMode);
                    window.setTimeout(() => {
                        root.classList.remove('theme-switching');
                    }, 120);
                });

                return isDarkMode;
            }

            root.classList.toggle('theme-dark', isDarkMode);
            root.classList.remove('theme-switching');
            if (persist) persistMode(isDarkMode);

            return isDarkMode;
        };

        const toggle = () => apply(!isDark());

        return { isDark, apply, toggle };
    })();
};

const resolveDarkModeState = () => (
    window.AppTheme
        ? window.AppTheme.isDark()
        : document.documentElement.classList.contains('theme-dark')
);

const buildThemeState = () => ({
    darkMode: false,
    initThemeState() {
        this.darkMode = resolveDarkModeState();
    },
    toggleDarkMode() {
        if (window.AppTheme) {
            this.darkMode = window.AppTheme.toggle();
            return;
        }

        this.darkMode = !this.darkMode;
        document.documentElement.classList.toggle('theme-dark', this.darkMode);
        window.localStorage.setItem('ione_theme', this.darkMode ? 'dark' : 'light');
    },
});

const buildLegalModalState = () => ({
    legalModalOpen: false,
    legalModalTab: 'terms',
    openLegalModal(tab = 'terms') {
        this.legalModalTab = tab;
        this.legalModalOpen = true;
        document.body.classList.add('overflow-hidden');
    },
    closeLegalModal() {
        this.legalModalOpen = false;
        document.body.classList.remove('overflow-hidden');
    },
});

export const registerAlpineStateCollections = (Alpine) => {
    Alpine.data('appShellState', () => ({
        sidebarOpen: false,
        ...buildThemeState(),
        ...buildLegalModalState(),
        init() {
            this.initThemeState();
        },
    }));

    Alpine.data('loginPageState', () => ({
        ...buildThemeState(),
        ...buildLegalModalState(),
        init() {
            this.initThemeState();
        },
    }));

    Alpine.data('publicLegalPageState', () => ({
        ...buildThemeState(),
        init() {
            this.initThemeState();
        },
    }));

    Alpine.data('headerNotificationDropdown', () => ({
        notificationOpen: false,
    }));

    Alpine.data('profileMenuDropdown', () => ({
        open: false,
    }));
};
