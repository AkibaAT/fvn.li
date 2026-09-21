<script lang="ts">
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
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

{#if chips.length > 0}
    {#each chips as chip (chip.key)}
        <span class="inline-flex h-7 items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 text-[13px] text-fg">
            {#if chip.type === 'language' && chip.flagCode}
                <span class="fi fi-{chip.flagCode} rounded-xs"></span>
            {/if}
            {#if chip.type === 'platform' && chip.value && getPlatformIcon(chip.value)}
                {@const iconMeta = getPlatformIcon(chip.value)}
                {@const Icon = iconMeta.icon}
                <Icon class="h-3.5 w-3.5 text-fg-muted" />
            {/if}
            {#if chip.type === 'storePlatform' && chip.value && getStorePlatformIcon?.(chip.value)}
                {#if chip.value === 'itch_io'}
                    <Itchio class="h-3.5 w-3.5 text-fg-muted" monochrome />
                {:else if chip.value === 'steam'}
                    <Steam class="h-3.5 w-3.5 text-fg-muted" monochrome />
                {:else if chip.value === 'other'}
                    <GlobeIcon class="h-3.5 w-3.5 text-fg-muted" />
                {/if}
            {/if}
            {chip.label}
            {#if chip.onClear}
                <button
                    type="button"
                    onclick={chip.onClear}
                    aria-label="Remove {chip.label}"
                    class="inline-flex h-4 w-4 items-center justify-center text-fg-faint transition-colors hover:text-fg"
                >
                    &times;
                </button>
            {/if}
        </span>
    {/each}
    <button type="button" onclick={onClearAll} class="text-[13px] text-fg-muted transition-colors hover:text-fg">Clear</button>
{/if}
