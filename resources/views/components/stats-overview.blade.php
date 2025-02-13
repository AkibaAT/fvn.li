@props(['gameStats', 'ratingStats'])
<div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
    <!-- Game Stats -->
    <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
            Games
        </h2>
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Games
                </dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($gameStats['total']) }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Listed Games
                </dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($gameStats['visible']) }}
                </dd>
                <dd class="text-sm text-gray-500 dark:text-gray-400">
                    Listing rate: {{ number_format($gameStats['listing_rate'], 1) }}%
                </dd>
            </div>
        </dl>
        @if ($gameStats['latest_update'])
            <div class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Update:</span>
                <span class="ml-1 text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::parse($gameStats['latest_update'])->diffForHumans() }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ({{ \Carbon\Carbon::parse($gameStats['latest_update'])->format('Y-m-d H:i:s') }})
                    </span>
                </span>
            </div>
        @endif
    </div>

    <!-- Rating Stats -->
    <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
            Ratings
        </h2>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- All Ratings -->
            <div>
                <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                    All Ratings
                </h3>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Ratings
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($ratingStats['total']) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Reviews
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($ratingStats['reviews']['total']) }}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Review rate: {{ number_format($ratingStats['reviews']['review_rate'], 1) }}%
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Average Rating
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['average_rating'], 2) }}
                                </span>
                            <x-rating-stars :rating="number_format($ratingStats['average_rating'], 2)"/>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Listed Games -->
            <div>
                <h3 class="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                    Listed Games
                </h3>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Ratings
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($ratingStats['visible_games']['total']) }}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            ({{ number_format(($ratingStats['visible_games']['total'] / max($ratingStats['total'], 1)) * 100, 1) }}% of all)
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Reviews
                        </dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($ratingStats['visible_games']['reviews']) }}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Review rate: {{ number_format($ratingStats['visible_games']['review_rate'], 1) }}%
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Average Rating
                        </dt>
                        <dd class="mt-1 flex items-center gap-2">
                                <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['visible_games']['average_rating'], 2) }}
                                </span>
                            <x-rating-stars :rating="number_format($ratingStats['visible_games']['average_rating'], 2)" />
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
        @if ($ratingStats['latest'])
            <div class="mt-4 text-sm">
                <span class="text-gray-500 dark:text-gray-400">Latest Rating:</span>
                <span class="ml-1 text-gray-900 dark:text-gray-100">
                    {{ \Carbon\Carbon::parse($ratingStats['latest'])->diffForHumans() }}
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        ({{ \Carbon\Carbon::parse($ratingStats['latest'])->format('Y-m-d H:i:s') }})
                    </span>
                </span>
            </div>
        @endif
    </div>
</div>
