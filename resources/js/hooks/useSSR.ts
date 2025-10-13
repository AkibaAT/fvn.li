import { useEffect, useState } from 'react';

/**
 * SSR-safe hook for accessing window object
 */
export function useWindow() {
    const [windowObj, setWindowObj] = useState<typeof window | null>(null);

    useEffect(() => {
        setWindowObj(window);
    }, []);

    return windowObj;
}

/**
 * SSR-safe hook for accessing document object
 */
export function useDocument() {
    const [documentObj, setDocumentObj] = useState<typeof document | null>(null);

    useEffect(() => {
        setDocumentObj(document);
    }, []);

    return documentObj;
}

/**
 * SSR-safe hook for accessing localStorage
 */
export function useLocalStorage() {
    const [localStorageObj, setLocalStorageObj] = useState<typeof localStorage | null>(null);

    useEffect(() => {
        setLocalStorageObj(localStorage);
    }, []);

    return localStorageObj;
}

/**
 * SSR-safe hook for accessing sessionStorage
 */
export function useSessionStorage() {
    const [sessionStorageObj, setSessionStorageObj] = useState<typeof sessionStorage | null>(null);

    useEffect(() => {
        setSessionStorageObj(sessionStorage);
    }, []);

    return sessionStorageObj;
}

/**
 * SSR-safe hook for accessing navigator object
 */
export function useNavigator() {
    const [navigatorObj, setNavigatorObj] = useState<typeof navigator | null>(null);

    useEffect(() => {
        setNavigatorObj(navigator);
    }, []);

    return navigatorObj;
}

/**
 * SSR-safe hook for checking if component is mounted (client-side)
 */
export function useIsMounted() {
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    return isMounted;
}

/**
 * SSR-safe hook for getting current URL origin
 */
export function useOrigin() {
    const [origin, setOrigin] = useState<string>('');

    useEffect(() => {
        setOrigin(window.location.origin);
    }, []);

    return origin;
}

/**
 * SSR-safe hook for media queries
 */
export function useMediaQuery(query: string) {
    const [matches, setMatches] = useState(false);

    useEffect(() => {
        const mediaQuery = window.matchMedia(query);
        setMatches(mediaQuery.matches);

        const handler = (event: MediaQueryListEvent) => {
            setMatches(event.matches);
        };

        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    }, [query]);

    return matches;
}

/**
 * SSR-safe hook for checking dark mode preference
 */
export function useDarkMode() {
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        const updateDarkMode = () => {
            const stored = localStorage.getItem('appearance');
            setIsDark(
                stored === 'dark' || 
                (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
            );
        };

        updateDarkMode();

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => updateDarkMode();
        
        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    }, []);

    return isDark;
}