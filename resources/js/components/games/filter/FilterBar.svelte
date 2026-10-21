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
    import { availableFilterFields, buildFilterChips, findFilterField, type FilterFieldId } from './filter-fields';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import type { CurrentFilters, FilterOptions } from '@/types';

    interface Props {
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        class?: string;
    }

    let { filters, currentFilters, class: className = '' }: Props = $props();

    const { updateFilters, clearFilters } = useGameFilters({
        getCurrentFilters: () => currentFilters,
        onGamesPage: true,
    });

    const { getPlatformIcon: getTypedPlatformIcon } = usePlatformIcons();
    const getPlatformIcon = (platform: string) => getTypedPlatformIcon(platform as GameCardPlatform);

    let menuOpen = $state(false);
    let activeFieldId = $state<FilterFieldId | null>(null);
    let returnFocusField = $state<FilterFieldId | null>(null);

    const activeField = $derived(activeFieldId ? findFilterField(activeFieldId) : undefined);

    function openMenu() {
        activeFieldId = null;
        returnFocusField = null;
        menuOpen = !menuOpen;
    }

    function handlePopoverClose() {
        menuOpen = false;
        activeFieldId = null;
    }

    function selectField(field: FilterFieldId) {
        returnFocusField = field;
        activeFieldId = field;
    }

    function backToMenu() {
        returnFocusField = activeFieldId;
        activeFieldId = null;
    }

    const chips = $derived(buildFilterChips(currentFilters, filters));

    const menuFields = $derived(
        availableFilterFields(filters).map((field) => ({
            id: field.id,
            label: field.label,
            group: field.group,
            activeCount: chips.filter((chip) => chip.field.id === field.id).length,
        })),
    );

    function editChipField(field: FilterFieldId) {
        selectField(field);
        menuOpen = true;
    }
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
                <span class="rounded-full border border-accent-fg bg-accent px-1.5 text-2xs font-medium text-on-accent">{chips.length}</span>
            {/if}
        </button>

        {#if menuOpen}
            <div
                class="popover-elevated absolute left-0 z-50 mt-1.5 w-88 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-border-strong"
                role="dialog"
                aria-label="Filter builder"
            >
                {#if activeField}
                    {#key activeField.id}
                        <FilterValuePicker field={activeField} {filters} {currentFilters} {updateFilters} onBack={backToMenu} {getPlatformIcon} />
                    {/key}
                {:else}
                    <FilterMenu fields={menuFields} onSelect={selectField} focusField={returnFocusField} />
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
                        onclick={() => editChipField(chip.field.id)}
                        aria-label="Edit filter: {chip.accessibleLabel}"
                        class="inline-flex h-full items-center gap-1.5 px-2.5 transition-colors hover:bg-surface"
                    >
                        {#if chip.flagCode}
                            <span class="fi fi-{chip.flagCode} rounded-sm"></span>
                        {:else if chip.field.id === 'platform' && chip.value && getPlatformIcon(chip.value)}
                            {@const iconMeta = getPlatformIcon(chip.value)}
                            {@const PlatformIconComponent = iconMeta.icon}
                            <PlatformIconComponent class="h-3.5 w-3.5 text-fg-muted" />
                        {:else if chip.field.id === 'storePlatform' && chip.value}
                            {#if chip.value === 'itch_io'}
                                <Itchio class="h-3.5 w-3.5 text-fg-muted" monochrome />
                            {:else if chip.value === 'steam'}
                                <Steam class="h-3.5 w-3.5 text-fg-muted" monochrome />
                            {:else if chip.value === 'other'}
                                <GlobeIcon class="h-3.5 w-3.5 text-fg-muted" />
                            {/if}
                        {:else if chip.excluded}
                            <span class="text-fg-muted" aria-hidden="true">not</span>
                        {/if}
                        {chip.label}
                    </button>
                    <button
                        type="button"
                        onclick={() => updateFilters(chip.clear)}
                        aria-label="Remove {chip.accessibleLabel}"
                        class="inline-flex h-full w-6 items-center justify-center border-l border-border text-fg-faint transition-colors hover:text-fg"
                    >
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </span>
            {/each}
        </div>
        <button type="button" onclick={clearFilters} aria-label="Clear all filters" class="px-1 text-ui text-fg-muted transition-colors hover:text-fg"
            >Clear</button
        >
    {/if}
</div>
