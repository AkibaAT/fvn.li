@if (!empty($selectedPlatforms) || !empty($selectedStatuses) || !empty($selectedEngines) || !empty($selectedLanguages) || !empty($selectedGameJams) || !empty($selectedTags) || $nsfw || $sfw || $showPaid || $showFree || $showDemo || ($sortField !== 'latest_version_published_at' || $sortDirection !== 'desc') || $showHidden)
    <div class="mb-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium text-gray-900 dark:text-gray-300">Active Filters:</div>
            <button wire:click="clearFilters"
                    class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                Reset All
            </button>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            @include('games.list.partials.sort-indicator')
            @include('games.list.partials.filter-badges')
        </div>
    </div>
@endif
