<script lang="ts">
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import type { StorePlatform, StorePlatformIconMeta } from '@/hooks/useStorePlatformIcons';

    let {
        platform,
        iconMeta,
        isActive = false,
        onclick,
    }: {
        platform: StorePlatform;
        iconMeta: StorePlatformIconMeta;
        isActive?: boolean;
        onclick?: (platform: StorePlatform) => void;
    } = $props();

    const handleClick = () => {
        if (onclick) onclick(platform);
    };

    const baseClasses = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-all';
    const interactiveClasses = $derived(onclick ? 'cursor-pointer' : '');
    const stateClasses = $derived(
        isActive ? 'border-2 border-current bg-opacity-20 shadow-sm' : 'border border-gray-200 bg-white dark:border-gray-600/50 dark:bg-gray-700/50',
    );
</script>

<button
    onclick={handleClick}
    disabled={!onclick}
    class="{baseClasses} {interactiveClasses} {stateClasses} {iconMeta.color}"
    title={iconMeta.title}
    aria-label="{iconMeta.title} store"
    aria-pressed={isActive}
>
    {#if platform === 'itch_io'}
        <Itchio class="h-4 w-4" />
    {:else if platform === 'steam'}
        <Steam class="h-4 w-4" />
    {:else}
        <svg
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
        </svg>
    {/if}
    <span class="hidden sm:inline">{iconMeta.label}</span>
</button>
