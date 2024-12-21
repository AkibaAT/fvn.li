<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Search, Filter, and Sort Controls --}}
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search games, authors, or tags..."
                    class="px-4 py-3 w-full rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                >
            </div>
            <div class="flex gap-2">
                <button
                    @click="document.getElementById('sort-modal').showModal()"
                    class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
                >
                    <x-heroicon-o-arrows-up-down class="w-5 h-5" />
                    Sort
                    @if($sortField !== 'version_published_at' || $sortDirection !== 'desc')
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    @endif
                </button>

                <button
                    @click="document.getElementById('filters-modal').showModal()"
                    class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
                >
                    <x-heroicon-o-funnel class="w-5 h-5" />
                    Filters
                    @if(!empty($selectedPlatforms) || !empty($selectedStatuses) || !empty($selectedEngines) || $nsfw)
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    @endif
                </button>
            </div>
        </div>

        {{-- Active Filters and Sort Summary --}}
        @if(!empty($selectedPlatforms) || !empty($selectedStatuses) || !empty($selectedEngines) || $nsfw || ($sortField !== 'version_published_at' || $sortDirection !== 'desc'))
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Filters:</div>
                    <button wire:click="clearFilters" class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                        Reset All
                    </button>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    {{-- Sort indicator --}}
                    @php
                        $sortLabels = [
                            'version_published_at' => 'Latest Update',
                            'initially_published_at' => 'Initial Release',
                            'stats_words' => 'Word Count',
                            'rating_count' => 'Review Count',
                            'name' => 'Name'
                        ];
                        $isDefaultSort = $sortField === 'version_published_at' && $sortDirection === 'desc';
                    @endphp
                    @if(!$isDefaultSort)
                        <button
                            wire:click="resetSort"
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
                        >
                            Sorted by: {{ $sortLabels[$sortField] }} {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endif

                    {{-- Platform filters --}}
                    @foreach($selectedPlatforms as $platform)
                        @php $decodedPlatform = $this->decodeFilterValue($platform); @endphp
                        <button wire:click="toggleFilter('platform', '{{ $platform }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            {{ $platforms[$decodedPlatform] }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach

                    {{-- Status filters --}}
                    @foreach($selectedStatuses as $status)
                        @php $decodedStatus = $this->decodeFilterValue($status); @endphp
                        <button wire:click="toggleFilter('status', '{{ $status }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                            {{ $decodedStatus }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach

                    {{-- Engine filters --}}
                    @foreach($selectedEngines as $engine)
                        @php $decodedEngine = $this->decodeFilterValue($engine); @endphp
                        <button wire:click="toggleFilter('engine', '{{ $engine }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                            {{ $decodedEngine }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach

                    {{-- NSFW filter --}}
                    @if($nsfw)
                        <button wire:click="$toggle('nsfw')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                            NSFW
                            <span class="ml-2">&times;</span>
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Games Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($games as $game)
                {{-- Individual Game Card --}}
                <div class="bg-white/10 dark:bg-gray-800/50 rounded-lg shadow-sm p-4 flex flex-col backdrop-blur-sm">
                    {{-- Game Header --}}
                    <div class="flex gap-4">
                        <img
                            src="{{ $game->thumb_url }}"
                            alt="{{ $game->name }}"
                            class="h-24 w-32 object-cover rounded"
                        >
                        <div class="flex flex-col min-w-0 flex-1">
                            <a href="{{ $game->url }}" target="_blank"
                               class="text-base font-medium text-gray-100 hover:text-blue-400 line-clamp-2">
                                {{ $game->name }}
                            </a>
                            <p class="text-sm text-gray-300 mt-1">{!! $game->authors !!}</p>
                            <div class="mt-2">
                                <x-platform-icons :platforms="$game->platforms" :selected-platforms="$selectedPlatforms" />
                            </div>
                        </div>
                    </div>

                    {{-- Game Details --}}
                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Status:</span>
                            <button wire:click="toggleFilter('status', '{{ $this->encodeFilterValue($game->status) }}')"
                                @class([
                                    'ml-1 hover:text-blue-400',
                                    'text-blue-400 font-medium' => in_array($this->encodeFilterValue($game->status), $selectedStatuses),
                                    'text-gray-200' => !in_array($this->encodeFilterValue($game->status), $selectedStatuses),
                                ])>
                                {{ $game->status }}
                            </button>
                        </div>
                        <div>
                            <span class="text-gray-400">Engine:</span>
                            <button wire:click="toggleFilter('engine', '{{ $this->encodeFilterValue($game->game_engine) }}')"
                                @class([
                                    'ml-1 hover:text-blue-400',
                                    'text-blue-400 font-medium' => in_array($this->encodeFilterValue($game->game_engine), $selectedEngines),
                                    'text-gray-200' => !in_array($this->encodeFilterValue($game->game_engine), $selectedEngines),
                                ])>
                                {{ $game->game_engine }}
                            </button>
                        </div>
                        <div>
                            <span class="text-gray-400">Words:</span>
                            <span class="ml-1 text-gray-200">{{ number_format($game->stats_words) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-400">Reviews:</span>
                            <span class="ml-1 text-gray-200">{{ $game->rating_count ?? '-' }}</span>
                        </div>
                    </div>

                    {{-- Tags --}}
                    @if($game->tags)
                        <div class="mt-4 flex flex-wrap gap-1">
                            @foreach(explode(',', $game->tags) as $tag)
                                <x-badge class="bg-gray-700/50 text-gray-200">{{ trim($tag) }}</x-badge>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <x-filters.select
                wire:model.live="perPage"
                :options="[12 => '12 per page', 24 => '24 per page', 36 => '36 per page']"
                class="w-full sm:w-auto"
            />
            {{ $games->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- Filters Dialog --}}
    <dialog
        wire:ignore.self
        id="filters-modal"
        class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        {{-- Header --}}
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                Filter Games
            </h3>
            <button
                @click="$el.closest('dialog').close()"
                class="text-gray-400 hover:text-gray-500"
            >
                <span class="sr-only">Close</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Content --}}
        <div class="space-y-6">
            {{-- Platforms --}}
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Platforms</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($platforms as $value => $label)
                        @php $encodedValue = $this->encodeFilterValue($value); @endphp
                        <button wire:click="toggleFilter('platform', '{{ $encodedValue }}')"
                                class="px-3 py-1 rounded-lg text-sm {{ in_array($encodedValue, $selectedPlatforms)
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Status --}}
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($statuses as $status)
                        @php $encodedStatus = $this->encodeFilterValue($status); @endphp
                        <button wire:click="toggleFilter('status', '{{ $encodedStatus }}')"
                                class="px-3 py-1 rounded-lg text-sm {{ in_array($encodedStatus, $selectedStatuses)
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                            {{ $status }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Game Engines --}}
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Game Engine</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($gameEngines as $engine)
                        @php $encodedEngine = $this->encodeFilterValue($engine); @endphp
                        <button wire:click="toggleFilter('engine', '{{ $encodedEngine }}')"
                                class="px-3 py-1 rounded-lg text-sm {{ in_array($encodedEngine, $selectedEngines)
                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                            {{ $engine }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- NSFW Toggle --}}
            <div>
                <button wire:click="$toggle('nsfw')"
                        class="px-3 py-1 rounded-lg text-sm {{ $nsfw
                            ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    NSFW
                </button>
            </div>
            {{-- Filter sections remain the same --}}
        </div>

        {{-- Footer --}}
        <div class="mt-6 flex justify-end">
            <button
                @click="$el.closest('dialog').close()"
                type="button"
                class="rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                Close
            </button>
        </div>
    </dialog>

    {{-- Sort Dialog --}}
    <dialog
        wire:ignore.self
        id="sort-modal"
        class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-sm dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Sort Games</h3>
            <button
                @click="$el.closest('dialog').close()"
                class="text-gray-400 hover:text-gray-500"
            >
                <span class="sr-only">Close</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="space-y-2">
            @foreach([
                'version_published_at' => 'Latest Update',
                'initially_published_at' => 'Initial Release',
                'stats_words' => 'Word Count',
                'rating_count' => 'Review Count',
                'name' => 'Name'
            ] as $field => $label)
                <button
                    wire:click="sortBy('{{ $field }}')"
                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between {{ $sortField === $field ? 'bg-gray-50 dark:bg-gray-700' : '' }}"
                >
                    <span>{{ $label }}</span>
                    @if($sortField === $field)
                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </dialog>

    <script>
        document.getElementById('sort-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.currentTarget.close();
            }
        });
        document.getElementById('filters-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.currentTarget.close();
            }
        });
    </script>
</div>
