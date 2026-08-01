<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    let {
        game,
        authorsInlineHtml,
    }: {
        game: GameCardGame;
        authorsInlineHtml: string;
    } = $props();

    const gameName = $derived(game.effective_name);
</script>

<div class="space-y-1 overflow-hidden">
    <h2 class="line-clamp-2 min-h-[3.5rem] text-lg font-semibold break-words text-gray-900 dark:text-white">
        <Link
            href={route('games.show', game.slug)}
            class="rounded transition-colors hover:text-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:hover:text-blue-400"
            aria-label="View details for {gameName}"
        >
            {gameName}
        </Link>
    </h2>

    {#if game.authors}
        <div class="-mt-1 line-clamp-1 min-h-5 text-sm text-gray-600 dark:text-gray-400">
            <span class="sr-only">Authors: </span>
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html authorsInlineHtml}
        </div>
    {/if}
</div>
