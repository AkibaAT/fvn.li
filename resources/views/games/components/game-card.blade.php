@props(['game', 'selectedStatuses' => [], 'selectedEngines' => [], 'selectedPlatforms' => [], 'selectedLanguages' => [], 'selectedTags' => [], 'nsfw' => false, 'sfw' => false, 'userLists' => null, 'publicLists' => null])

@php
    use Illuminate\Support\Facades\Auth;
@endphp

<div
    class="relative bg-white dark:bg-gray-800/50 rounded-lg shadow-sm p-4 flex flex-col backdrop-blur-xs border border-gray-200 dark:border-transparent transition-all duration-150">
    <div class="flex gap-4">
        @if ($game->is_visible)
            <div class="flex flex-col h-full">
                <a href="{{ route('games.show', $game) }}">
                    <x-game-thumbnail :game="$game" variant="small" class="h-24 w-32 object-cover rounded-sm"/>
                </a>
                @if ($game->is_on_sale || $game->is_paid || $game->has_demo)
                    <div class="flex flex-wrap gap-0.5 mt-1">
                        @if ($game->is_on_sale)
                            <div class="bg-purple-600 text-white text-xs px-1.5 py-0.5 rounded-sm">
                                Sale
                                @if (isset($game->discount_percentage))
                                    -{{ $game->discount_percentage }}%
                                @endif
                            </div>
                        @endif
                        @if ($game->is_paid)
                            <div class="bg-blue-600 text-white text-xs px-1.5 py-0.5 rounded-sm">
                                @if ($game->is_on_sale && $game->current_price && $game->original_price)
                                    <span class="line-through text-blue-200">${{ number_format($game->original_price, 2) }}</span>
                                    ${{ number_format($game->current_price, 2) }}
                                @else
                                    {{ $game->current_price > 0 ? '$'.number_format($game->current_price, 2) : 'Paid' }}
                                @endif
                            </div>
                        @endif
                        @if ($game->has_demo)
                            <div class="bg-green-600 text-white text-xs px-1.5 py-0.5 rounded-sm">
                                Demo
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
        <div class="flex flex-col min-w-0 flex-1">
            <div class="min-w-0 flex items-top gap-2">
                <a href="{{ route('games.show', $game) }}"
                   class="text-base font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400 line-clamp-2">
                    {{ $game->name }}
                </a>
                <a href="{{ route('track.external-project', ['game_id' => $game->id, 'url' => $game->url]) }}"
                   target="_blank"
                   class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                   title="Open on itch.io">
                    <i class="icon-external-link"></i>
                </a>
                @if ($game->is_suspended)
                    <span class="text-yellow-500 dark:text-yellow-400" title="This game has been suspended">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </span>
                @endif
            </div>

            @if ($game->authors)
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{!! $game->authors !!}</p>
            @endif

            <div class="flex flex-col gap-2 mt-2">
                <div class="min-w-0 flex flex-wrap items-center gap-2">
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

                    @auth
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
                    @endauth
                </div>

                <div class="flex items-center gap-2">
                    <x-games::platform-icons :platforms="$game->platforms" :selected-platforms="$selectedPlatforms"/>
                </div>

                @if ($game->supported_languages && $game->supported_languages->isNotEmpty())
                    @php
                        // Pre-decode the JSON if it's a string (from the aggregation)
                        $languages = is_string($game->supported_languages)
                            ? collect(json_decode($game->supported_languages, true))->sortBy('ref_name')->values()->all()
                            : $game->supported_languages->sortBy('ref_name')->values()->toArray();
                    @endphp
                    <x-games::language-flags
                        :languages="$languages"
                        :selected-languages="$selectedLanguages"
                    />
                @endif
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 text-sm border-t border-gray-100 dark:border-gray-700/50 pt-4">
        @foreach ([
            ['label' => 'Status', 'value' => $game->status, 'type' => 'status', 'isActive' => in_array($game->status, $selectedStatuses ?? [])],
            ['label' => 'Engine', 'value' => $game->game_engine, 'type' => 'engine', 'isActive' => in_array($game->game_engine, $selectedEngines ?? [])],
            ['label' => 'Words (EN)', 'value' => number_format($game->english_word_count ?? 0) ?: '-', 'isFilter' => false],
            ['label' => 'Released', 'value' => $game->initially_published_at?->format('M j, Y') ?? '-', 'isFilter' => false],
            ['label' => 'Updated', 'value' => $game->latest_version_published_at?->format('M j, Y') ?? '-', 'isFilter' => false],
        ] as $detail)
            <div>
                <span class="text-gray-500 dark:text-gray-400">{{ $detail['label'] }}:</span>
                @if ($detail['isFilter'] ?? true)
                    <button
                        wire:click="toggleFilter('{{ $detail['type'] }}', '{{ addslashes($detail['value']) }}')"
                        @class([
                            'ml-1 hover:text-blue-400 cursor-pointer transition-colors duration-150',
                            'text-blue-400 font-medium' => $detail['isActive'] ?? false,
                            'text-gray-700 dark:text-gray-200 hover:text-blue-400' => !$detail['isActive'] ?? true,
                        ])>
                        {{ $detail['value'] }}
                    </button>
                @else
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ $detail['value'] }}</span>
                @endif
            </div>
        @endforeach
        <div>
            <span class="text-gray-500 dark:text-gray-400">Rating:</span>
            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ $game->rating ? number_format($game->rating, 1) : '-' }}</span>
            @if ($game->rating)
                <span class="text-gray-600 dark:text-gray-300 text-xs">
                    ({{ number_format($game->rating_count) }} reviews)
                </span>
            @endif
        </div>
    </div>

    @if ($game->tags->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-1.5">
            @foreach ($game->tags as $tag)
                @php
                    $tagId = (string) $tag->id;
                    $isActive = in_array($tagId, $selectedTags);
                @endphp
                <button
                    wire:click="toggleFilter('tag', '{{ addslashes($tagId) }}')"
                    @class([
                        'inline-flex items-center px-3 py-1 rounded-full text-xs font-medium transition-colors duration-200 cursor-pointer font-semibold',
                        'bg-blue-600 text-white dark:bg-blue-700 border-2 border-blue-700 dark:border-blue-500 shadow-md' => $isActive,
                        'bg-white dark:bg-gray-700/50 text-gray-600 dark:text-gray-200 border border-gray-200 dark:border-gray-600/50' => !$isActive
                    ])
                    @if ($isActive)
                        title="Click to remove this filter"
                    @else
                        title="Click to filter by this tag"
                    @endif
                >
                    {{ $tag->name }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($game->gameJams && $game->gameJams->isNotEmpty())
        <div class="mt-4 border-t border-gray-100 dark:border-gray-700/50 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Game Jams</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($game->gameJams as $jam)
                    <button
                        wire:click="toggleFilter('gamejam', '{{ addslashes($jam->id) }}')"
                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800/50">
                        {{ $jam->name }}
                        @if ($jam->pivot && $jam->pivot->ranking)
                            <span class="ml-1.5 px-1.5 py-0.5 bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                                {{ $jam->pivot->ranking }}
                            </span>
                        @endif
                        @if ($jam->pivot && $jam->pivot->criteria_rankings)
                            @php
                                $criteriaData = json_decode($jam->pivot->criteria_rankings, true) ?? [];
                                $tooltipText = [];
                                foreach ($criteriaData as $criteria => $details) {
                                    if (is_array($details)) {
                                        $tooltipText[] = $criteria . ': ' . ($details['rank'] ?? '') .
                                            (isset($details['score']) ? ' (Score: ' . $details['score'] . ')' : '');
                                    } else {
                                        $tooltipText[] = $criteria . ': ' . $details;
                                    }
                                }
                            @endphp
                            <span
                                class="ml-1.5 cursor-help"
                                title="{{ implode(', ', $tooltipText) }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600 dark:text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @auth
        <div class="mt-4"> </div>
        <div class="mt-auto flex items-center justify-between gap-4 border-t border-gray-100 dark:border-gray-700/50 pt-3">
            <div class="flex-1">
                <x-lists::list-buttons :game="$game" :userLists="$userLists ?? null" :compact="true" class="w-full" />
            </div>
            <x-games::notification-toggle :game="$game" label="Receive notifications" justify="justify-start" />
        </div>
    @endauth
</div>


