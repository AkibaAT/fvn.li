import { page } from '@inertiajs/svelte';
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
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        return new URL(urlOrPath, base).pathname;
    } catch {
        return urlOrPath;
    }
}

function computeRoutes(currentUrl?: string): Routes {
    if (typeof window === 'undefined') {
        return {
            games: { path: '/games', isActive: false },
            lists: { path: '/lists/public', isActive: false },
            ratings: { path: '/ratings', isActive: false },
        };
    }

    const currentPath = getPathname(currentUrl ?? window.location.pathname);
    const gamesPath = getPathname(route('games.index'));
    const listsPath = getPathname(route('lists.public'));
    const ratingsPath = getPathname(route('ratings.index'));

    return {
        games: {
            path: gamesPath,
            isActive: currentPath === gamesPath || currentPath.startsWith(gamesPath + '/'),
        },
        lists: {
            path: listsPath,
            isActive: currentPath === listsPath || currentPath.startsWith(listsPath + '/'),
        },
        ratings: {
            path: ratingsPath,
            isActive: currentPath === ratingsPath || currentPath.startsWith(ratingsPath + '/'),
        },
    };
}

export function useStableRoutes() {
    let currentUrl = $state('');
    let routes = $state<Routes>(computeRoutes());

    $effect(() => {
        currentUrl = page.url;
        routes = computeRoutes(currentUrl);
    });

    return {
        get games() {
            return routes.games;
        },
        get lists() {
            return routes.lists;
        },
        get ratings() {
            return routes.ratings;
        },
    };
}
