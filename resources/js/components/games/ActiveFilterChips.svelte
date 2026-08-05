<script lang="ts">
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { Button } from '@/components/ui';
    import type { PlatformIconMeta } from '@/hooks/usePlatformIcons';

    interface ActiveChip {
        key: string;
        type: string;
        value?: string;
        label: string;
        flagCode?: string;
        onClear?: () => void;
    }

    interface Props {
        chips: ActiveChip[];
        onClearAll: () => void;
        getPlatformIcon: (platform: string) => PlatformIconMeta;
        getStorePlatformIcon?: (platform: string) => { color: string; title: string; label: string } | undefined;
    }

    let { chips, onClearAll, getPlatformIcon, getStorePlatformIcon }: Props = $props();
</script>

{#if chips.length === 0}
    <span class="text-sm text-gray-600 dark:text-gray-400">No active filters</span>
{:else}
    <span class="mr-1 text-sm font-medium text-gray-800 dark:text-gray-200">Active filters:</span>
    {#each chips as chip (chip.key)}
        <span
            class="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-800 dark:border-blue-400/30 dark:bg-blue-500/15 dark:text-blue-100"
        >
            {#if chip.type === 'language' && chip.flagCode}
                <span class="fi fi-{chip.flagCode} mr-0.5 rounded-xs"></span>
            {/if}
            {#if chip.type === 'platform' && chip.value && getPlatformIcon(chip.value)}
                {@const iconMeta = getPlatformIcon(chip.value)}
                {@const Icon = iconMeta.icon}
                <Icon class="mr-0.5 h-4 w-4 {iconMeta.color}" />
            {/if}
            {#if chip.type === 'storePlatform' && chip.value && getStorePlatformIcon?.(chip.value)}
                {@const iconMeta = getStorePlatformIcon?.(chip.value)}
                {#if chip.value === 'itch_io'}
                    <Itchio class="h-4 w-4 {iconMeta?.color} mr-0.5" />
                {:else if chip.value === 'steam'}
                    <Steam class="h-4 w-4 {iconMeta?.color} mr-0.5" />
                {:else if chip.value === 'other'}
                    <GlobeIcon class="h-4 w-4 {iconMeta?.color} mr-0.5" />
                {/if}
            {/if}
            {chip.label}
            {#if chip.onClear}
                <Button type="button" variant="link" tone="neutral" size="xs" ariaLabel="Remove {chip.label}" onclick={chip.onClear} class="ml-1">
                    &times;
                </Button>
            {/if}
        </span>
    {/each}
    <Button type="button" variant="link" tone="danger" size="xs" onclick={onClearAll} class="ml-1">Reset all</Button>
{/if}
