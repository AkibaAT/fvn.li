<script lang="ts">
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';

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
        getChipColorClass: (type?: string) => string;
        getPlatformIcon: (platform: string) => { icon: string; color: string } | undefined;
        getStorePlatformIcon?: (platform: string) => { color: string; title: string; label: string } | undefined;
    }

    let { chips, onClearAll, getChipColorClass, getPlatformIcon, getStorePlatformIcon }: Props = $props();
</script>

{#if chips.length === 0}
    <span class="text-sm text-gray-600 dark:text-gray-400">No active filters</span>
{:else}
    <span class="mr-1 text-sm font-medium text-gray-800 dark:text-gray-200">Active filters:</span>
    {#each chips as chip (chip.key)}
        <span class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs {getChipColorClass(chip.type)}">
            {#if chip.type === 'language' && chip.flagCode}
                <span class="fi fi-{chip.flagCode} mr-0.5 rounded-xs"></span>
            {/if}
            {#if chip.type === 'platform' && chip.value && getPlatformIcon(chip.value)}
                {@const iconMeta = getPlatformIcon(chip.value)}
                <i class="{iconMeta?.icon} {iconMeta?.color} mr-0.5"></i>
            {/if}
            {#if chip.type === 'storePlatform' && chip.value && getStorePlatformIcon?.(chip.value)}
                {@const iconMeta = getStorePlatformIcon?.(chip.value)}
                {#if chip.value === 'itch_io'}
                    <Itchio class="h-4 w-4 {iconMeta?.color} mr-0.5" />
                {:else if chip.value === 'steam'}
                    <Steam class="h-4 w-4 {iconMeta?.color} mr-0.5" />
                {:else if chip.value === 'other'}
                    <svg class="h-4 w-4 {iconMeta?.color} mr-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                    </svg>
                {/if}
            {/if}
            {chip.label}
            {#if chip.onClear}
                <button
                    aria-label="Remove {chip.label}"
                    onclick={chip.onClear}
                    class="ml-1 cursor-pointer hover:opacity-80"
                >
                    &times;
                </button>
            {/if}
        </span>
    {/each}
    <button
        type="button"
        onclick={onClearAll}
        class="ml-1 cursor-pointer text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
    >
        Reset all
    </button>
{/if}
