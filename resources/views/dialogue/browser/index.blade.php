@php use App\Models\Game; @endphp
<div class="bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
        <div class="mb-4 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
            @if ($gameId)
                <a href="{{ route('games.show', Game::find($gameId)) }}"
                   class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Game Details
                </a>
            @else
                <a href="{{ route('games.index') }}"
                   class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Game List
                </a>
            @endif
        </div>

        <!-- Filters section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Dialogue Browser
                </h1>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Game</label>
                    <select wire:model.live="gameId"
                            class="px-4 py-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs">
                        <option value="">All Games</option>
                        @foreach ($games as $game)
                            <option value="{{ $game->id }}">{{ $game->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Version</label>
                    <select wire:model.live="versionId"
                            class="px-4 py-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
                            @if (!$gameId) disabled @endif>
                        <option value="">All Versions</option>
                        @foreach ($versions as $version)
                            <option value="{{ $version->id }}">{{ $version->version }}
                                ({{ $version->published_at?->format('Y-m-d') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Character</label>
                    <select wire:model.live="selectedCharacter"
                            class="px-4 py-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
                            @if (!$versionId && !$gameId) disabled @endif>
                        <option value="">All Characters</option>
                        <option value="narrator">Narrator</option>
                        <option value="menu_choice">Menu Choices</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character->character_id }}">{{ $character->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                    <select wire:model.live="selectedLanguage"
                            class="px-4 py-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs">
                        @foreach ($languages as $language)
                            <option value="{{ $language->id }}">
                                {{ $language->ref_name }} ({{ $language->flag_code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Context</label>
                    <select wire:model.live="selectedContext"
                            class="px-4 py-2 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs"
                            @if (!$versionId) disabled @endif>
                        <option value="">All Contexts</option>
                        @foreach ($contexts as $context)
                            <option value="{{ $context }}">{{ $context }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                    <div class="relative mt-1 flex rounded-md shadow-xs">
                        <input wire:model.live.debounce.300ms="searchQuery"
                               type="text"
                               placeholder="Search dialogue..."
                               @if ($showDuplicates) disabled @endif
                               class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs px-4 py-2">
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex flex-wrap items-center justify-between mb-2 gap-2">
                <div class="flex items-center space-x-4">
                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
                        <input wire:model.live="groupByContext"
                               type="checkbox"
                               class="mr-2 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Group by context
                    </label>

                    <button
                        wire:click="toggleDuplicates"
                        class="flex items-center text-sm px-3 py-1 rounded-lg {{ $showDuplicates
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                             stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        {{ $showDuplicates ? 'Hide Duplicates' : 'Show Duplicates' }}
                    </button>
                </div>
            </div>

            <!-- Duplicates Options -->
            @if ($showDuplicates)
                <div class="mt-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Duplicate Line Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Minimum Line
                                Length</label>
                            <input
                                wire:model.live.debounce.500ms="minLineLength"
                                type="number"
                                min="3"
                                max="50"
                                class="px-3 py-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Minimum
                                Duplicates</label>
                            <input
                                wire:model.live.debounce.500ms="minDuplicateCount"
                                type="number"
                                min="2"
                                max="20"
                                class="px-3 py-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Maximum Results</label>
                            <input
                                wire:model.live.debounce.500ms="maxDuplicates"
                                type="number"
                                min="5"
                                max="50"
                                class="px-3 py-1.5 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-xs">
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Statistics card -->
        @if ($statistics)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Version Statistics</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Lines</div>
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($statistics['total_lines']) }}</div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Unique Texts</div>
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($statistics['unique_texts']) }}</div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Duplication Ratio</div>
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($statistics['duplication_ratio'], 2) }}
                            x
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Space Saved</div>
                        <div
                            class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($statistics['space_efficiency'], 1) }}
                            %
                        </div>
                        <div
                            class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($statistics['estimated_saved_kb']) }}
                            KB
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Results Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6">
            <!-- Top Duplicates View -->
            @if ($showDuplicates)
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Top Duplicated Lines {{ $gameId ? 'in Selected Game' : 'Across All Games' }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Showing lines that appear at least {{ $minDuplicateCount }} times, with a minimum length
                        of {{ $minLineLength }} characters.
                    </p>
                </div>

                @if ($topDuplicates->isEmpty())
                    <div
                        class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <p class="text-yellow-700 dark:text-yellow-500">
                            No duplicate lines found matching your criteria. Try adjusting the minimum line length or
                            duplicate count.
                        </p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ($topDuplicates as $duplicate)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        Appears {{ $duplicate->usage_count }} times
                                    </div>
                                    <div
                                        class="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200 text-xs rounded-full">
                                        {{ strlen($duplicate->text_content) }} characters
                                    </div>
                                </div>

                                <div
                                    class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg text-gray-900 dark:text-gray-100 mb-3 border border-gray-200 dark:border-gray-700">
                                    {{ $duplicate->text_content }}
                                </div>

                                <div class="mt-3">
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Examples:
                                    </div>
                                    <div class="space-y-2">
                                        @foreach ($duplicate->examples as $example)
                                            <div
                                                class="text-xs p-2 bg-white dark:bg-gray-800 rounded-lg text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                <div class="flex justify-between">
                                                    <span class="font-medium">{{ $example->game_name }} ({{ $example->version }})</span>
                                                    <span>{{
                                                        $example->character_id === 'menu_choice'
                                                            ? 'Choice'
                                                            : ($example->character_display_name ?? $example->character_id)
                                                    }}</span>
                                                </div>
                                                @if ($example->context)
                                                    <div class="text-gray-500 dark:text-gray-400 mt-1">
                                                        Context: {{ $example->context }}
                                                    </div>
                                                @endif
                                                <div class="text-gray-500 dark:text-gray-400 mt-1">
                                                    {{ $example->file_path }}:{{ $example->line_number }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Search Results -->
            @elseif ($searchQuery && $searchResults->total() > 0)
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Search Results: {{ $searchResults->total() }} matches for "{{ $searchQuery }}"
                    </h3>
                </div>

                <!-- Group by context view -->
                @if ($groupByContext && $groupedResults->count() > 0)
                    @foreach ($groupedResults as $context => $lines)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-4">
                            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">
                                {{ $context ?: 'No Context' }}
                            </h4>

                            <div class="space-y-3">
                                @foreach ($lines as $line)
                                    <div
                                        class="bg-white dark:bg-gray-800 shadow-xs rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                        <div class="flex items-start">
                                            @if ($line->character && $line->character->character_id != 'narrator' && $line->character->character_id != 'menu_choice')
                                                <div class="flex-shrink-0 mr-3">
                                                    <div
                                                        class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                                                        <span class="text-blue-800 dark:text-blue-200 font-medium">
                                                            {{ substr($line->character->character_id, 0, 1) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium
                                                    {{ $line->character->character_id == 'narrator' ? 'text-gray-600 dark:text-gray-400 italic' :
                                                      ($line->character->character_id == 'menu_choice' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400') }}">
                                                    {{ $line->character->character_id == 'menu_choice'
                                                      ? 'Choice'
                                                      : ($line->character->getDisplayName($line->iso_code) ?? $line->character->character_id) }}
                                                </p>

                                                <div class="text-gray-900 dark:text-gray-100 mt-1">
                                                    @if (isset($line->highlighted_text))
                                                        {!! $line->highlighted_text !!}
                                                    @else
                                                        {{ $line->text_content }}
                                                    @endif
                                                </div>

                                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                    <span
                                                        class="font-medium">{{ $line->gameVersion->game->name }}</span>
                                                    ({{ $line->gameVersion->version }}) -
                                                    {{ $line->file_path }}:{{ $line->line_number }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <!-- Flat view -->
                    <div class="space-y-3">
                        @foreach ($searchResults as $line)
                            <div
                                class="bg-white dark:bg-gray-800 shadow-xs rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-start">
                                    @if ($line->character && $line->character->character_id != 'narrator' && $line->character->character_id != 'menu_choice')
                                        <div class="flex-shrink-0 mr-3">
                                            <div
                                                class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                                                <span class="text-blue-800 dark:text-blue-200 font-medium">
                                                    {{ substr($line->character->character_id, 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start">
                                            <p class="text-sm font-medium
                                                {{ $line->character->character_id == 'narrator' ? 'text-gray-600 dark:text-gray-400 italic' :
                                                  ($line->character->character_id == 'menu_choice' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400') }}">
                                                {{ $line->character->character_id == 'menu_choice'
                                                  ? 'Choice'
                                                  : ($line->character->getDisplayName($line->iso_code) ?? $line->character->character_id) }}
                                            </p>

                                            @if ($line->context)
                                                <span
                                                    class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                                    {{ $line->context }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="text-gray-900 dark:text-gray-100 mt-1">
                                            @if (isset($line->highlighted_text))
                                                {!! $line->highlighted_text !!}
                                            @else
                                                {{ $line->text_content }}
                                            @endif
                                        </div>

                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">{{ $line->gameVersion->game->name }}</span>
                                            ({{ $line->gameVersion->version }}) -
                                            {{ $line->file_path }}:{{ $line->line_number }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $searchResults->links(data: ['scrollTo' => false]) }}
                </div>
            @elseif ($searchQuery)
                <div
                    class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <p class="text-yellow-700 dark:text-yellow-500">No results found for "{{ $searchQuery }}"</p>
                </div>
            @else
                <div
                    class="bg-gray-50 dark:bg-gray-700/30 border border-gray-200 dark:border-gray-700 rounded-lg p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ $showDuplicates
                            ? 'Adjust the settings above to find duplicated dialogue lines'
                            : 'Enter a search term to find dialogue or use the "Show Duplicates" button to see repeated lines' }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
