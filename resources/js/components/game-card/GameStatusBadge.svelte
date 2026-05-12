<script lang="ts">
    import { Button } from '@/components/ui';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    let {
        game,
        isActive = false,
        onclick,
    }: {
        game: GameCardGame;
        isActive?: boolean;
        onclick: (status: string) => void;
    } = $props();

    const statusClass = $derived(
        game.status === 'Released'
            ? 'border-emerald-300 bg-emerald-200 text-emerald-800 dark:border-emerald-700/60 dark:bg-emerald-900/40 dark:text-emerald-300'
            : game.status === 'In development'
              ? 'border-blue-300 bg-blue-200 text-blue-800 dark:border-blue-700/60 dark:bg-blue-900/40 dark:text-blue-300'
              : 'border-gray-300 bg-gray-200 text-gray-800 dark:border-gray-700/60 dark:bg-gray-900/40 dark:text-gray-300',
    );

    const activeClass = $derived(
        isActive
            ? `border-2 ring-1 ${
                  game.status === 'Released'
                      ? 'ring-emerald-300 dark:ring-emerald-300'
                      : game.status === 'In development'
                        ? 'ring-blue-300 dark:ring-blue-300'
                        : 'ring-gray-300 dark:ring-gray-500'
              }`
            : '',
    );
</script>

{#if game.status}
    <Button
        type="button"
        variant="outline"
        tone="neutral"
        onclick={() => onclick(String(game.status))}
        class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold {statusClass} {activeClass}"
        aria-label="Filter by status: {game.status}"
        title="Filter by status: {game.status}"
    >
        {game.status}
    </Button>
{/if}
