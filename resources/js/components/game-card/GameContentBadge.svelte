<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';


    let {
        game,
        nsfw,
        showPaid,
        showDemo,
        showSale,
        showDelisted,
        onNsfwToggle,
        onPaidToggle,
        onDemoToggle,
        onSaleToggle,
        onDelistedToggle,
    }: {
        game: GameCardGame;
        nsfw?: boolean;
        showPaid?: boolean;
        showDemo?: boolean;
        showSale?: boolean;
        showDelisted?: boolean;
        onNsfwToggle?: () => void;
        onPaidToggle?: () => void;
        onDemoToggle?: () => void;
        onSaleToggle?: () => void;
        onDelistedToggle?: () => void;
    } = $props();
</script>

{#if game.is_nsfw}
    <button
        type="button"
        onclick={onNsfwToggle}
        class="cursor-pointer rounded-full border border-red-300 bg-red-200 px-3 py-1.5 text-xs font-bold text-red-800 dark:border-red-700/60 dark:bg-red-900/40 dark:text-red-300 {nsfw ? 'border-2 ring-1 ring-red-300 dark:ring-red-300' : ''}"
        aria-label="Filter by NSFW content"
        title="Filter by NSFW content"
    >
        <i class="icon-shield inline" aria-hidden="true"></i> NSFW
    </button>
{/if}

{#if Boolean((game as Record<string, unknown>).is_on_sale)}
    <button
        type="button"
        onclick={onSaleToggle}
        class="cursor-pointer rounded-full border border-rose-300 bg-rose-200 px-3 py-1.5 text-xs font-bold text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/40 dark:text-rose-300 {showSale ? 'border-2 ring-1 ring-rose-300 dark:ring-rose-300' : ''}"
        aria-label="Filter by games on sale"
        title="Filter by games on sale"
    >
        🔖 Sale
    </button>
{/if}

{#if game.is_paid}
    <button
        type="button"
        onclick={onPaidToggle}
        class="cursor-pointer rounded-full border border-amber-300 bg-amber-200 px-3 py-1.5 text-xs font-bold text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/40 dark:text-amber-300 {showPaid ? 'border-2 ring-1 ring-amber-300 dark:ring-amber-300' : ''}"
        aria-label="Filter by paid games"
        title="Filter by paid games"
    >
        <i class="icon-currency-circle-dollar inline" aria-hidden="true"></i> Paid
    </button>
{/if}

{#if game.has_demo}
    <button
        type="button"
        onclick={onDemoToggle}
        class="cursor-pointer rounded-full border border-sky-300 bg-sky-200 px-3 py-1.5 text-xs font-bold text-sky-800 dark:border-sky-700/60 dark:bg-sky-900/40 dark:text-sky-300 {showDemo ? 'border-2 ring-1 ring-sky-300 dark:ring-sky-300' : ''}"
        aria-label="Filter by has demo"
        title="Filter by has demo"
    >
        <i class="icon-gamepad-2 inline" aria-hidden="true"></i> Demo
    </button>
{/if}

{#if game.is_delisted}
    <button
        type="button"
        onclick={onDelistedToggle}
        class="cursor-pointer rounded-full border border-yellow-300 bg-yellow-200 px-3 py-1.5 text-xs font-bold text-yellow-800 dark:border-yellow-700/60 dark:bg-yellow-900/40 dark:text-yellow-300 {showDelisted ? 'border-2 ring-1 ring-yellow-300 dark:ring-yellow-300' : ''}"
        aria-label="Filter by delisted games"
        title="Filter by delisted games"
    >
        Delisted
    </button>
{/if}
