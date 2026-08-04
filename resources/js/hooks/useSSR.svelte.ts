export function useDarkMode() {
    let isDark = $state(false);

    $effect(() => {
        const updateDarkMode = () => {
            const stored = localStorage.getItem('appearance');
            isDark = stored === 'dark' || (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        };

        updateDarkMode();

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => updateDarkMode();

        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    });

    return {
        get value() {
            return isDark;
        },
    };
}
