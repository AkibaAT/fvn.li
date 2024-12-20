<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Search and Filter Controls --}}
        <div class="mb-6 flex gap-4">
            <div class="flex-1">
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search games, authors, or tags..."
                    class="px-4 py-3 w-full rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                >
            </div>
            <button
                @click="document.getElementById('filters-modal').showModal()"
                class="px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"
            >
                <x-heroicon-o-funnel class="w-5 h-5" />
                Filters
            </button>
        </div>

        {{-- Active Filters Summary --}}
        @if(!empty($selectedPlatforms) || !empty($selectedStatuses) || !empty($selectedEngines) || $nsfw)
            <div class="mb-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Filters:</div>
                    <button wire:click="clearFilters" class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200">
                        Clear All
                    </button>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($selectedPlatforms as $platform)
                        @php $decodedPlatform = $this->decodeFilterValue($platform); @endphp
                        <button wire:click="toggleFilter('platform', '{{ $platform }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                            {{ $platforms[$decodedPlatform] }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach
                    @foreach($selectedStatuses as $status)
                        @php $decodedStatus = $this->decodeFilterValue($status); @endphp
                        <button wire:click="toggleFilter('status', '{{ $status }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                            {{ $decodedStatus }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach
                    @foreach($selectedEngines as $engine)
                        @php $decodedEngine = $this->decodeFilterValue($engine); @endphp
                        <button wire:click="toggleFilter('engine', '{{ $engine }}')"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                            {{ $decodedEngine }}
                            <span class="ml-2">&times;</span>
                        </button>
                    @endforeach
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

        {{-- Games List --}}
        <div class="overflow-hidden rounded-lg bg-white dark:bg-gray-800 shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900/50">
                        <x-table.header>Game</x-table.header>
                        <x-table.sortable-header field="initially_published_at" :currentField="$sortField" :direction="$sortDirection">
                            Initial Release
                        </x-table.sortable-header>
                        <x-table.sortable-header field="version_published_at" :currentField="$sortField" :direction="$sortDirection">
                            Latest Update
                        </x-table.sortable-header>
                        <x-table.header>Version</x-table.header>
                        <x-table.sortable-header field="stats_words" align="center" :currentField="$sortField" :direction="$sortDirection">
                            Words
                        </x-table.sortable-header>
                        <x-table.header>Status</x-table.header>
                        <x-table.sortable-header field="rating_count" align="center" :currentField="$sortField" :direction="$sortDirection">
                            Reviews
                        </x-table.sortable-header>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($games as $game)
                        <tr wire:key="{{ $game->id }}" class="group hover:bg-gray-50 dark:hover:bg-gray-900/50">
                            <x-table.cell>
                                <x-game.card :game="$game" />
                            </x-table.cell>
                            <x-table.cell class="whitespace-nowrap">
                                {{ $game->initially_published_at?->format('Y-m-d') }}
                            </x-table.cell>
                            <x-table.cell class="whitespace-nowrap">
                                {{ $game->version_published_at?->format('Y-m-d') }}
                            </x-table.cell>
                            <x-table.cell>
                                {{ $game->version }}
                            </x-table.cell>
                            <x-table.cell align="center">
                                {{ number_format($game->stats_words) }}
                            </x-table.cell>
                            <x-table.cell>
                                <button wire:click="toggleFilter('status', '{{ $game->status }}')"
                                        class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $game->status }}
                                </button>
                            </x-table.cell>
                            <x-table.cell align="center">
                                {{ $game->rating_count }}
                            </x-table.cell>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-4 flex items-center justify-between">
            <x-filters.select
                wire:model.live="perPage"
                :options="[10 => '10 per page', 25 => '25 per page', 50 => '50 per page']"
            />

            {{ $games->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- Filters Modal --}}
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

    <script>
        document.getElementById('filters-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.currentTarget.close();
            }
        });
    </script>
</div>

