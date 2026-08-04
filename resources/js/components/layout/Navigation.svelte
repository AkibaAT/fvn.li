<script lang="ts">
    import { useStableRoutes } from '@/hooks/useStableRoutes.svelte';
    import { router } from '@inertiajs/svelte';

    const routes = useStableRoutes();

    const links = $derived([
        { label: 'Games', route: routes.games },
        { label: 'Lists', route: routes.lists },
        { label: 'Ratings', route: routes.ratings },
    ]);

    function navigate(event: MouseEvent, path: string): void {
        event.preventDefault();
        router.visit(path, { preserveState: false });
    }
</script>

<nav class="hidden items-center space-x-1 md:flex" aria-label="Main navigation">
    {#each links as link (link.label)}
        <a
            href={link.route.path}
            onclick={(event) => navigate(event, link.route.path)}
            class="rounded-lg px-4 py-2 font-medium transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white {link
                .route.isActive
                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                : 'text-gray-700'}"
            aria-current={link.route.isActive ? 'page' : undefined}
        >
            {link.label}
        </a>
    {/each}
</nav>
