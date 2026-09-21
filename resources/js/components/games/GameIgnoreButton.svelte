<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
    import NoSymbolSolidIcon from '@/components/icons/NoSymbolSolid.svelte';
    import { toggleIgnoredGame } from '@/api';
    import { page } from '@inertiajs/svelte';
    import { toast } from '@/utils/toast';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';

    interface Props {
        gameId: number;
        isIgnored: boolean;
        class?: string;
    }

    let { gameId, isIgnored, class: className = '' }: Props = $props();

    const auth = $derived((page as any).props?.auth);
    let isToggling = $state(false);

    const handleToggle = async (event: MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (!auth?.user || isToggling) return;

        isToggling = true;
        try {
            await toggleIgnoredGame(gameId);
            if (!(await refreshPage())) return;
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to update ignore list');
        } finally {
            isToggling = false;
        }
    };
</script>

<!--
    Callers render this only for signed-in visitors: a component that SSR'd to a lone
    comment node for guests left Svelte unable to hydrate the surrounding card.
-->
<button
    type="button"
    onclick={handleToggle}
    disabled={isToggling}
    class={twMerge(
        clsx(
            'inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg disabled:opacity-50',
            className,
        ),
    )}
    title={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
    aria-label={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
>
    {#if isIgnored}
        <NoSymbolSolidIcon class="h-4 w-4 text-accent" />
    {:else}
        <NoSymbolIcon class="h-4 w-4" />
    {/if}
</button>
