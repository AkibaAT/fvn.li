<div class="bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search games..."
                    class="px-4 py-3 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
                >
            </div>
            <div class="flex gap-2">
                @foreach ([
                    'sort' => [
                        'icon' => 'heroicon-o-arrows-up-down',
                        'modal' => 'sort-modal',
                        'active' => $sortField !== 'published_at' || $sortDirection !== 'desc'
                    ],
                    'filters' => [
                        'icon' => 'heroicon-o-funnel',
                        'modal' => 'filters-modal',
                        'active' => !empty($selectedPlatforms) || !empty($selectedLanguages)
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

        @if (!empty($selectedPlatforms) || !empty($selectedLanguages) || ($sortField !== 'published_at' || $sortDirection !== 'desc'))
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-300">Active Filters:</div>
                    <button wire:click="clearFilters"
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                        Reset All
                    </button>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @if ($sortField !== 'published_at' || $sortDirection !== 'desc')
                        <button wire:click="sortBy('published_at')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">
                            Sorted by: {{ match($sortField) {
                                'published_at' => 'Release Date',
                                'stats_words' => 'Word Count',
                                'rating_count' => 'Review Count',
                                default => $sortField}
                            }} {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endif

                    @foreach ($selectedPlatforms as $platform)
                        <button wire:click="toggleFilter('platform', '{{ $platform }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            {{ $platforms[$this->decodeFilterValue($platform)] }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach

                    @foreach ($selectedLanguages as $language)
                        <button wire:click="toggleFilter('language', '{{ $language }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                            {{ $languages[$language] }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <x-table.header>Game</x-table.header>
                        <x-table.header>Version</x-table.header>
                        <x-table.sortable-header :field="'published_at'" :currentField="$sortField"
                                                 :direction="$sortDirection">
                            Published
                        </x-table.sortable-header>
                        <x-table.header class="text-center">Platforms</x-table.header>
                        <x-table.header class="text-center">Languages</x-table.header>
                        <x-table.sortable-header :field="'stats_words'" :currentField="$sortField"
                                                 :direction="$sortDirection">
                            Words
                        </x-table.sortable-header>
                        <x-table.sortable-header :field="'rating'" :currentField="$sortField"
                                                 :direction="$sortDirection">
                            Rating
                        </x-table.sortable-header>
                        <x-table.sortable-header :field="'rating_count'" :currentField="$sortField"
                                                 :direction="$sortDirection">
                            Reviews
                        </x-table.sortable-header>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($versions as $version)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <x-table.cell>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('games.show', $version->game) }}"
                                       class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $version->game->name }}
                                    </a>
                                    <a href="{{ $version->game->url }}"
                                       target="_blank"
                                       class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                       title="Open on itch.io">
                                        <i class="fas fa-external-link-alt text-sm"></i>
                                    </a>
                                </div>
                            </x-table.cell>
                            <x-table.cell>{{ $version->version }}</x-table.cell>
                            <x-table.cell>{{ $version->published_at->format('M j, Y') }}</x-table.cell>
                            <x-table.cell class="text-center">
                                <div class="flex justify-center space-x-2">
                                    @foreach (['windows', 'linux', 'mac', 'android', 'web'] as $platform)
                                        @if ($version->{"platform_{$platform}"})
                                            <button
                                                wire:click="toggleFilter('platform', '{{ $platform }}')"
                                                @class([
                                                    'p-1 rounded-sm transition-colors',
                                                    'bg-blue-100 dark:bg-blue-900' => in_array($platform, $selectedPlatforms),
                                                    'hover:bg-gray-100 dark:hover:bg-gray-700' => !in_array($platform, $selectedPlatforms),
                                                ])
                                            >
                                                <x-dynamic-component
                                                    :component="'heroicon-o-' . match($platform) {
                                                            'windows' => 'window',
                                                            'linux' => 'command-line',
                                                            'mac' => 'computer-desktop',
                                                            'android' => 'device-phone-mobile',
                                                            'web' => 'globe-alt'
                                                        }"
                                                    class="w-4 h-4"
                                                />
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </x-table.cell>
                            <x-table.cell class="text-center">
                                <div class="flex flex-wrap justify-center gap-1">
                                    @foreach ($version->languageStats as $stat)
                                        <button
                                            wire:click="toggleFilter('language', '{{ $this->encodeFilterValue($stat->iso_code) }}')"
                                            @class([
                                                'px-1.5 py-0.5 text-xs rounded-sm',
                                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' =>
                                                    in_array($this->encodeFilterValue($stat->iso_code), $selectedLanguages),
                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' =>
                                                    !in_array($this->encodeFilterValue($stat->iso_code), $selectedLanguages),
                                            ])
                                        >
                                            {{ $stat->language->ref_name }}
                                        </button>
                                    @endforeach
                                </div>
                            </x-table.cell>
                            <x-table.cell class="text-right">
                                @if ($version->languageStats->isNotEmpty())
                                    {{ number_format($version->languageStats->sum('words')) }}
                                @else
                                    -
                                @endif
                            </x-table.cell>
                            <x-table.cell class="text-right">
                                @if ($version->rating)
                                    {{ number_format($version->rating, 1) }}
                                @else
                                    -
                                @endif
                            </x-table.cell>
                            <x-table.cell class="text-right">
                                {{ $version->rating_count ?? 0 }}
                            </x-table.cell>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $versions->links() }}
        </div>
    </div>

    {{-- Sort Dialog --}}
    <dialog
        wire:ignore.self
        id="sort-modal"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-sm dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        <x-dialog-header title="Sort Versions"/>

        <div class="space-y-2">
            @foreach ([
                'published_at' => 'Release Date',
                'stats_words' => 'Word Count',
                'rating' => 'Rating',
                'rating_count' => 'Review Count'
            ] as $field => $label)
                <button
                    wire:click="sortBy('{{ $field }}')"
                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between {{ $sortField === $field ? 'bg-gray-50 dark:bg-gray-700' : '' }}"
                >
                    <span>{{ $label }}</span>
                    @if ($sortField === $field)
                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <x-dialog-footer/>
    </dialog>

    {{-- Filters Dialog --}}
    <dialog
        wire:ignore.self
        id="filters-modal"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        <x-dialog-header title="Filter Versions"/>

        <div class="space-y-6">
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Platforms</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($platforms as $platform => $label)
                        <button
                            wire:click="toggleFilter('platform', '{{ $platform }}')"
                            class="px-3 py-1 rounded-lg text-sm {{ in_array($platform, $selectedPlatforms)
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Languages</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($languages as $code => $name)
                        <button
                            wire:click="toggleFilter('language', '{{ $code }}')"
                            class="px-3 py-1 rounded-lg text-sm {{ in_array($code, $selectedLanguages)
                                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}"
                        >
                            {{ $name }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <x-dialog-footer/>
    </dialog>

    @include('components.meta-data-refresh')
</div>
