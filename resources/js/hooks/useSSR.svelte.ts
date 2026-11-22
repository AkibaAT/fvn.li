export function useDarkMode() {
    let isDark = $state(false);

    $effect(() => {
        if (typeof document === 'undefined' || typeof window === 'undefined') {
            return;
        }

        const updateDarkMode = () => {
            if (document.documentElement.classList.contains('dark')) {
                isDark = true;
                return;
            }

            const cookieMatch = document.cookie.match(/(?:^|; )appearance=([^;]*)/);
            const stored = cookieMatch ? decodeURIComponent(cookieMatch[1]) : localStorage.getItem('appearance');
            isDark = stored === 'dark' || ((stored === null || stored === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        };

        updateDarkMode();

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', updateDarkMode);

        let observer: MutationObserver | null = null;
        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(updateDarkMode);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        }

        return () => {
            mediaQuery.removeEventListener('change', updateDarkMode);
            observer?.disconnect();
        };
    });

    return {
        get value() {
            return isDark;
        },
    };
}
