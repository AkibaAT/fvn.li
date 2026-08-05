<script lang="ts">
    import { Button } from '@/components/ui';
    import BookmarkIcon from '@/components/icons/Bookmark.svelte';
    import CurrencyCircleDollarIcon from '@/components/icons/CurrencyCircleDollar.svelte';
    import GamepadIcon from '@/components/icons/Gamepad.svelte';
    import ShieldIcon from '@/components/icons/Shield.svelte';
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

    const badges = $derived([
        {
            visible: game.is_nsfw,
            label: 'NSFW',
            icon: ShieldIcon,
            active: Boolean(nsfw),
            onToggle: onNsfwToggle,
            ariaLabel: 'Filter by NSFW content',
            classes: 'border-red-300 bg-red-200 text-red-800 dark:border-red-700/60 dark:bg-red-900/40 dark:text-red-300',
            activeClasses: 'border-2 ring-1 ring-red-300 dark:ring-red-300',
        },
        {
            visible: Boolean((game as Record<string, unknown>).is_on_sale),
            label: 'Sale',
            icon: BookmarkIcon,
            active: Boolean(showSale),
            onToggle: onSaleToggle,
            ariaLabel: 'Filter by games on sale',
            classes: 'border-rose-300 bg-rose-200 text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/40 dark:text-rose-300',
            activeClasses: 'border-2 ring-1 ring-rose-300 dark:ring-rose-300',
        },
        {
            visible: game.is_paid,
            label: 'Paid',
            icon: CurrencyCircleDollarIcon,
            active: Boolean(showPaid),
            onToggle: onPaidToggle,
            ariaLabel: 'Filter by paid games',
            classes: 'border-amber-300 bg-amber-200 text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/40 dark:text-amber-300',
            activeClasses: 'border-2 ring-1 ring-amber-300 dark:ring-amber-300',
        },
        {
            visible: game.has_demo,
            label: 'Demo',
            icon: GamepadIcon,
            active: Boolean(showDemo),
            onToggle: onDemoToggle,
            ariaLabel: 'Filter by has demo',
            classes: 'border-sky-300 bg-sky-200 text-sky-800 dark:border-sky-700/60 dark:bg-sky-900/40 dark:text-sky-300',
            activeClasses: 'border-2 ring-1 ring-sky-300 dark:ring-sky-300',
        },
        {
            visible: game.is_delisted,
            label: 'Delisted',
            icon: null,
            active: Boolean(showDelisted),
            onToggle: onDelistedToggle,
            ariaLabel: 'Filter by delisted games',
            classes: 'border-yellow-300 bg-yellow-200 text-yellow-800 dark:border-yellow-700/60 dark:bg-yellow-900/40 dark:text-yellow-300',
            activeClasses: 'border-2 ring-1 ring-yellow-300 dark:ring-yellow-300',
        },
    ]);
</script>

{#each badges as badge (badge.label)}
    {#if badge.visible}
        {@const Icon = badge.icon}
        <Button
            type="button"
            variant="outline"
            onclick={badge.onToggle}
            class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold {badge.classes} {badge.active ? badge.activeClasses : ''}"
            ariaLabel={badge.ariaLabel}
            title={badge.ariaLabel}
        >
            {#if Icon}<Icon class="h-4 w-4" />{/if}
            {badge.label}
        </Button>
    {/if}
{/each}
