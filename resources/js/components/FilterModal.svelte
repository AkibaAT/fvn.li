<script lang="ts">
    import { untrack } from 'svelte';
    import MultiSelect from '@/components/MultiSelect.svelte';
    import { Button, Drawer, Radio } from '@/components/ui';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import type { CurrentFilters, FilterOptions } from '@/types';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        onGamesPage?: boolean;
    }

    let { isOpen, onClose, filters, currentFilters, onGamesPage = false }: Props = $props();

    const {
        updateFilters: hookUpdateFilters,
        toggleFilter,
        clearFilters: hookClearFilters,
        hasActiveFilters,
    } = useGameFilters({ getCurrentFilters: () => currentFilters, getFilters: () => filters, onGamesPage: untrack(() => onGamesPage) });

    const updateFilters = (newFilters: Partial<CurrentFilters>) => {
        hookUpdateFilters(newFilters);
        if (!onGamesPage) {
            onClose();
        }
    };

    const clearFilters = () => {
        hookClearFilters();
        if (!onGamesPage) {
            onClose();
        }
    };

    const readingTimeDefaults: Record<string, string> = {
        short: 'Short (< 10k words)',
        medium: 'Medium (10k-50k words)',
        long: 'Long (> 50k words)',
    };

    const contentPricingOptions: Record<string, string> = {
        sfw: 'Safe for Work',
        nsfw: 'NSFW Content',
        showFree: 'Free Games',
        showPaid: 'Paid Games',
        showDemo: 'Has Demo',
        showSale: 'On Sale',
        delisted: 'Delisted',
    };
    const contentPricingKeys = Object.keys(contentPricingOptions) as Array<keyof CurrentFilters>;
    let selectedContentPricing = $derived(contentPricingKeys.filter((key) => Boolean(currentFilters[key])));

    const toggleContentPricing = (value: string) => {
        const key = contentPricingKeys.find((candidate) => candidate === value);
        if (!key) return;

        updateFilters({ [key]: !currentFilters[key] } as Partial<CurrentFilters>);
    };

    const clearContentPricing = () => {
        updateFilters({
            sfw: false,
            nsfw: false,
            showFree: false,
            showPaid: false,
            showDemo: false,
            showSale: false,
            delisted: false,
        });
    };
</script>

{#snippet clearFilterButton(label: string, action: () => void)}
    <Button type="button" variant="link" tone="danger" size="xs" onclick={action}>{label}</Button>
{/snippet}

{#snippet actions()}
    {#if hasActiveFilters()}
        <Button type="button" variant="link" tone="danger" size="sm" onclick={clearFilters}>Clear All</Button>
    {/if}
{/snippet}

<Drawer open={isOpen} {onClose} title="Filter Games" describedBy="games-filter-desc" closeLabel="Close filter dialog" {actions}>
    <p id="games-filter-desc" class="sr-only">
        Use the options to filter games by content, platforms, languages, engine, tags, jams, and visibility.
    </p>

    <div class="space-y-8 p-6">
        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Content & Pricing</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Content & Pricing"
                    items={contentPricingOptions}
                    selectedItems={selectedContentPricing}
                    onToggle={toggleContentPricing}
                    placeholder="Select content and pricing..."
                />
                {#if selectedContentPricing.length > 0}
                    {@render clearFilterButton('Clear content and pricing', clearContentPricing)}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Status</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Status"
                    items={filters.statuses}
                    selectedItems={currentFilters.selectedStatuses || []}
                    onToggle={(value) => toggleFilter('status', value)}
                    placeholder="Select status..."
                />
                {#if currentFilters.selectedStatuses && currentFilters.selectedStatuses.length > 0}
                    {@render clearFilterButton('Clear all statuses', () => updateFilters({ selectedStatuses: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Platforms</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Platforms"
                    items={filters.platforms}
                    selectedItems={currentFilters.selectedPlatforms || []}
                    onToggle={(value) => toggleFilter('platform', value)}
                    placeholder="Select platforms..."
                />
                {#if currentFilters.selectedPlatforms && currentFilters.selectedPlatforms.length > 0}
                    {@render clearFilterButton('Clear all platforms', () => updateFilters({ selectedPlatforms: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Store Platforms</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Store Platforms"
                    items={filters.storePlatforms}
                    selectedItems={currentFilters.selectedStorePlatforms || []}
                    onToggle={(value) => toggleFilter('storePlatform', value)}
                    placeholder="Select store platforms..."
                />
                {#if currentFilters.selectedStorePlatforms && currentFilters.selectedStorePlatforms.length > 0}
                    {@render clearFilterButton('Clear all store platforms', () => updateFilters({ selectedStorePlatforms: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Languages</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Languages"
                    items={filters.languages}
                    selectedItems={currentFilters.selectedLanguages || []}
                    onToggle={(value) => toggleFilter('language', value)}
                    placeholder="Select languages..."
                />
                {#if currentFilters.selectedLanguages && currentFilters.selectedLanguages.length > 0}
                    {@render clearFilterButton('Clear all languages', () => updateFilters({ selectedLanguages: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Game Engine</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Game Engines"
                    items={filters.gameEngines}
                    selectedItems={currentFilters.selectedEngines || []}
                    onToggle={(value) => toggleFilter('engine', value)}
                    placeholder="Select engines..."
                />
                {#if currentFilters.selectedEngines && currentFilters.selectedEngines.length > 0}
                    {@render clearFilterButton('Clear all engines', () => updateFilters({ selectedEngines: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Tags</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Tags"
                    items={filters.tags}
                    selectedItems={currentFilters.selectedTags || []}
                    onToggle={(value) => toggleFilter('tag', value)}
                />
                {#if currentFilters.selectedTags && currentFilters.selectedTags.length > 0}
                    {@render clearFilterButton('Clear all tags', () => updateFilters({ selectedTags: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Exclude Tags</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Exclude Tags"
                    items={filters.tags}
                    selectedItems={currentFilters.excludedTags || []}
                    onToggle={(value) => toggleFilter('excludeTag', value)}
                    placeholder="Select tags to exclude..."
                />
                {#if currentFilters.excludedTags && currentFilters.excludedTags.length > 0}
                    {@render clearFilterButton('Clear all excluded tags', () => updateFilters({ excludedTags: [] }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Reading Time</h3>
            <div class="space-y-3">
                {#each Object.entries(filters.readingTimeOptions || readingTimeDefaults) as [value, label] (value)}
                    <Radio
                        name="readingTime"
                        {value}
                        {label}
                        checked={currentFilters.readingTime === value}
                        class="text-violet-600 focus:ring-violet-500"
                        onchange={() => updateFilters({ readingTime: value })}
                    />
                {/each}
                {#if currentFilters.readingTime}
                    {@render clearFilterButton('Clear reading time', () => updateFilters({ readingTime: '' }))}
                {/if}
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Game Jams</h3>
            <div class="space-y-2">
                <MultiSelect
                    title="Game Jams"
                    items={filters.gameJams}
                    selectedItems={currentFilters.selectedGameJams || []}
                    onToggle={(value) => toggleFilter('gameJam', value)}
                />
                {#if currentFilters.selectedGameJams && currentFilters.selectedGameJams.length > 0}
                    {@render clearFilterButton('Clear all game jams', () => updateFilters({ selectedGameJams: [] }))}
                {/if}
            </div>
        </div>
    </div>
</Drawer>
