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

function safeRoutePath(name: string, fallback: string): string {
    try {
        return getPathname(route(name));
    } catch {
        return fallback;
    }
}

function computeRoutes(currentUrl?: string): Routes {
    const fallbackPath = typeof window !== 'undefined' ? window.location.pathname : '/';
    const currentPath = getPathname(currentUrl || fallbackPath);
    const gamesPath = safeRoutePath('games.index', '/games');
    const listsPath = safeRoutePath('lists.public', '/lists/public');
    const ratingsPath = safeRoutePath('ratings.index', '/ratings');

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
    const routes = $derived(computeRoutes(page.url));

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
