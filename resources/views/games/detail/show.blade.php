<div class="bg-gray-100 dark:bg-gray-900">
    @if ($game->custom_css)
        <style id="custom_css">
            .game_description img {
                display: initial;
            }
            {!! $game->custom_css !!}
        </style>
    @endif
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
                @if ($game->hasAdditionalLinks())
                    <a href="#downloads"
                       class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        Downloads
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
                        <div class="flex flex-col sm:flex-row gap-2">
                            <a href="{{ $game->url }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                Visit Game Page
                            </a>
                        </div>
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

                            @if ($game->is_suspended)
                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    Suspended
                                </span>
                            @endif

                            @if ($game->is_on_sale)
                                <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded-full">
                                    Sale
                                    @if (isset($game->discount_percentage))
                                        -{{ $game->discount_percentage }}%
                                    @endif
                                </span>
                            @endif

                            @if ($game->is_paid)
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                                    @if ($game->is_on_sale && isset($game->original_price))
                                        <span class="line-through text-blue-500 dark:text-blue-400">${{ number_format($game->original_price, 2) }}</span>
                                        ${{ number_format($game->min_price, 2) }}
                                    @else
                                        {{ $game->min_price > 0 ? '$'.number_format($game->min_price, 2) : 'Paid' }}
                                    @endif
                                </span>
                            @endif

                            @if ($game->has_demo)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">
                                    Demo
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

                    <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300 game_description">
                        <div class="inner_column size_very_large family_grandstander" id="inner_column">
                            @if ($game->full_description)
                                {!! $game->full_description !!}
                            @else
                                {!! $game->description !!}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- VN List Buttons - Compact Version -->
                        <x-lists::list-buttons :game="$game" :userLists="$userLists ?? null" :publicLists="$publicLists ?? null" />
                    </div>
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
                            'Price' => $game->is_paid ? ($game->min_price > 0 ? '$'.number_format($game->min_price, 2) : 'Paid') : 'Free',
                            'Original Price' => ($game->is_paid && $game->is_on_sale && isset($game->original_price)) ? '$'.number_format($game->original_price, 2) : null,
                            'Discount' => ($game->is_on_sale && isset($game->discount_percentage)) ? $game->discount_percentage.'%' : null,
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

                    <div class="flex flex-wrap gap-2">
                        @foreach ($game->tags as $tag)
                            <a href="{{ route('games.index', ['selectedTags' => [$tag->id]]) }}"
                               class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-full transition-colors">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                        @if ($game->custom_tags)
                            @foreach (explode(',', $game->custom_tags) as $customTag)
                                @if (trim($customTag))
                                    <span class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full">
                                        {{ trim($customTag) }}
                                    </span>
                                @endif
                            @endforeach
                        @endif
                    </div>
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
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-sm bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                                {{ $jam->name }}
                                            </span>
                                        </a>
                                    </h3>
                                    @if ($jam->start_date && $jam->end_date)
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $jam->start_date->format('M j, Y') }} - {{ $jam->end_date->format('M j, Y') }}
                                            <span class="text-gray-500 dark:text-gray-500">({{ $jam->getDurationInDays() }} days)</span>
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
                                            @if ($jam->participant_count)
                                                <span class="text-gray-500 dark:text-gray-500 ml-1">({{ number_format($jam->participant_count) }} participants)</span>
                                            @endif
                                        </p>
                                    @endif
                                    @if ($jam->pivot && $jam->pivot->ranking)
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Game Rank:</span>
                                            <span class="px-1.5 py-0.5 bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                                                {{ $jam->pivot->ranking }}
                                            </span>
                                        </p>
                                    @endif
                                    @if ($jam->pivot && $jam->pivot->criteria_rankings)
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Criteria Rankings:</span>
                                            <ul class="mt-1 ml-4 list-disc space-y-1">
                                                @foreach (json_decode($jam->pivot->criteria_rankings, true) ?? [] as $criteria => $details)
                                                    <li>
                                                        <span class="font-medium">{{ $criteria }}:</span>
                                                        @if (is_array($details))
                                                            {{ $details['rank'] ?? '' }}
                                                            @if (isset($details['score']))
                                                                <span class="text-xs px-1 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded">(Score: {{ $details['score'] }})</span>
                                                            @endif
                                                        @else
                                                            {{ $details }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    @if ($jam->host)
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                                            Hosted by {{ $jam->host }}
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

                @if ($game->blur_screenshots)
                    <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Content Warning</h3>
                                <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                    <p>Screenshots are blurred as they may contain sensitive or NSFW content. Click on any screenshot to view it in full.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="screenshots-gallery">
                    @foreach ($screenshots as $index => $screenshot)
                        <a href="{{ $screenshot['url'] }}" data-title="Screenshot {{ $index + 1 }}" class="block overflow-hidden rounded-lg hover:opacity-90 transition-opacity">
                            <img
                                src="{{ $screenshot['thumbnail_url'] }}"
                                alt="Screenshot {{ $index + 1 }}"
                                class="w-full h-auto object-cover {{ $game->blur_screenshots ? 'blur-sm hover:blur-none transition-all duration-300' : '' }}"
                            >
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Downloads (Full Width) --}}
        @if ($game->hasAdditionalLinks())
            <div id="downloads" class="bg-white dark:bg-gray-800 rounded-lg shadow-xs p-6 mb-6 scroll-mt-14">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-6">
                    Downloads
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($game->additional_links as $link)
                        <a href="{{ $link['url'] }}" target="_blank"
                           class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-blue-300 dark:hover:border-blue-500 transition-all duration-200 group">
                            <div class="flex-shrink-0">
                                @if (!empty($link['platform']))
                                    @switch($link['platform'])
                                        @case('windows')
                                            <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-13.051-1.851"/>
                                                </svg>
                                            </div>
                                            @break
                                        @case('mac')
                                            <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                                                </svg>
                                            </div>
                                            @break
                                        @case('linux')
                                            <div class="p-2 bg-orange-50 dark:bg-orange-900/30 rounded-lg">
                                                <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12.504 0c-.155 0-.315.008-.48.021-4.226.333-3.105 4.807-3.17 6.298-.076 1.092-.3 1.953-1.05 3.02-.885 1.051-2.127 2.75-2.716 4.521-.278.832-.41 1.684-.287 2.489a.424.424 0 00-.11.135c-.26.268-.45.6-.663.839-.199.199-.485.267-.797.4-.313.136-.658.269-.864.68-.09.189-.136.394-.132.602 0 .199.027.4.055.536.058.399.116.728.04.97-.249.68-.28 1.145-.106 1.484.174.334.535.47.94.601.81.2 1.91.135 2.774.6.926.466 1.866.67 2.616.47.526-.116.97-.464 1.208-.946.587-.003 1.23-.269 2.26-.334.699-.058 1.574.267 2.577.2.025.134.063.198.114.333l.003.003c.391.778 1.113 1.132 1.884 1.071.771-.06 1.592-.536 2.257-1.306.631-.765 1.683-1.084 2.378-1.503.348-.199.629-.469.649-.853.023-.4-.2-.811-.714-1.376v-.097l-.003-.003c-.17-.2-.25-.535-.338-.926-.085-.401-.182-.786-.492-1.046h-.003c-.059-.054-.123-.067-.188-.135a.357.357 0 00-.19-.064c.431-1.278.264-2.55-.173-3.694-.533-1.41-1.465-2.638-2.175-3.483-.796-1.005-1.576-1.957-1.56-3.368.026-2.152.236-6.133-3.544-6.139zm.529 3.405h.013c.213 0 .396.062.584.198.19.135.33.332.438.533.105.259.158.459.166.724 0-.02.006-.04.006-.06v.105a.086.086 0 01-.004-.021l-.004-.024a1.807 1.807 0 01-.15.706.953.953 0 01-.213.335.71.71 0 00-.088-.042c-.104-.045-.198-.064-.284-.133a1.312 1.312 0 00-.22-.066c-.05-.024-.111-.04-.173-.065-.049-.021-.1-.04-.15-.06-.056-.014-.111-.027-.16-.062a.65.65 0 01-.248-.158.625.625 0 01-.162-.409c0-.021-.003-.042-.003-.063v-.02c0-.283.108-.54.249-.742.14-.202.293-.314.465-.314z"/>
                                                </svg>
                                            </div>
                                            @break
                                        @case('android')
                                            <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993.0001.5511-.4482.9997-.9993.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993 0 .5511-.4482.9997-.9993.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5902 8.2439 13.8533 7.8508 12 7.8508s-3.5902.3931-5.1367 1.0989L4.841 5.4467a.4161.4161 0 00-.5677-.1521.4157.4157 0 00-.1521.5676l1.9973 3.4592C2.6889 11.1867.3432 14.6589 0 18.761h24c-.3435-4.1021-2.6892-7.5743-6.1185-9.4396"/>
                                                </svg>
                                            </div>
                                            @break
                                        @case('ios')
                                            <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <svg class="h-6 w-6 text-gray-700 dark:text-gray-300" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                                                </svg>
                                            </div>
                                            @break
                                        @case('web')
                                            <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                                </svg>
                                            </div>
                                            @break
                                        @default
                                            <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <svg class="h-6 w-6 text-gray-600 dark:text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/>
                                                </svg>
                                            </div>
                                    @endswitch
                                @else
                                    <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <svg class="h-6 w-6 text-gray-600 dark:text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 mb-1">
                                    {{ $link['name'] }}
                                </div>
                                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    @if (!empty($link['platform']))
                                        <span class="capitalize font-medium">{{ $link['platform'] }}</span>
                                    @endif
                                    @if (!empty($link['last_edited_at']))
                                        @if (!empty($link['platform']))
                                            <span>•</span>
                                        @endif
                                        <span>Updated {{ \Carbon\Carbon::parse($link['last_edited_at'])->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-gray-400 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </div>
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

                <div class="mt-4 flex flex-wrap gap-4">
                    @if ($latestVersion && $latestVersion->dialogueLines()->count() > 0)
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
                    @endif

                    <!-- Android Build Button (only for logged in users and eligible games) -->
                    @auth
                        @if ($isEligibleForAndroidBuild && $latestVersion)
                            @include('games.components.android-build-button-new', ['game' => $game, 'version' => $latestVersion])
                        @endif
                    @endauth
                </div>

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
                                        {{ $review->rater->name }}
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
