<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Rating History
                </h2>
                <button wire:click="toggleReviewsView"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    Show {{ $showOnlyReviews ? 'all ratings' : 'reviews only' }}
                </button>
                <button wire:click="toggleGameVisibility"
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    Show {{ $showOnlyVisibleGames ? 'all games' : 'listed games only' }}
                </button>
            </div>

            {{-- Sort Controls --}}
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">Sort by:</span>
                <button wire:click="sortBy('published_at')"
                        class="px-3 py-1 text-sm rounded-lg {{ $sortField === 'published_at'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    Date {{ $sortField === 'published_at' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                </button>
                <button wire:click="sortBy('rating')"
                        class="px-3 py-1 text-sm rounded-lg {{ $sortField === 'rating'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    Rating {{ $sortField === 'rating' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
                </button>
            </div>
        </div>
    </div>

    <div class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach ($ratings as $rating)
            <div class="p-6">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('games.show', ['game' => $rating->game->slug]) }}"
                           class="text-lg font-medium text-blue-600 dark:text-blue-400 hover:underline">
                            {{ $rating->game->name }}
                        </a>
                        @if ($previousRatingCounts[$rating->game_id] ?? false)
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                <button wire:click="showRatingHistory({{ $rating->game_id }})" class="hover:underline">
                                    ({{ $previousRatingCounts[$rating->game_id] }} previous {{ Str::plural('rating', $previousRatingCounts[$rating->game_id]) }})
                                </button>
                            </span>
                        @endif
                        <a href="{{ $rating->game->url }}"
                           target="_blank"
                           class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                           title="Open on itch.io">
                            <i class="icon-external-link"></i>
                        </a>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1 text-yellow-400">
                            @for ($i = 0; $i < $rating->rating; $i++)
                                <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $rating->published_at->format('M j, Y') }}
                        </span>
                        <a href="https://itch.io/event/{{ $rating->event_id }}"
                           target="_blank"
                           class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                           title="View on itch.io">
                            <i class="icon-external-link"></i>
                        </a>
                    </div>
                </div>

                @if ($rating->review)
                    <div class="prose dark:prose-invert max-w-none mt-2 text-gray-600 dark:text-gray-300">
                        {!! $rating->review !!}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
        {{ $ratings->links(data: ['scrollTo' => '#versions']) }}
    </div>
</div>
