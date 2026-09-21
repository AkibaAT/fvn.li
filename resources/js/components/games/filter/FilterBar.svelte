<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { Popover } from '@/components/ui';
    import { cn } from '@/utils/cn';
    import FilterMenu from './FilterMenu.svelte';
    import FilterValuePicker from './FilterValuePicker.svelte';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import type { CurrentFilters, FilterOptions } from '@/types';

    interface Props {
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        class?: string;
    }

    let { filters, currentFilters, class: className = '' }: Props = $props();

    const { updateFilters, toggleFilter, clearFilters, buildActiveFilterChips } = useGameFilters({
        getCurrentFilters: () => currentFilters,
        getFilters: () => filters,
        onGamesPage: true,
    });

    const { getPlatformIcon: getTypedPlatformIcon } = usePlatformIcons();
    const getPlatformIcon = (platform: string) => getTypedPlatformIcon(platform as GameCardPlatform);

    let menuOpen = $state(false);
    let activeField = $state<string | null>(null);

    function openMenu() {
        activeField = null;
        menuOpen = !menuOpen;
    }

    function handlePopoverClose() {
        menuOpen = false;
        activeField = null;
    }

    const fieldCatalog = [
        { id: 'tags', label: 'Tags', group: 'Refine' },
        { id: 'status', label: 'Status', group: 'Refine' },
        { id: 'language', label: 'Language', group: 'Refine' },
        { id: 'readingTime', label: 'Reading time', group: 'Refine' },
        { id: 'platform', label: 'Platform', group: 'Platform & store' },
        { id: 'storePlatform', label: 'Store', group: 'Platform & store' },
        { id: 'content', label: 'Content rating', group: 'Content & price' },
        { id: 'price', label: 'Price & availability', group: 'Content & price' },
        { id: 'engine', label: 'Engine', group: 'Details' },
        { id: 'gameJam', label: 'Game jam', group: 'Details' },
        { id: 'visibility', label: 'Visibility', group: 'Details' },
    ];

    const fieldActiveCounts = $derived<Record<string, number>>({
        tags: (currentFilters.selectedTags?.length ?? 0) + (currentFilters.excludedTags?.length ?? 0),
        status: currentFilters.selectedStatuses?.length ?? 0,
        language: currentFilters.selectedLanguages?.length ?? 0,
        readingTime: currentFilters.readingTime ? 1 : 0,
        platform: currentFilters.selectedPlatforms?.length ?? 0,
        storePlatform: currentFilters.selectedStorePlatforms?.length ?? 0,
        content: (currentFilters.sfw ? 1 : 0) + (currentFilters.nsfw ? 1 : 0),
        price:
            (currentFilters.showFree ? 1 : 0) +
            (currentFilters.showPaid ? 1 : 0) +
            (currentFilters.showDemo ? 1 : 0) +
            (currentFilters.showSale ? 1 : 0),
        engine: currentFilters.selectedEngines?.length ?? 0,
        gameJam: currentFilters.selectedGameJams?.length ?? 0,
        visibility: currentFilters.showIgnored ? 1 : 0,
    });

    const menuFields = $derived(fieldCatalog.map((field) => ({ ...field, activeCount: fieldActiveCounts[field.id] ?? 0 })));

    const chips = $derived(buildActiveFilterChips());

    const chipFieldMap: Record<string, string> = {
        status: 'status',
        engine: 'engine',
        platform: 'platform',
        storePlatform: 'storePlatform',
        language: 'language',
        gameJam: 'gameJam',
        tag: 'tags',
        excludeTag: 'tags',
        readingTime: 'readingTime',
        sfw: 'content',
        nsfw: 'content',
        free: 'price',
        paid: 'price',
        demo: 'price',
        sale: 'price',
    };

    function editChipField(chipType: string) {
        activeField = chipFieldMap[chipType] ?? null;
        menuOpen = true;
    }

    const chipLabel = (chip: (typeof chips)[number]) => (chip.type === 'excludeTag' ? chip.label.replace(/^Exclude:\s*/, '') : chip.label);
</script>

<div
    class={cn(
        'flex min-h-9 min-w-0 flex-1 flex-wrap items-center gap-1.5',
        chips.length > 0 && 'rounded-lg border border-border bg-surface p-1 shadow-sm max-sm:basis-full',
        className,
    )}
>
    <Popover bind:open={menuOpen} onClose={handlePopoverClose} class="shrink-0">
        <button
            type="button"
            onclick={openMenu}
            aria-label="Add filter"
            aria-expanded={menuOpen}
            aria-haspopup="dialog"
            class="inline-flex h-7 items-center gap-1 rounded-md border border-dashed border-border-strong px-2.5 text-ui font-medium text-fg-muted transition-colors hover:border-fg hover:text-fg"
        >
            <PlusIcon class="h-3.5 w-3.5" />
            Filter
            {#if chips.length > 0}
                <span class="rounded-full border border-accent px-1.5 text-2xs font-medium text-accent-fg">{chips.length}</span>
            {/if}
        </button>

        {#if menuOpen}
            <div
                class="popover-elevated absolute left-0 z-50 mt-1.5 w-88 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-border-strong"
                role="dialog"
                aria-label="Filter builder"
            >
                {#if activeField}
                    <FilterValuePicker
                        field={activeField}
                        {filters}
                        {currentFilters}
                        {toggleFilter}
                        {updateFilters}
                        onBack={() => (activeField = null)}
                        {getPlatformIcon}
                    />
                {:else}
                    <FilterMenu fields={menuFields} onSelect={(field) => (activeField = field)} />
                {/if}
            </div>
        {/if}
    </Popover>

    {#if chips.length > 0}
        <div class="flex min-w-0 flex-wrap items-center gap-1.5" role="list" aria-label="Active filters">
            {#each chips as chip (chip.key)}
                <span
                    class="inline-flex h-7 items-center overflow-hidden rounded-md border border-border bg-surface-alt text-ui text-fg"
                    role="listitem"
                >
                    <button
                        type="button"
                        onclick={() => editChipField(chip.type)}
                        aria-label="Edit filter: {chipLabel(chip)}"
                        class="inline-flex h-full items-center gap-1.5 px-2.5 transition-colors hover:bg-surface"
                    >
                        {#if chip.type === 'language' && chip.flagCode}
                            <span class="fi fi-{chip.flagCode} rounded-sm"></span>
                        {:else if chip.type === 'platform' && chip.value && getPlatformIcon(chip.value)}
                            {@const iconMeta = getPlatformIcon(chip.value)}
                            {@const PlatformIconComponent = iconMeta.icon}
                            <PlatformIconComponent class="h-3.5 w-3.5 text-fg-muted" />
                        {:else if chip.type === 'storePlatform' && chip.value}
                            {#if chip.value === 'itch_io'}
                                <Itchio class="h-3.5 w-3.5 text-fg-muted" monochrome />
                            {:else if chip.value === 'steam'}
                                <Steam class="h-3.5 w-3.5 text-fg-muted" monochrome />
                            {:else if chip.value === 'other'}
                                <GlobeIcon class="h-3.5 w-3.5 text-fg-muted" />
                            {/if}
                        {:else if chip.type === 'excludeTag'}
                            <span class="text-fg-muted" aria-hidden="true">not</span>
                        {/if}
                        {chipLabel(chip)}
                    </button>
                    {#if chip.onClear}
                        <button
                            type="button"
                            onclick={chip.onClear}
                            aria-label="Remove {chipLabel(chip)}"
                            class="inline-flex h-full w-6 items-center justify-center border-l border-border text-fg-faint transition-colors hover:text-fg"
                        >
                            <XMarkIcon class="h-3.5 w-3.5" />
                        </button>
                    {/if}
                </span>
            {/each}
        </div>
        <button type="button" onclick={clearFilters} class="px-1 text-ui text-fg-muted transition-colors hover:text-fg">Clear</button>
    {/if}
</div>
