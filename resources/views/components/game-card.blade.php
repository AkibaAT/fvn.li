@props(['game', 'selectedStatuses' => [], 'selectedEngines' => [], 'selectedPlatforms' => [], 'selectedLanguages' => [], 'nsfw' => false, 'sfw' => false])

<div
    class="relative bg-white dark:bg-gray-800/50 rounded-lg shadow-sm p-4 flex flex-col backdrop-blur-xs border border-gray-200 dark:border-transparent transition-all duration-150">
    <div class="flex gap-4">
        <a href="{{ route('games.show', $game) }}">
            <img
                src="{{ $game->thumb_url }}"
                alt="{{ $game->name }}"
                class="h-24 w-32 object-cover rounded-sm"
            >
        </a>
        <div class="flex flex-col min-w-0 flex-1">
            <div class="min-w-0 flex items-top gap-2">
                <a href="{{ route('games.show', $game) }}"
                   class="text-base font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400 line-clamp-2">
                    {{ $game->name }}
                </a>
                <a href="{{ $game->url }}"
                   target="_blank"
                   class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                   title="Open on itch.io">
                    <i class="icon-external-link"></i>
                </a>
            </div>

            @if ($game->authors)
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{!! $game->authors !!}</p>
            @endif

            <div class="flex flex-col gap-2 mt-2">
                <div class="min-w-0">
                    @if ($game->is_nsfw)
                        <button
                            wire:click="$toggle('nsfw')"
                            @class([
                                'shrink-0 text-xs px-1.5 py-0.5 rounded-sm cursor-pointer transition-colors',
                                'bg-red-200 text-red-800 dark:bg-red-800/50 dark:text-red-200/90 ring-2 ring-red-500 dark:ring-red-500' => $nsfw,
                                'bg-red-100 text-red-700 dark:bg-red-800/50 dark:text-red-300 hover:bg-red-200 hover:text-red-800 dark:hover:bg-red-800/50 dark:hover:text-red-300' => !$nsfw,
                            ])>
                            NSFW
                        </button>
                    @else
                        <button
                            wire:click="$toggle('sfw')"
                            @class([
                                'shrink-0 text-xs px-1.5 py-0.5 rounded-sm cursor-pointer transition-colors',
                                'bg-green-200 text-green-800 dark:bg-green-800/50 dark:text-green-200/90 ring-2 ring-green-500 dark:ring-green-500' => $sfw,
                                'bg-green-100 text-green-700 dark:bg-green-800/50 dark:text-green-300 hover:bg-green-200 hover:text-green-800 dark:hover:bg-green-800/50 dark:hover:text-green-300' => !$sfw,
                            ])>
                            SFW
                        </button>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <x-platform-icons :platforms="$game->platforms" :selected-platforms="$selectedPlatforms"/>
                </div>

                @if ($game->supported_languages && $game->supported_languages->isNotEmpty())
                    @php
                        // Pre-decode the JSON if it's a string (from the aggregation)
                        $languages = is_string($game->supported_languages)
                            ? collect(json_decode($game->supported_languages, true))->sortBy('ref_name')->values()->all()
                            : $game->supported_languages->sortBy('ref_name')->values()->toArray();
                    @endphp
                    <x-language-flags
                        :languages="$languages"
                        :selected-languages="$selectedLanguages"
                    />
                @endif
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 text-sm border-t border-gray-100 dark:border-gray-700/50 pt-4">
        @foreach ([
            ['label' => 'Status', 'value' => $game->status, 'type' => 'status', 'isActive' => in_array($this->encodeFilterValue($game->status), $selectedStatuses ?? [])],
            ['label' => 'Engine', 'value' => $game->game_engine, 'type' => 'engine', 'isActive' => in_array($this->encodeFilterValue($game->game_engine), $selectedEngines ?? [])],
            ['label' => 'Words (EN)', 'value' => number_format($game->english_word_count ?? 0) ?: '-', 'isFilter' => false],
            ['label' => 'Reviews', 'value' => $game->rating_count ?? '-', 'isFilter' => false],
            ['label' => 'Released', 'value' => $game->initially_published_at?->format('M j, Y') ?? '-', 'isFilter' => false],
            ['label' => 'Updated', 'value' => $game->latest_version_published_at?->format('M j, Y') ?? '-', 'isFilter' => false],
        ] as $detail)
            <div>
                <span class="text-gray-500 dark:text-gray-400">{{ $detail['label'] }}:</span>
                @if ($detail['isFilter'] ?? true)
                    <button
                        wire:click="toggleFilter('{{ $detail['type'] }}', '{{ $this->encodeFilterValue($detail['value']) }}')"
                        @class([
                            'ml-1 hover:text-blue-400',
                            'text-blue-400 font-medium' => $detail['isActive'] ?? false,
                            'text-gray-700 dark:text-gray-200' => !$detail['isActive'] ?? true,
                        ])>
                        {{ $detail['value'] }}
                    </button>
                @else
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ $detail['value'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    @if ($game->tags)
        <div class="mt-4 flex flex-wrap gap-1.5">
            @foreach (explode(',', $game->tags) as $tag)
                <span
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-white dark:bg-gray-700/50 text-gray-600 dark:text-gray-200 border border-gray-200 dark:border-gray-600/50 hover:bg-gray-50 dark:hover:bg-gray-600/50 transition-colors">
                    {{ trim($tag) }}
                </span>
            @endforeach
        </div>
    @endif
</div>
