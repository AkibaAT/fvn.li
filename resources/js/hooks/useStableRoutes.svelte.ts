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

// Helper function to extract pathname from a URL or return as-is if already a path
function getPathname(urlOrPath: string): string {
    try {
        if (urlOrPath.startsWith('http://') || urlOrPath.startsWith('https://')) {
            return new URL(urlOrPath).pathname;
        }
        return urlOrPath;
    } catch {
        return urlOrPath;
    }
}

function computeRoutes(): Routes {
    if (typeof window === 'undefined') {
        return {
            games: { path: '/games', isActive: false },
            lists: { path: '/lists/public', isActive: false },
            ratings: { path: '/ratings', isActive: false },
        };
    }

    const currentPath = window.location.pathname;
    const gamesPath = getPathname(route('games.index'));
    const listsPath = getPathname(route('lists.public'));
    const ratingsPath = getPathname(route('ratings.index'));

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
    };
}

export function useStableRoutes() {
    let routes = $state<Routes>(computeRoutes());

    const updateRoutes = () => {
        if (typeof window === 'undefined') return;
        routes = computeRoutes();
    };

    $effect(() => {
        updateRoutes();

        const handlePopState = () => {
            updateRoutes();
        };

        window.addEventListener('popstate', handlePopState);

        return () => {
            window.removeEventListener('popstate', handlePopState);
        };
    });

    return {
        get games() { return routes.games; },
        get lists() { return routes.lists; },
        get ratings() { return routes.ratings; },
    };
}
