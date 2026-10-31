<script lang="ts">
    import { Flag } from '@/components/ui';
    import { cn } from '@/utils/cn';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    interface Props {
        game: GameCardGame;
        selectedStatuses?: string[];
        nsfw?: boolean;
        showPaid?: boolean;
        showDemo?: boolean;
        showSale?: boolean;
        showLost?: boolean;
        onStatusClick?: (status: string) => void;
        onNsfwToggle?: () => void;
        onPaidToggle?: () => void;
        onDemoToggle?: () => void;
        onSaleToggle?: () => void;
        onLostToggle?: () => void;
        limit?: number;
        class?: string;
    }

    let {
        game,
        selectedStatuses = [],
        nsfw = false,
        showPaid = false,
        showDemo = false,
        showSale = false,
        showLost = false,
        onStatusClick,
        onNsfwToggle,
        onPaidToggle,
        onDemoToggle,
        onSaleToggle,
        onLostToggle,
        limit = Infinity,
        class: className = '',
    }: Props = $props();

    /** The status flag only appears when the game is not in its default released/published state. */
    const statusLabel = $derived.by(() => {
        const status = typeof game.status === 'string' ? game.status.trim() : '';
        if (!status) return null;

        const normalized = status.toLowerCase();
        if (normalized === 'released' || normalized === 'published') return null;
        return normalized === 'in development' ? 'In dev' : status;
    });

    const flags = $derived(
        [
            {
                key: 'lost',
                label: game.delisted_reason ? 'Lost' : null,
                active: showLost,
                ariaLabel: 'Filter by lost media',
                onclick: () => onLostToggle?.(),
            },
            {
                key: 'status',
                label: statusLabel,
                active: Boolean(game.status && selectedStatuses.includes(String(game.status))),
                ariaLabel: `Filter by status: ${game.status}`,
                onclick: () => onStatusClick?.(String(game.status)),
            },
            {
                key: 'nsfw',
                label: game.is_nsfw ? '18+' : null,
                active: nsfw,
                ariaLabel: 'Filter by NSFW content',
                onclick: () => onNsfwToggle?.(),
            },
            {
                key: 'paid',
                label: game.is_paid ? 'Paid' : null,
                active: showPaid,
                ariaLabel: 'Filter by paid games',
                onclick: () => onPaidToggle?.(),
            },
            {
                key: 'demo',
                label: game.has_demo ? 'Demo' : null,
                active: showDemo,
                ariaLabel: 'Filter by has demo',
                onclick: () => onDemoToggle?.(),
            },
            {
                key: 'sale',
                label: game.is_on_sale ? 'Sale' : null,
                active: showSale,
                ariaLabel: 'Filter by games on sale',
                onclick: () => onSaleToggle?.(),
            },
        ].filter((flag) => Boolean(flag.label)),
    );

    const visibleFlags = $derived(flags.slice(0, limit));
    const hiddenFlags = $derived(flags.slice(limit));
</script>

{#each visibleFlags as flag (flag.key)}
    <Flag label={flag.label as string} active={flag.active} ariaLabel={flag.ariaLabel} onclick={flag.onclick} class={className} />
{/each}
{#if hiddenFlags.length > 0}
    <span
        class={cn(
            'inline-flex items-center self-center rounded-sm border border-border-strong px-1.25 py-px text-micro font-semibold text-fg-muted',
            className,
        )}
        title={hiddenFlags.map((flag) => flag.label).join(', ')}
    >
        +{hiddenFlags.length}
    </span>
{/if}
