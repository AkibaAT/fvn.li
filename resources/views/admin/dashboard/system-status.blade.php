<div class="bg-gray-100 dark:bg-gray-900">
    <div class="sticky top-0 z-10 mb-4 flex items-center justify-between bg-gray-100 py-4 dark:bg-gray-900">
        <a href="{{ route('games.index') }}"
           class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Game List
        </a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            System Status
        </h1>
    </div>

    <x-ratings::stats-overview :game-stats="$gameStats" :rating-stats="$ratingStats"/>
    <livewire:rating-trends/>
    <x-admin::tasks-summary :health-summary="$healthSummary"/>
    <x-admin::tasks-list :monitored-tasks="$monitoredTasks" :date-format="$dateFormat"/>
</div>
