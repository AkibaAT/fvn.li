<script lang="ts">
    import clsx from 'clsx';
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { useStorePlatformIcons, type StorePlatform } from '@/hooks/useStorePlatformIcons';
    import { cn } from '@/utils/cn';

    interface Props {
        storePlatform: StorePlatform;
        active?: boolean;
        onclick?: (platform: StorePlatform) => void;
        overlay?: boolean;
        class?: string;
    }

    let { storePlatform, active = false, onclick, overlay = false, class: className = '' }: Props = $props();

    const { getStorePlatformIcon } = useStorePlatformIcons();
    const title = $derived(getStorePlatformIcon(storePlatform).title);
</script>

<button
    type="button"
    onclick={() => onclick?.(storePlatform)}
    {title}
    aria-label="{title} store"
    aria-pressed={active}
    class={cn(
        'inline-flex shrink-0 items-center justify-center rounded-sm transition-colors',
        overlay ? 'h-5 w-5.5 border border-border-strong shadow-sm' : 'size-4.5',
        active
            ? 'bg-fg text-surface'
            : clsx(overlay && 'bg-surface', storePlatform === 'itch_io' ? 'text-itchio hover:text-fg' : 'text-fg-muted hover:text-fg'),
        className,
    )}
>
    {#if storePlatform === 'itch_io'}
        <Itchio class="size-3.25" monochrome />
    {:else if storePlatform === 'steam'}
        <Steam class="size-3.25" monochrome />
    {:else}
        <GlobeIcon class="size-3.25" />
    {/if}
</button>
