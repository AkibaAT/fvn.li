import React from 'react';
import { useStableRoutes } from '@/hooks/useStableRoutes';
import { router } from '@inertiajs/react';

export default function Navigation() {
    const routes = useStableRoutes();

    return (
        <nav
            className="hidden items-center space-x-1 md:flex"
            role="navigation"
            aria-label="Main navigation"
        >
            <a
                href={routes.games.path}
                onClick={(e) => {
                    e.preventDefault();
                    // Always navigate to a clean Games index to clear filters/search
                    router.get(routes.games.path, {}, { preserveState: false });
                }}
                className={`rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                    routes.games.isActive
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'text-gray-700'
                }`}
                aria-current={routes.games.isActive ? 'page' : undefined}
            >
                <span className="mr-2" aria-hidden="true">🎮</span>
                Games
            </a>
            <a
                href={routes.lists.path}
                onClick={(e) => {
                    e.preventDefault();
                    router.visit(routes.lists.path);
                }}
                className={`rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                    routes.lists.isActive
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'text-gray-700'
                }`}
                aria-current={routes.lists.isActive ? 'page' : undefined}
            >
                <span className="mr-2" aria-hidden="true">📋</span>
                Lists
            </a>

            <a
                href={routes.ratings.path}
                onClick={(e) => {
                    e.preventDefault();
                    router.visit(routes.ratings.path);
                }}
                className={`rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                    routes.ratings.isActive
                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                        : 'text-gray-700'
                }`}
            >
                <span className="mr-2" aria-hidden="true">⭐</span>
                Ratings
            </a>
        </nav>
    );
}
