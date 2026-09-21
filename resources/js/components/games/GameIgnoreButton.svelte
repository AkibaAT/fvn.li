<script lang="ts">
    import { cn } from '@/utils/cn';
    import { refreshPage } from '@/utils/refreshPage';
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
    import NoSymbolSolidIcon from '@/components/icons/NoSymbolSolid.svelte';
    import { toggleIgnoredGame } from '@/api';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import { page } from '@inertiajs/svelte';

    interface Props {
        gameId: number;
        isIgnored: boolean;
        class?: string;
    }

    let { gameId, isIgnored, class: className = '' }: Props = $props();

    const auth = $derived((page as any).props?.auth);
    const toggleAction = useAsyncAction();

    const handleToggle = async (event: MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();

        if (!auth?.user || toggleAction.isLoading) return;

        await toggleAction.run(
            async () => {
                await toggleIgnoredGame(gameId);
                await refreshPage();
            },
            { fallbackError: 'Failed to update ignore list' },
        );
    };
</script>

<button
    type="button"
    onclick={handleToggle}
    disabled={toggleAction.isLoading}
    class={cn(
        'inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg disabled:opacity-50',
        className,
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
