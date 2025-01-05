@php
    $latestVersion = $game->latestVersion();
    $supportedLanguages = $game->getLatestSupportedLanguages();
    $englishWordCount = $game->getEnglishWordCount();
@endphp

<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Game Header --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <div class="flex gap-6">
                <img src="{{ $game->thumb_url }}"
                     alt="{{ $game->name }}"
                     class="w-48 h-36 object-cover rounded-lg">

                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $game->name }}
                        </h1>
                        <a href="{{ $game->url }}"
                           target="_blank"
                           class="text-blue-600 dark:text-blue-400 hover:underline">
                            Visit Game Page
                        </a>
                    </div>

                    <div class="mt-2 flex items-center gap-4">
                        <x-platform-icons
                            :platforms="$game->platforms"
                            :selected-platforms="[]" />

                        @if ($game->is_nsfw)
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded-full">
                                NSFW
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 prose dark:prose-invert max-w-none">
                        {!! $game->description !!}
                    </div>
                </div>
            </div>
        </div>

        {{-- Game Details --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            {{-- Left Column: Basic Info --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Game Details
                </h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                    @foreach ([
                        'Status' => [$game->status, 'text-gray-900 dark:text-gray-100'],
                        'Engine' => $game->game_engine,
                        'Initial Release' => $game->initially_published_at?->format('M j, Y'),
                        'Latest Update' => $latestVersion?->published_at?->format('M j, Y'),
                        'Current Version' => $latestVersion?->version,
                        'Word Count (English)' => $englishWordCount ? number_format($englishWordCount) : '-',
                        'Rating' => $game->rating ? number_format($game->rating, 1) : '-',
                        'Review Count' => $game->rating_count ? number_format($game->rating_count) : '-',
                        'Languages' => $supportedLanguages->count() > 0 ? $supportedLanguages->pluck('ref_name')->join(', ') : '-',
                    ] as $label => $data)
                        @php
                            if (!is_array($data)) {
                                $data = [$data, 'text-gray-900 dark:text-gray-100'];
                            }
                        @endphp
                        @if ($data[0])
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400 text-sm">{{ $label }}</dt>
                                <dd class="{{ $data[1] }}">{{ $data[0] }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            {{-- Right Column: Tags --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Tags
                </h2>

                @if ($game->tags)
                    <div class="flex flex-wrap gap-2">
                        @foreach (explode(',', $game->tags) as $tag)
                            <span class="px-3 py-1 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full">
                                {{ trim($tag) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Game Versions --}}
        @if ($versions->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Version History
                </h2>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                        <tr class="text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Version</th>
                            <th class="px-4 py-2">Released</th>
                            <th class="px-4 py-2">Languages</th>
                            <th class="px-4 py-2">English Words</th>
                            <th class="px-4 py-2">Rating</th>
                            <th class="px-4 py-2">Reviews</th>
                            <th class="px-4 py-2">Platforms</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($versions as $version)
                            <tr class="text-sm text-gray-900 dark:text-gray-100">
                                <td class="px-4 py-2">{{ $version->version }}</td>
                                <td class="px-4 py-2">{{ $version->published_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2">
                                    {{ $version->languageStats->map(fn($stat) => $stat->language->ref_name)->join(', ') }}
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $englishStats = $version->getStatsForLanguage('eng');
                                    @endphp
                                    {{ $englishStats ? number_format($englishStats->words) : '-' }}
                                </td>
                                <td class="px-4 py-2">{{ $version->rating ? number_format($version->rating, 1) : '-' }}</td>
                                <td class="px-4 py-2">{{ $version->rating_count ?? '-' }}</td>
                                <td class="px-4 py-2">
                                    <x-platform-icons
                                        :platforms="[
                                            'windows' => $version->is_windows,
                                            'linux' => $version->is_linux,
                                            'mac' => $version->is_mac,
                                            'android' => $version->is_android,
                                            'web' => $version->is_web,
                                        ]"
                                        :selected-platforms="[]" />
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Reviews Section --}}
            @if ($reviews->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Reviews
                    </h2>

                    <div class="space-y-6">
                        @foreach ($reviews as $review)
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 last:border-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $review->rater->name }}
                                        </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $review->published_at->format('M j, Y') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 text-yellow-400">
                                        @for ($i = 0; $i < $review->rating; $i++)
                                            <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                </div>

                                <div class="prose dark:prose-invert max-w-none">
                                    {!! $review->review !!}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{ $reviews->links() }}
                </div>
            @endif
    </div>

    @include('components.meta-data-refresh')
</div>
