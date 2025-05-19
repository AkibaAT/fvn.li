<dialog
    wire:ignore.self
    id="filters-modal"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-ui.dialog-header title="Filter Games"/>

    <div class="space-y-6">
        @php
            $filterSections = [
                [
                    'title' => 'Languages',
                    'type' => 'language',
                    'items' => $languages,
                    'selected' => $selectedLanguages,
                    'class' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
                ],
                [
                    'title' => 'Platforms',
                    'type' => 'platform',
                    'items' => $platforms,
                    'selected' => $selectedPlatforms,
                    'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                ],
                [
                    'title' => 'Status',
                    'type' => 'status',
                    'items' => $statuses,
                    'selected' => $selectedStatuses,
                    'class' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                ],
                [
                    'title' => 'Tags',
                    'type' => 'tag',
                    'items' => $tags,
                    'selected' => $selectedTags,
                    'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
                ],
                [
                    'title' => 'Game Engine',
                    'type' => 'engine',
                    'items' => $gameEngines,
                    'selected' => $selectedEngines,
                    'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
                ],
                [
                    'title' => 'Game Jams',
                    'type' => 'gamejam',
                    'items' => $gameJams,
                    'selected' => $selectedGameJams,
                    'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                ]
            ];
        @endphp

        <div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content Rating</div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$toggle('sfw')"
                        class="px-3 py-1 rounded-lg text-sm {{ $sfw
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    SFW
                </button>
                <button wire:click="$toggle('nsfw')"
                        class="px-3 py-1 rounded-lg text-sm {{ $nsfw
                            ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    NSFW
                </button>
            </div>
        </div>

        @foreach ($filterSections as $section)
            <div>
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $section['title'] }}</div>
                @if (in_array($section['type'], ['gamejam', 'tag']))
                    <div x-data="{
                        open: false,
                        search: '',
                        items: @js($section['items']),
                        selected: @js($section['selected']),
                        get filteredItems() {
                            // Get entries and sort them alphabetically by name (the second element in each entry)
                            const entries = Object.entries(this.items);
                            const sortedEntries = entries.sort((a, b) => a[1].toLowerCase().localeCompare(b[1].toLowerCase()));

                            // If there's a search term, filter the sorted entries
                            if (this.search) {
                                return sortedEntries.filter(([id, name]) =>
                                    name.toLowerCase().includes(this.search.toLowerCase())
                                );
                            }

                            return sortedEntries;
                        },
                        isSelected(value) {
                            return this.selected.includes(value);
                        },
                        toggle(value) {
                            $wire.toggleFilter('{{ $section['type'] }}', value);
                        },
                        init() {
                            // Listen for Livewire updates to selected items
                            const propertyMap = {
                                'gamejam': 'selectedGameJams',
                                'tag': 'selectedTags',
                            };

                            const propertyName = propertyMap['{{ $section['type'] }}'] || `selected{{ Str::studly($section['type']) }}s`;
                            this.$watch(`$wire.${propertyName}`, (newValue) => {
                                this.selected = newValue;
                            });

                            // Listen for the clearFilters event
                            $wire.$on('filtersCleared', () => {
                                this.selected = [];
                            });
                        }
                    }" class="relative">
                        <button
                            @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 text-left border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            <span x-text="selected.length ? `${selected.length} selected` : 'Select {{ $section['title'] }}...'" class="text-sm"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div
                            x-show="open"
                            @click.away="open = false"
                            class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg overflow-hidden"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95">
                            <div class="p-2">
                                <input
                                    x-model="search"
                                    type="text"
                                    placeholder="Search {{ strtolower($section['title']) }}..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                            </div>
                            <div class="max-h-60 overflow-y-auto">
                                <template x-if="filteredItems.length === 0">
                                    <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No results found</div>
                                </template>
                                <template x-for="[value, label] in filteredItems" :key="value">
                                    <button
                                        @click="toggle(value)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-between"
                                        :class="isSelected(value) ? '{{ $section['class'] }}' : 'text-gray-700 dark:text-gray-200'">
                                        <span x-text="label"></span>
                                        <svg x-show="isSelected(value)" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($section['items'] as $value => $label)
                            <button wire:click="toggleFilter('{{ $section['type'] }}', '{{ addslashes($value) }}')"
                                    class="px-3 py-1 rounded-lg text-sm {{ in_array($value, $section['selected'])
                                        ? $section['class']
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                                @if ($section['type'] === 'language')
                                    <span
                                        class="fi fi-{{ $label['flag_code'] }} rounded-xs mr-1"></span>{{ $label['ref_name'] }}
                                @else
                                    {{ $label }}
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pricing</div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$toggle('showFree')"
                        class="px-3 py-1 rounded-lg text-sm {{ $showFree
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    Free Games
                </button>
                <button wire:click="$toggle('showPaid')"
                        class="px-3 py-1 rounded-lg text-sm {{ $showPaid
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    Paid Games
                </button>
                <button wire:click="$toggle('showDemo')"
                        class="px-3 py-1 rounded-lg text-sm {{ $showDemo
                            ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    Has Demo
                </button>
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Visibility</div>
            <div class="flex flex-wrap gap-2">
                <button wire:click="$toggle('showHidden')"
                        class="px-3 py-1 rounded-lg text-sm {{ $showHidden
                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'
                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600' }}">
                    Show Unlisted Games
                </button>
            </div>
        </div>
    </div>

    <x-ui.dialog-footer/>
</dialog>
