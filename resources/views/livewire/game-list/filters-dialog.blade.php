<dialog
    wire:ignore.self
    id="filters-modal"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-dialog-header title="Filter Games"/>

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
                    'title' => 'Game Engine',
                    'type' => 'engine',
                    'items' => $gameEngines,
                    'selected' => $selectedEngines,
                    'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
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
                <div class="flex flex-wrap gap-2">
                    @foreach ($section['items'] as $value => $label)
                        <button wire:click="toggleFilter('{{ $section['type'] }}', '{{ $value }}')"
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
            </div>
        @endforeach

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

    <x-dialog-footer/>
</dialog>
