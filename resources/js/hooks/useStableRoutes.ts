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
}

export function useStableRoutes() {
    const [routes, setRoutes] = useState<Routes>(() => {
        // SSR-safe: only call route() in browser environment
        if (typeof window === 'undefined') {
            return {
                games: { path: '/games', isActive: false },
                lists: { path: '/lists/public', isActive: false },
                ratings: { path: '/ratings', isActive: false },
            };
        }
        return {
            games: { path: route('games.index'), isActive: false },
            lists: { path: route('lists.public'), isActive: false },
            ratings: { path: route('ratings.index'), isActive: false },
        };
    });

    const updateRoutes = useCallback(() => {
        if (typeof window === 'undefined') return;
        
        const currentPath = window.location.pathname;
        
        setRoutes(prevRoutes => {
            const gamesPath = route('games.index');
            const listsPath = route('lists.public');
            const ratingsPath = route('ratings.index');

            const newGamesActive = currentPath === gamesPath || currentPath.startsWith(gamesPath + '/');
            const newListsActive = currentPath === listsPath || currentPath.startsWith(listsPath + '/');
            const newRatingsActive = currentPath === ratingsPath || currentPath.startsWith(ratingsPath + '/');

            // Only update if something actually changed
            if (prevRoutes.games.isActive === newGamesActive &&
                prevRoutes.lists.isActive === newListsActive &&
                prevRoutes.ratings.isActive === newRatingsActive) {
                return prevRoutes;
            }

            return {
                games: { path: gamesPath, isActive: newGamesActive },
                lists: { path: listsPath, isActive: newListsActive },
                ratings: { path: ratingsPath, isActive: newRatingsActive },
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