<div class="mb-6 flex flex-col sm:flex-row gap-4">
    <div class="flex-1">
        <input
            wire:model.live="search"
            type="search"
            placeholder="Search games, authors, or tags..."
            class="px-4 py-3 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
        >
    </div>
    <div class="flex gap-2">
        @foreach ([
            'sort' => [
                'icon' => 'heroicon-o-arrows-up-down',
                'modal' => 'sort-modal',
                'active' => $sortField !== 'latest_version_published_at' || $sortDirection !== 'desc'
            ],
            'filters' => [
                'icon' => 'heroicon-o-funnel',
                'modal' => 'filters-modal',
                'active' => !empty($selectedPlatforms) || !empty($selectedStatuses) || !empty($selectedEngines) || $nsfw || $sfw || $showPaid || $showFree || $showDemo || $showSuspended
            ]
        ] as $type => $config)
            <button
                @click="document.getElementById('{{ $config['modal'] }}').showModal()"
                class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 shadow-xs"
            >
                <x-dynamic-component :component="$config['icon']" class="w-5 h-5"/>
                {{ ucfirst($type) }}
                @if ($config['active'])
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
