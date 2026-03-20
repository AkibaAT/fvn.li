/**
 * SSR-safe hook for accessing window object
 */
export function useWindow() {
    const windowObj = $derived(window);

    return {
        get value() {
            return windowObj;
        },
    };
}

/**
 * SSR-safe hook for accessing document object
 */
export function useDocument() {
    const documentObj = $derived(document);

    return {
        get value() {
            return documentObj;
        },
    };
}

/**
 * SSR-safe hook for accessing localStorage
 */
export function useLocalStorage() {
    const localStorageObj = $derived(localStorage);

    return {
        get value() {
            return localStorageObj;
        },
    };
}

/**
 * SSR-safe hook for accessing sessionStorage
 */
export function useSessionStorage() {
    const sessionStorageObj = $derived(sessionStorage);

    return {
        get value() {
            return sessionStorageObj;
        },
    };
}

/**
 * SSR-safe hook for accessing navigator object
 */
export function useNavigator() {
    const navigatorObj = $derived(navigator);

    return {
        get value() {
            return navigatorObj;
        },
    };
}

/**
 * SSR-safe hook for checking if component is mounted (client-side)
 */
export function useIsMounted() {
    const isMounted = $derived(true);

    return {
        get value() {
            return isMounted;
        },
    };
}

/**
 * SSR-safe hook for getting current URL origin
 */
export function useOrigin() {
    const origin = $derived(typeof window !== 'undefined' ? window.location.origin : '');

    return {
        get value() {
            return origin;
        },
    };
}

/**
 * SSR-safe hook for media queries
 */
export function useMediaQuery(query: string) {
    let matches = $state(false);

    $effect(() => {
        const mediaQuery = window.matchMedia(query);
        matches = mediaQuery.matches;

        const handler = (event: MediaQueryListEvent) => {
            matches = event.matches;
        };

        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    });

    return {
        get value() {
            return matches;
        },
    };
}

/**
 * SSR-safe hook for checking dark mode preference
 */
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
