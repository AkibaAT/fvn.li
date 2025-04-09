@if ($game->custom_css)
    <style id="custom_css">
        {!! $game->custom_css !!}
    </style>
@endif

<div class="bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
        <div class="mb-5 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
            <a href="{{ route('games.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Game List
            </a>

            {{-- Section Navigation --}}
            <nav class="flex space-x-4">
                @if ($game->is_visible)
                    <a href="#details"
                       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Details
                    </a>
                @endif
                @if (count($screenshots) > 0)
                    <a href="#screenshots"
                       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Screenshots
                    </a>
                @endif
                @if ($versions->isNotEmpty())
                    <a href="#versions"
                       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Versions
                    </a>
                @endif
                <a href="#reviews"
                   class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    {{ $showAllRatings ? 'Ratings' : 'Reviews' }}
                </a>
            </nav>
        </div>

        {{-- Game Header --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-6">
                @if ($game->is_visible && $game->thumb_url)
                    <div class="shrink-0">
                        <x-game-thumbnail :game="$game" variant="default" class="object-cover rounded-lg max-w-64 max-h-52"/>
                    </div>
                @endif

                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $game->name }}
                        </h1>
                        <a href="{{ $game->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                            Visit Game Page
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                        <div class="flex items-center gap-4">
                            <x-games::platform-icons
                                :platforms="$platforms"
                                :selected-platforms="[]"
                                :clickable="false"/>

                            @if ($game->is_nsfw)
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">
                                    NSFW
                                </span>
                            @endif
                        </div>

                        {{-- List Tags and Notifications --}}
                        @auth
                            <div class="flex items-center gap-4">
                                @php
                                    $defaultList = Auth::user()->vnLists()
                                        ->where('is_default', true)
                                        ->whereHas('entries', function($query) use ($game) {
                                            $query->where('game_id', $game->id);
                                        })
                                        ->first();
                                @endphp
                                <div data-list-tags="{{ $game->id }}" class="flex gap-2">
                                    @if ($defaultList)
                                        <span
                                            data-list-type="{{ $defaultList->type }}"
                                            class="px-2 py-1 text-xs font-semibold rounded-full
                                                @if ($defaultList->type === 'reading')
                                                    bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                @elseif ($defaultList->type === 'completed')
                                                    bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                                @elseif ($defaultList->type === 'plan_to_read')
                                                    bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                                @elseif ($defaultList->type === 'on_hold')
                                                    bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200
                                                @elseif ($defaultList->type === 'dropped')
                                                    bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                                @endif"
                                        >
                                            {{ ucwords(str_replace('_', ' ', $defaultList->type)) }}
                                        </span>
                                        @if ($defaultList->is_public)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                Public
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                {{-- Notification Toggle --}}
                                <x-games::notification-toggle :game="$game" />
                            </div>
                        @endauth
                    </div>

                    @if ($game->authors)
                        <div class="mb-3 text-gray-600 dark:text-gray-300">
                            {!! $game->authors !!}
                        </div>
                    @endif

                    <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300 game-description-container">
                        @if ($game->full_description)
                            {!! $game->full_description !!}
                        @else
                            {!! $game->description !!}
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <!-- VN List Buttons - Compact Version -->
                    <x-lists::list-buttons :game="$game" :userLists="$userLists ?? null" :publicLists="$publicLists ?? null" />
                </div>
            </div>
        </div>

        {{-- Game Details --}}
        @if ($game->is_visible)
            <div id="details" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 scroll-mt-14">
                {{-- Left Column: Basic Info --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Game Details
                    </h2>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                        @foreach ([
                            'Status' => $game->status,
                            'Engine' => $game->game_engine,
                            'Initial Release' => $game->initially_published_at?->format('M j, Y'),
                            'Latest Update' => $latestVersion?->published_at?->format('M j, Y'),
                            'Current Version' => $latestVersion?->version,
                            'Word Count (English)' => $englishStats?->words ? number_format($englishStats->words) : '-',
                            'Characters' => $versionCharacterCounts[$latestVersion?->id] ?? '-',
                            'Rating' => $game->rating ? number_format($game->rating, 1) : '-',
                            'Review Count' => $game->rating_count ? number_format($game->rating_count) : '-',
                            'Price' => $hasPrice ? ('$' . number_format($minPrice, 2) . ($isOnSale ? ' (On Sale)' : '')) : 'Free',
                        ] as $label => $value)
                            @if ($value)
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400 text-sm">{{ $label }}</dt>
                                    <dd class="text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if ($supportedLanguages && $supportedLanguages->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="text-gray-500 dark:text-gray-400 text-sm mb-2">Supported Languages</h3>
                            <x-games::language-flags
                                :languages="$supportedLanguages->sortBy('ref_name')->values()"
                                :selected-languages="[]"
                                :show-labels="false"
                                :clickable="false"
                            />
                        </div>
                    @endif
                </div>

                {{-- Right Column: Tags --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Tags
                    </h2>

                    @if ($game->tags || $game->custom_tags)
                        <div class="flex flex-wrap gap-2">
                            @foreach (array_merge(
                                $game->tags ? explode(',', $game->tags) : [],
                                $game->custom_tags ? explode(',', $game->custom_tags) : []
                            ) as $tag)
                                <span
                                    class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full">
                                    {{ trim($tag) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Game Jams --}}
                @if ($gameJams->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Game Jams
                        </h2>
                        <div class="space-y-4">
                            @foreach ($gameJams as $jam)
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 last:border-0 last:pb-0">
                                    <h3 class="font-medium text-gray-900 dark:text-gray-100">
                                        <a href="{{ $jam->url }}" target="_blank" class="hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $jam->name }}
                                        </a>
                                    </h3>
                                    @if ($jam->start_date && $jam->end_date)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $jam->start_date->format('M j, Y') }} - {{ $jam->end_date->format('M j, Y') }}
                                        </p>
                                    @endif
                                    @if ($jam->theme)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Theme:</span> {{ $jam->theme }}
                                        </p>
                                    @endif
                                    @if ($jam->submission_count)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Submissions:</span> {{ number_format($jam->submission_count) }}
                                        </p>
                                    @endif
                                    @if ($jam->pivot && $jam->pivot->ranking)
                                        <p class="text-sm font-medium text-green-600 dark:text-green-400 mt-1">
                                            {{ $jam->pivot->ranking }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif


            </div>
        @endif

        {{-- Screenshots Gallery (Full Width) --}}
        @if (count($screenshots) > 0)
            <div id="screenshots" class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6 scroll-mt-14">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Screenshots
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="screenshots-gallery">
                    @foreach ($screenshots as $index => $screenshot)
                        <a href="{{ $screenshot['url'] }}" data-title="Screenshot {{ $index + 1 }}" class="block overflow-hidden rounded-lg hover:opacity-90 transition-opacity">
                            <img src="{{ $screenshot['thumbnail_url'] }}" alt="Screenshot {{ $index + 1 }}" class="w-full h-auto object-cover">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($versions->isNotEmpty())
            <div id="versions" class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6 scroll-mt-14">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Version History
                </h2>

                @if ($latestVersion && $latestVersion->dialogueLines()->count() > 0)
                    <div class="mt-4 flex gap-4">
                        <a href="{{ route('dialogue.browser', ['gameId' => $game->id, 'versionId' => $latestVersion->id]) }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-300 disabled:opacity-25 transition">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                            </svg>
                            Browse Dialogue
                        </a>
                        <a href="{{ route('dialogue.browser', ['gameId' => $game->id, 'versionId' => $latestVersion->id, 'showDuplicates' => true]) }}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            View Duplicate Lines
                        </a>
                    </div>
                @endif

                <div class="mt-6 mb-4">
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 my-3">
                        <h3 class="text-base font-medium text-gray-100 mb-3">Compare Versions</h3>
                        <form class="flex flex-col gap-4 sm:flex-row items-end">
                            <div>
                                <label for="compareFromVersionId" class="block text-sm font-medium text-gray-400 mb-1">From Version</label>
                                <select
                                    id="compareFromVersionId"
                                    wire:model.live="compareFromVersionId"
                                    class="border px-4 py-2 rounded-lg border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-full sm:w-auto"
                                >
                                    <option value="">Select version...</option>
                                    @foreach ($versions as $version)
                                        @if ($versionCharacterCounts[$version->id] > 0)
                                            <option value="{{ $version->id }}">{{ $version->version }} ({{ $version->published_at->format('M j, Y') }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="compareToVersionId" class="block text-sm font-medium text-gray-400 mb-1">To Version</label>
                                <select
                                    id="compareToVersionId"
                                    wire:model.live="compareToVersionId"
                                    class="border px-4 py-2 rounded-lg border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 w-full sm:w-auto"
                                >
                                    <option value="">Select version...</option>
                                    @foreach ($versions as $version)
                                        @if ($versionCharacterCounts[$version->id] > 0)
                                            <option value="{{ $version->id }}">{{ $version->version }} ({{ $version->published_at->format('M j, Y') }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    onclick="compareGameVersions('{{ $compareFromVersionId }}', '{{ $compareToVersionId }}', '{{ $game->id }}')"
                                    class="inline-flex items-center px-4 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
                                    @if (!$compareFromVersionId || !$compareToVersionId) disabled @endif
                                >
                                    COMPARE
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div>
                    @foreach ($versions as $version)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 my-3">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <div
                                    class="flex-1 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                    <div class="w-full flex items-center">
                                        <div
                                            class="font-medium text-gray-900 dark:text-gray-100">{{ $version->published_at->format('M j, Y') }}</div>
                                    </div>

                                    <div class="w-full flex items-center">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            Version {{ $version->version }}</div>
                                    </div>

                                    {{-- Languages --}}
                                    <div class="w-full flex items-center">
                                        <x-games::language-flags
                                            :languages="$version->supportedLanguages
                                                ->where('is_available', true)
                                                ->map(fn($sl) => [
                                                    'iso_code' => $sl->iso_code,
                                                    'ref_name' => $sl->language->ref_name,
                                                    'flag_code' => $sl->language->flag_code
                                                ])
                                                ->sortBy('ref_name')
                                                ->values()"
                                            :selected-languages="[]"
                                            :clickable="false"
                                        />
                                    </div>

                                    {{-- Platforms --}}
                                    <div class="w-full flex items-center">
                                        <x-games::platform-icons
                                            :platforms="[
                                        'windows' => $version->is_windows,
                                        'linux' => $version->is_linux,
                                        'mac' => $version->is_mac,
                                        'android' => $version->is_android,
                                        'web' => $version->is_web,
                                    ]"
                                            :selected-platforms="[]"
                                            :clickable="false"
                                        />
                                    </div>

                                    {{-- Word count --}}
                                    @php
                                        $englishStats = $version->getStatsForLanguage('eng');
                                    @endphp
                                    <div class="w-full flex items-center whitespace-nowrap text-sm">
                                        <span class="text-gray-500">Words:</span>
                                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                                    {{ $englishStats && $englishStats->words ? number_format($englishStats->words) : '-' }}
                                </span>
                                    </div>

                                    {{-- Rating --}}
                                    <div class="w-full flex items-center whitespace-nowrap text-sm">
                                        <span class="text-gray-500">Rating:</span>
                                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                                    {{ $version->rating ? number_format($version->rating, 1) : '-' }}
                                </span>
                                        @if ($version->rating_count)
                                            <span class="ml-1 text-gray-500">({{ $version->rating_count }})</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if ($versionCharacterCounts[$version->id] > 0)
                                <button
                                    wire:click="showCharacterStats({{ $version->id }})"
                                    class="text-blue-600 dark:text-blue-400 hover:underline text-sm mr-5"
                                >
                                    View {{ $versionCharacterCounts[$version->id] }} Characters
                                </button>
                            @endif
                            @if ($version->fileCategories->isNotEmpty())
                                <button
                                    wire:click="showFileStats({{ $version->id }})"
                                    class="text-blue-600 dark:text-blue-400 hover:underline text-sm"
                                    title="View file statistics"
                                >
                                    View File Stats
                                </button>
                            @endif
                        </div>

                        <!-- Character Stats Dialog -->
                        <dialog
                            wire:ignore.self
                            id="character-stats-{{ $version->id }}"
                            class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl min-w-80 max-w-6xl dark:text-gray-100 backdrop:backdrop-blur-md"
                        >
                            <x-ui.dialog-header title="Character Statistics"/>

                            @if ($selectedVersionId === $version->id && $characterStats)
                                <div class="overflow-x-auto max-w-[calc(100vw-3rem)] -mx-6 px-6">
                                    <table class="w-full text-sm">
                                        <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="text-left py-2 px-3 font-medium">Character</th>
                                            @foreach ($characterStats['languages'] as $lang)
                                                <th class="text-right py-2 px-3 font-medium">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <span class="fi fi-{{ $lang['flag'] }} rounded-xs"></span>
                                                        <span>{{ $lang['name'] }}</span>
                                                    </div>
                                                </th>
                                            @endforeach
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($characterStats['characters'] as $character)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="py-2 px-3">{{ $character }}</td>
                                                @foreach ($characterStats['languages'] as $lang)
                                                    <td class="py-2 px-3 text-right tabular-nums">
                                                        {{ isset($characterStats['wordCounts'][$character][$lang['id']])
                                                            ? number_format($characterStats['wordCounts'][$character][$lang['id']])
                                                            : '-' }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        </tbody>
                                        <tfoot class="border-t border-gray-200 dark:border-gray-700 font-medium">
                                        <tr>
                                            <td class="py-2 px-3">Total</td>
                                            @foreach ($characterStats['languages'] as $lang)
                                                <td class="py-2 px-3 text-right tabular-nums">
                                                    {{ number_format($characterStats['languageTotals'][$lang['id']] ?? 0) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="flex items-center justify-center p-4">
                                    <div
                                        class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 dark:border-gray-100"></div>
                                </div>
                            @endif

                            <x-ui.dialog-footer/>
                        </dialog>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <x-ui.select
                        wire:model.live="versionsPerPage"
                        :value="$versionsPerPage"
                        :options="[
                            5 => '5 per page',
                            10 => '10 per page',
                            25 => '25 per page'
                        ]"
                        placeholder=""
                        class="w-full sm:w-auto"
                    />
                    {{ $versions->links(data: ['scrollTo' => '#versions']) }}
                </div>
            </div>
        @endif

        {{-- Reviews Section --}}
        <div id="reviews" class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 scroll-mt-14">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $showAllRatings ? 'Ratings' : 'Reviews' }}
                    </h2>
                    @if ($availableRatings->isNotEmpty())
                        <x-ui.select
                            wire:model.live="selectedRating"
                            :value="$selectedRating"
                            :options="$availableRatings->mapWithKeys(fn($rating) => [$rating => $rating.' Star' . ($rating !== 1 ? 's' : '')])->all()"
                            placeholder="Any Stars"
                            class="w-40"
                        />
                    @endif
                </div>
                <button
                    wire:click="toggleRatingsView"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                >
                    Show {{ $showAllRatings ? 'reviews only' : 'all ratings' }}
                </button>
            </div>

            <div class="space-y-6">
                @foreach ($reviews as $review)
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-6 last:border-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    <a href="{{ route('raters.show', $review->rater) }}" class="hover:underline">
                                        {{ $review->rater->alias }}
                                    </a>
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $review->published_at->format('M j, Y') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1 text-yellow-400">
                                    @for ($i = 0; $i < $review->rating; $i++)
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <a href="https://itch.io/event/{{ $review->event_id }}"
                                   target="_blank"
                                   class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                   title="View on itch.io">
                                    <i class="icon-external-link"></i>
                                </a>
                            </div>
                        </div>

                        @if ($review->review && (!$showAllRatings || $review->is_reviewed))
                            <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                {!! $review->review !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <x-ui.select
                    wire:model.live="reviewsPerPage"
                    :value="$reviewsPerPage"
                    :options="[
                        5 => '5 per page',
                        10 => '10 per page',
                        25 => '25 per page'
                    ]"
                    placeholder=""
                    class="w-full sm:w-auto"
                />
                {{ $reviews->links(data: ['scrollTo' => '#reviews']) }}
            </div>
        </div>
    </div>

    @include('games.components.file-stats-dialog')
    <livewire:components.version-comparison />

    @include('components.ui.meta-data-refresh')

    @push('scripts')
        @vite('resources/js/list-buttons.ts')
        @vite('resources/js/toggle-notifications.ts')
        @vite('resources/js/screenshots-lightbox.ts')
    @endpush
</div>

<script>
    document.addEventListener('open-dialog', (e) => {
        document.getElementById(e.detail.dialogId).showModal();
    });

    // Close dialog when clicking outside
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.currentTarget.close();
            }
        });
    });

    // Version comparison functionality
    function compareGameVersions(fromVersionId, toVersionId, gameId) {
        Livewire.dispatch('compare-game-versions', {
            params: {
                fromVersionId: fromVersionId,
                toVersionId: toVersionId,
                gameId: gameId
            }
        });
    }

    // Helper function to show success messages
    function showSuccessMessage(message) {
        const successMessage = document.createElement('div');
        successMessage.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg z-50';
        successMessage.innerHTML = `<p>${message}</p>`;
        document.body.appendChild(successMessage);

        // Remove the message after 3 seconds
        setTimeout(() => {
            successMessage.remove();
        }, 3000);
    }

    // Helper function to show error messages
    function showErrorMessage(message) {
        const errorMessage = document.createElement('div');
        errorMessage.className = 'fixed bottom-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg z-50';
        errorMessage.innerHTML = `<p>${message}</p>`;
        document.body.appendChild(errorMessage);

        // Remove the message after 5 seconds
        setTimeout(() => {
            errorMessage.remove();
        }, 5000);
    }


</script>
