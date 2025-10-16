import { useCallback, useEffect, useState } from 'react';
import { route } from 'ziggy-js';

interface RouteInfo {
    path: string;
    isActive: boolean;
}

interface Routes {
    games: RouteInfo;
    lists: RouteInfo;
    ratings: RouteInfo;
    news: RouteInfo;
}

// Helper function to extract pathname from a URL or return as-is if already a path
function getPathname(urlOrPath: string): string {
    try {
        // If it's a full URL, extract the pathname
        if (urlOrPath.startsWith('http://') || urlOrPath.startsWith('https://')) {
            return new URL(urlOrPath).pathname;
        }
        // Otherwise, it's already a path
        return urlOrPath;
    } catch {
        // If URL parsing fails, return as-is
        return urlOrPath;
    }
}

export function useStableRoutes() {
    const [routes, setRoutes] = useState<Routes>(() => {
        // SSR-safe: only call route() in browser environment
        if (typeof window === 'undefined') {
            return {
                games: { path: '/games', isActive: false },
                lists: { path: '/lists/public', isActive: false },
                ratings: { path: '/ratings', isActive: false },
                news: { path: '/news', isActive: false },
            };
        }

        // Calculate initial active state based on current path
        const currentPath = window.location.pathname;
        const gamesPath = getPathname(route('games.index'));
        const listsPath = getPathname(route('lists.public'));
        const ratingsPath = getPathname(route('ratings.index'));
        const newsPath = getPathname(route('news.index'));

        return {
            games: {
                path: gamesPath,
                isActive: currentPath === gamesPath || currentPath.startsWith(gamesPath + '/')
            },
            lists: {
                path: listsPath,
                isActive: currentPath === listsPath || currentPath.startsWith(listsPath + '/')
            },
            ratings: {
                path: ratingsPath,
                isActive: currentPath === ratingsPath || currentPath.startsWith(ratingsPath + '/')
            },
            news: {
                path: newsPath,
                isActive: currentPath === newsPath || currentPath.startsWith(newsPath + '/')
            },
        };
    });

    const updateRoutes = useCallback(() => {
        if (typeof window === 'undefined') return;

        const currentPath = window.location.pathname;

        setRoutes(prevRoutes => {
            const gamesPath = getPathname(route('games.index'));
            const listsPath = getPathname(route('lists.public'));
            const ratingsPath = getPathname(route('ratings.index'));
            const newsPath = getPathname(route('news.index'));

            const newGamesActive = currentPath === gamesPath || currentPath.startsWith(gamesPath + '/');
            const newListsActive = currentPath === listsPath || currentPath.startsWith(listsPath + '/');
            const newRatingsActive = currentPath === ratingsPath || currentPath.startsWith(ratingsPath + '/');
            const newNewsActive = currentPath === newsPath || currentPath.startsWith(newsPath + '/');

            // Only update if something actually changed
            if (prevRoutes.games.isActive === newGamesActive &&
                prevRoutes.lists.isActive === newListsActive &&
                prevRoutes.ratings.isActive === newRatingsActive &&
                prevRoutes.news.isActive === newNewsActive) {
                return prevRoutes;
            }

            return {
                games: { path: gamesPath, isActive: newGamesActive },
                lists: { path: listsPath, isActive: newListsActive },
                ratings: { path: ratingsPath, isActive: newRatingsActive },
                news: { path: newsPath, isActive: newNewsActive },
            };
        });
    }, []);

    useEffect(() => {
        updateRoutes();

        const handleInertiaComplete = () => {
            updateRoutes();
        };

        const handlePopState = () => {
            updateRoutes();
        };

        document.addEventListener('inertia:complete', handleInertiaComplete);
        window.addEventListener('popstate', handlePopState);

        return () => {
            document.removeEventListener('inertia:complete', handleInertiaComplete);
            window.removeEventListener('popstate', handlePopState);
        };
    }, [updateRoutes]);

    return routes;
}