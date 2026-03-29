<script lang="ts">
    import { untrack } from 'svelte';
    import MultiSelect from '@/components/MultiSelect.svelte';
    import { useGameFilters } from '@/hooks/useGameFilters.svelte';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import { isDialogBackdropClick } from '@/utils/dialog';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        onGamesPage?: boolean;
    }

    let { isOpen, onClose, filters, currentFilters, onGamesPage = false }: Props = $props();

    let dialogEl: HTMLDialogElement;
    let filterCloseBtnEl: HTMLButtonElement;

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

    $effect(() => {
        if (!dialogEl) return;

        if (isOpen) {
            dialogEl.showModal();
            requestAnimationFrame(() => {
                filterCloseBtnEl?.focus();
            });
        } else if (dialogEl.open) {
            dialogEl.close();
        }
    });

    function handleCancel(event: Event) {
        event.preventDefault();
        onClose();
    }

    function handleBackdropClick(event: MouseEvent) {
        if (isDialogBackdropClick(dialogEl, event)) {
            onClose();
        }
    }

    const readingTimeDefaults: Record<string, string> = {
        short: 'Short (< 10k words)',
        medium: 'Medium (10k-50k words)',
        long: 'Long (> 50k words)',
    };
</script>

<dialog
    bind:this={dialogEl}
    aria-modal="true"
    aria-labelledby="games-filter-title"
    aria-describedby="games-filter-desc"
    onclick={handleBackdropClick}
    oncancel={handleCancel}
    class="h-full max-h-none w-full max-w-none border-0 bg-transparent p-0 backdrop:bg-black/50 backdrop:backdrop-blur-sm"
>
    <h1 id="games-filter-title" class="sr-only">Filter Games</h1>
    <p id="games-filter-desc" class="sr-only">
        Use the options to filter games by content, platforms, languages, engine, tags, jams, and visibility.
    </p>

    <div class="ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Filter Games</h2>
            <div class="flex items-center space-x-2">
                {#if hasActiveFilters()}
                    <button
                        type="button"
                        onclick={clearFilters}
                        class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                    >
                        Clear All
                    </button>
                {/if}
                <button
                    bind:this={filterCloseBtnEl}
                    type="button"
                    onclick={onClose}
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    aria-label="Close filter dialog"
                >
                    <i class="icon-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="space-y-8 p-6">
                <!-- Content & Pricing -->
                <div>
                    <h3 class="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                        <span class="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                        Content & Pricing
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.sfw || false}
                                onchange={(e) => updateFilters({ sfw: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Safe for Work</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.nsfw || false}
                                onchange={(e) => updateFilters({ nsfw: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-red-600 focus:ring-red-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">NSFW Content</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.showFree || false}
                                onchange={(e) => updateFilters({ showFree: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-green-600 focus:ring-green-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Free Games</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.showPaid || false}
                                onchange={(e) => updateFilters({ showPaid: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Paid Games</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.showDemo || false}
                                onchange={(e) => updateFilters({ showDemo: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Has Demo</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.showSale || false}
                                onchange={(e) => updateFilters({ showSale: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">On Sale</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                checked={currentFilters.delisted || false}
                                onchange={(e) => updateFilters({ delisted: (e.target as HTMLInputElement).checked })}
                                class="mr-3 rounded border-gray-300 text-yellow-600 focus:ring-yellow-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Delisted</span>
                        </label>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedStatuses: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all statuses
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedPlatforms: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all platforms
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedStorePlatforms: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all store platforms
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedLanguages: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all languages
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedEngines: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all engines
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedTags: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all tags
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ excludedTags: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all excluded tags
                            </button>
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
                            <label class="flex items-center">
                                <input
                                    type="radio"
                                    name="readingTime"
                                    {value}
                                    checked={currentFilters.readingTime === value}
                                    onchange={() => updateFilters({ readingTime: value })}
                                    class="mr-3 border-gray-300 text-violet-600 focus:ring-violet-500"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{label}</span>
                            </label>
                        {/each}
                        {#if currentFilters.readingTime}
                            <button
                                type="button"
                                onclick={() => updateFilters({ readingTime: '' })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear reading time
                            </button>
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
                            <button
                                type="button"
                                onclick={() => updateFilters({ selectedGameJams: [] })}
                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear all game jams
                            </button>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
</dialog>
