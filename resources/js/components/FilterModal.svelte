<script lang="ts">
    import { untrack } from 'svelte';
    import MultiSelect from '@/components/MultiSelect.svelte';
    import { Button, Checkbox, Drawer, Radio } from '@/components/ui';
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
        <!-- Content & Pricing -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                Content & Pricing
            </h3>
            <div class="space-y-3">
                <Checkbox
                    label="Safe for Work"
                    checked={currentFilters.sfw || false}
                    onchange={(e) => updateFilters({ sfw: e.currentTarget.checked })}
                />
                <Checkbox
                    label="NSFW Content"
                    checked={currentFilters.nsfw || false}
                    class="text-red-600 focus:ring-red-500"
                    onchange={(e) => updateFilters({ nsfw: e.currentTarget.checked })}
                />
                <Checkbox
                    label="Free Games"
                    checked={currentFilters.showFree || false}
                    class="text-green-600 focus:ring-green-500"
                    onchange={(e) => updateFilters({ showFree: e.currentTarget.checked })}
                />
                <Checkbox
                    label="Paid Games"
                    checked={currentFilters.showPaid || false}
                    onchange={(e) => updateFilters({ showPaid: e.currentTarget.checked })}
                />
                <Checkbox
                    label="Has Demo"
                    checked={currentFilters.showDemo || false}
                    onchange={(e) => updateFilters({ showDemo: e.currentTarget.checked })}
                />
                <Checkbox
                    label="On Sale"
                    checked={currentFilters.showSale || false}
                    class="text-rose-600 focus:ring-rose-500"
                    onchange={(e) => updateFilters({ showSale: e.currentTarget.checked })}
                />
                <Checkbox
                    label="Delisted"
                    checked={currentFilters.delisted || false}
                    class="text-yellow-600 focus:ring-yellow-500"
                    onchange={(e) => updateFilters({ delisted: e.currentTarget.checked })}
                />
            </div>
        </div>

        <!-- Status -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-green-500"></span>
                Status
            </h3>
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

        <!-- Platforms -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                Platforms
            </h3>
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

        <!-- Store Platforms -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-orange-500"></span>
                Store Platforms
            </h3>
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

        <!-- Languages -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-indigo-500"></span>
                Languages
            </h3>
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

        <!-- Game Engine -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-cyan-500"></span>
                Game Engine
            </h3>
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

        <!-- Tags -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-teal-500"></span>
                Tags
            </h3>
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

        <!-- Exclude Tags -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-red-500"></span>
                Exclude Tags
            </h3>
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

        <!-- Reading Time -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-violet-500"></span>
                Reading Time
            </h3>
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

        <!-- Game Jams -->
        <div>
            <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                <span class="mr-2 h-2 w-2 rounded-full bg-orange-500"></span>
                Game Jams
            </h3>
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
