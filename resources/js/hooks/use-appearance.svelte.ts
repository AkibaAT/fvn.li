export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getCookie = (name: string) => {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith(prefix));

    return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
};

const isAppearance = (value: string | null): value is Appearance => value === 'light' || value === 'dark' || value === 'system';

const readAppearance = (): Appearance => {
    const cookieAppearance = getCookie('appearance');
    if (isAppearance(cookieAppearance)) {
        return cookieAppearance;
    }

    const storedAppearance = typeof localStorage !== 'undefined' ? localStorage.getItem('appearance') : null;
    if (isAppearance(storedAppearance)) {
        setCookie('appearance', storedAppearance);

        return storedAppearance;
    }

    // An unreadable cookie is rewritten so the server stops seeing the stale value.
    setCookie('appearance', 'system');

    return 'system';
};

const applyTheme = (appearance: Appearance) => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', isDark);
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = () => {
    applyTheme(readAppearance());
};

export function initializeAppearance() {
    applyTheme(readAppearance());

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    let appearance = $state<Appearance>(readAppearance());

    const updateAppearance = (mode: Appearance) => {
        appearance = mode;

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('appearance', mode);
        }

        setCookie('appearance', mode);

        applyTheme(mode);
    };

    $effect(() => {
        applyTheme(appearance);

        return () => mediaQuery()?.removeEventListener('change', handleSystemThemeChange);
    });

    return {
        get appearance() {
            return appearance;
        },
        updateAppearance,
    };
}
