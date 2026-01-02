import { useStableRoutes } from '@/hooks/useStableRoutes';
import { router } from '@inertiajs/react';

export default function Navigation() {
    const routes = useStableRoutes();

    const navLinkBase =
        'relative rounded-full px-3.5 py-2 text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-brand-secondary)] focus:ring-offset-2';
    const navLinkInactive =
        'text-[var(--color-ui-text-muted)] hover:bg-[var(--color-ui-surface-alt)] hover:text-[var(--color-ui-text)]';
    const navLinkActive =
        'bg-[var(--color-surface-peach)] text-[var(--color-link-hover)] shadow-sm';

    return (
        <nav
            className="hidden items-center gap-1 md:flex"
            role="navigation"
            aria-label="Main navigation"
        >
            <a
                href={routes.games.path}
                onClick={(e) => {
                    e.preventDefault();
                    router.get(routes.games.path, {}, { preserveState: false });
                }}
                className={`${navLinkBase} ${routes.games.isActive ? navLinkActive : navLinkInactive}`}
                aria-current={routes.games.isActive ? 'page' : undefined}
            >
                <span className="flex items-center gap-1.5">
                    <svg
                        className="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"
                        />
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    Games
                </span>
            </a>

            <a
                href={routes.lists.path}
                onClick={(e) => {
                    e.preventDefault();
                    router.visit(routes.lists.path);
                }}
                className={`${navLinkBase} ${routes.lists.isActive ? navLinkActive : navLinkInactive}`}
                aria-current={routes.lists.isActive ? 'page' : undefined}
            >
                <span className="flex items-center gap-1.5">
                    <svg
                        className="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                        />
                    </svg>
                    Lists
                </span>
            </a>

            <a
                href={routes.ratings.path}
                onClick={(e) => {
                    e.preventDefault();
                    router.visit(routes.ratings.path);
                }}
                className={`${navLinkBase} ${routes.ratings.isActive ? navLinkActive : navLinkInactive}`}
                aria-current={routes.ratings.isActive ? 'page' : undefined}
            >
                <span className="flex items-center gap-1.5">
                    <svg
                        className="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                        />
                    </svg>
                    Ratings
                </span>
            </a>
        </nav>
    );
}
