<script lang="ts">
    import { useStableRoutes } from '@/hooks/useStableRoutes.svelte';
    import { router } from '@inertiajs/svelte';

    const routes = useStableRoutes();
</script>

<nav class="hidden items-center space-x-1 md:flex" aria-label="Main navigation">
    <a
        href={routes.games.path}
        onclick={(e: MouseEvent) => {
            e.preventDefault();
            router.get(routes.games.path, {}, { preserveState: false });
        }}
        class="rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white {routes
            .games.isActive
            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
            : 'text-gray-700'}"
        aria-current={routes.games.isActive ? 'page' : undefined}
    >
        <i class="icon-gamepad-2 mr-1 inline" aria-hidden="true"></i>Games
    </a>
    <a
        href={routes.lists.path}
        onclick={(e: MouseEvent) => {
            e.preventDefault();
            router.visit(routes.lists.path);
        }}
        class="rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white {routes
            .lists.isActive
            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
            : 'text-gray-700'}"
        aria-current={routes.lists.isActive ? 'page' : undefined}
    >
        <i class="icon-paste mr-1 inline" aria-hidden="true"></i>Lists
    </a>
    <a
        href={routes.ratings.path}
        onclick={(e: MouseEvent) => {
            e.preventDefault();
            router.visit(routes.ratings.path);
        }}
        class="rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white {routes
            .ratings.isActive
            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
            : 'text-gray-700'}"
    >
        <i class="icon-star mr-1 inline" aria-hidden="true"></i>Ratings
    </a>
</nav>
