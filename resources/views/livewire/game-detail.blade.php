<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-4 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
            <a href="{{ route('games.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Game List
            </a>

            {{-- Section Navigation --}}
            <nav class="flex space-x-4">
                <a href="#details" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    Details
                </a>
                @if($versions->isNotEmpty())
                    <a href="#versions" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Versions
                    </a>
                @endif
                @if($reviews->isNotEmpty())
                    <a href="#reviews" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        {{ $showAllRatings ? 'Ratings' : 'Reviews' }}
                    </a>
                @endif
            </nav>
        </div>

        {{-- Game Header --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <div class="flex flex-col sm:flex-row gap-6">
                <div class="flex-shrink-0">
                    <img src="{{ $game->thumb_url }}"
                         alt="{{ $game->name }}"
                         class="object-cover rounded-lg max-w-80 max-h-64">
                </div>

                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $game->name }}
                        </h1>
                        <a href="{{ $game->url }}"
                           target="_blank"
                           class="text-blue-600 dark:text-blue-400 hover:underline">
                            Visit Game Page
                        </a>
                    </div>

                    <div class="mt-4 sm:mt-2 flex flex-wrap items-center gap-4">
                        <x-platform-icons
                            :platforms="[
                        'windows' => $game->is_windows,
                        'linux' => $game->is_linux,
                        'mac' => $game->is_mac,
                        'android' => $game->is_android,
                        'web' => $game->is_web,
                    ]"
                            :selected-platforms="[]"
                            :clickable="false"/>

                        @if ($game->is_nsfw)
                            <span
                                class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">
                        NSFW
                    </span>
                        @endif
                    </div>

                    @if ($game->authors)
                        <div class="mt-4 sm:mt-2 text-gray-600 dark:text-gray-300">
                            {!! $game->authors !!}
                        </div>
                    @endif

                    <div class="mt-4 prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                        {!! $game->description !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- Game Details --}}
        <div id="details" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 scroll-mt-14">
            {{-- Left Column: Basic Info --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
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
                        'Rating' => $game->rating ? number_format($game->rating, 1) : '-',
                        'Review Count' => $game->rating_count ? number_format($game->rating_count) : '-',
                    ] as $label => $value)
                        @if ($value)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400 text-sm">{{ $label }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if($languageStats && $languageStats->isNotEmpty())
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Supported Languages</h3>
                        <x-language-flags
                            :languages="$languageStats->map(fn($stat) => [
                                'iso_code' => $stat->iso_code,
                                'ref_name' => $stat->language->ref_name,
                                'flag_code' => $stat->language->flag_code
                            ])"
                            :selected-languages="[]"
                            :show-labels="false"
                            :clickable="false"
                        />
                    </div>
                @endif
            </div>

            {{-- Right Column: Tags --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
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
        </div>

        {{-- Game Versions --}}
        @if ($versions->isNotEmpty())
            <div id="versions" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6 scroll-mt-14">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Version History
                </h2>

                <div class="space-y-4">
                    @foreach ($versions as $version)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
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
                                        <x-language-flags
                                            :languages="$version->languageStats->map(fn($stat) => [
                                        'iso_code' => $stat->iso_code,
                                        'ref_name' => $stat->language->ref_name,
                                        'flag_code' => $stat->language->flag_code
                                    ])"
                                            :selected-languages="[]"
                                            :clickable="false"
                                        />
                                    </div>

                                    {{-- Platforms --}}
                                    <div class="w-full flex items-center">
                                        <x-platform-icons
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
                                        @if($version->rating_count)
                                            <span class="ml-1 text-gray-500">({{ $version->rating_count }})</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <x-filters.select
                        wire:model.live="versionsPerPage"
                        :value="$versionsPerPage"
                        :options="[
                            5 => '5 per page',
                            10 => '10 per page',
                            25 => '25 per page'
                        ]"
                        class="w-full sm:w-auto"
                    />
                    {{ $versions->links(data: ['scrollTo' => '#versions']) }}
                </div>
            </div>
        @endif

        {{-- Reviews Section --}}
        @if ($reviews->isNotEmpty())
            <div id="reviews" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 scroll-mt-14">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $showAllRatings ? 'Ratings' : 'Reviews' }}
                        </h2>
                        @if($availableRatings->isNotEmpty())
                            <x-filters.select
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
                                        <a target="_blank"
                                           href="https://itch.io/event/{{ $review->event_id }}">{{ $review->rater->id }}</a>
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $review->published_at->format('M j, Y') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 text-yellow-400">
                                    @for ($i = 0; $i < $review->rating; $i++)
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                            </div>

                            @if($review->review && (!$showAllRatings || $review->is_reviewed))
                                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                    {!! $review->review !!}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <x-filters.select
                        wire:model.live="reviewsPerPage"
                        :value="$reviewsPerPage"
                        :options="[
                            5 => '5 per page',
                            10 => '10 per page',
                            25 => '25 per page'
                        ]"
                        class="w-full sm:w-auto"
                    />
                    {{ $reviews->links(data: ['scrollTo' => '#reviews']) }}
                </div>
            </div>
        @endif
    </div>

    @include('components.meta-data-refresh')
</div>
