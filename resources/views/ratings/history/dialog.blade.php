<dialog
    wire:ignore.self
    id="rating-history"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-ui.dialog-header title="Rating History"/>

    @if ($ratings && $ratings->isNotEmpty())
        <div class="space-y-4">
            <div class="mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $gameName }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Rating history for this game:
                </p>
            </div>

            <div class="space-y-6">
                @foreach ($ratings as $historyRating)
                    <div class="{{ !$loop->last ? 'border-b border-gray-200 dark:border-gray-700 pb-6' : '' }}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1 text-yellow-400">
                                    @for ($i = 0; $i < $historyRating->rating; $i++)
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                  d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $historyRating->published_at->format('M j, Y') }}
                                </span>
                                @if ($historyRating->is_visible)
                                    <span
                                        class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                        Current
                                    </span>
                                @endif
                            </div>
                            <a href="https://itch.io/event/{{ $historyRating->event_id }}"
                               target="_blank"
                               class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                View on itch.io
                            </a>
                        </div>

                        @if ($historyRating->review)
                            <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                {!! $historyRating->review !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="py-4 text-center text-gray-500 dark:text-gray-400">
            No rating history found.
        </div>
    @endif

    <x-ui.dialog-footer/>
</dialog>
