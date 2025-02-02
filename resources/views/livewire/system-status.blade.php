<div class="bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-4 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
            <a href="{{ route('games.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
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

        {{-- Stats Overview --}}
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Game Stats --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Games
                </h2>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Games</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($gameStats['total']) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Listed Games</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($gameStats['visible']) }}
                        </dd>
                        <dd class="text-sm text-gray-500 dark:text-gray-400">
                            Listing rate: {{ number_format($gameStats['visible'] / max($gameStats['total'], 1) * 100, 1) }}%
                        </dd>
                    </div>
                </dl>
                @if ($gameStats['latest_update'])
                    <div class="mt-4 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Latest Update:</span>
                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                            {{ $gameStats['latest_update']->diffForHumans() }}
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                ({{ $gameStats['latest_update']->format('Y-m-d H:i:s') }})
                            </span>
                        </span>
                    </div>
                @endif
            </div>

            {{-- Rating Stats --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Ratings
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- All Ratings --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">All Ratings</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ratings</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['total']) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reviews</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['reviews']['total']) }}
                                </dd>
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate: {{ number_format($ratingStats['reviews']['review_rate'], 1) }}%
                                </dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    {{ number_format($ratingStats['average_rating'], 2) }}
                                    <div class="flex items-center gap-0.5 text-yellow-400">
                                        @for ($i = 0; $i < 5; $i++)
                                            <svg class="w-5 h-5 {{ $i < floor($ratingStats['average_rating']) ? 'fill-current' : 'fill-gray-300 dark:fill-gray-600' }}"
                                                 viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Listed Games Only --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Listed Games</h3>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ratings</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['visible_games']['total']) }}
                                </dd>
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    ({{ number_format($ratingStats['visible_games']['total'] / max($ratingStats['total'], 1) * 100, 1) }}% of all)
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reviews</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ number_format($ratingStats['visible_games']['reviews']) }}
                                </dd>
                                <dd class="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate: {{ number_format($ratingStats['visible_games']['review_rate'], 1) }}%
                                </dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    {{ number_format($ratingStats['visible_games']['average_rating'], 2) }}
                                    <div class="flex items-center gap-0.5 text-yellow-400">
                                        @for ($i = 0; $i < 5; $i++)
                                            <svg class="w-5 h-5 {{ $i < floor($ratingStats['visible_games']['average_rating']) ? 'fill-current' : 'fill-gray-300 dark:fill-gray-600' }}"
                                                 viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                @if ($ratingStats['latest'])
                    <div class="mt-4 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Latest Rating:</span>
                        <span class="ml-1 text-gray-900 dark:text-gray-100">
                            {{ $ratingStats['latest']->diffForHumans() }}
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                ({{ $ratingStats['latest']->format('Y-m-d H:i:s') }})
                            </span>
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- All Ratings Trend --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <div class="space-y-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        All Ratings Trend
                    </h2>
                    <x-rating-trend-graph
                        :data="$ratingStats['monthly_trend']"
                        line-color="#EAB308"
                        :text-color="'#6B7280'"
                        :grid-color="'#E5E7EB'"
                    />
                </div>
            </div>
        </div>

        {{-- Listed Games Ratings Trend --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <div class="space-y-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Listed Games Ratings Trend
                    </h2>
                    <x-rating-trend-graph
                        :data="$ratingStats['visible_games_monthly_trend']"
                        line-color="#22C55E"
                        :text-color="'#6B7280'"
                        :grid-color="'#E5E7EB'"
                    />
                </div>
            </div>
        </div>

        {{-- Scheduled Tasks Health Summary --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Scheduled Tasks Health
                </h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Task Status:</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Single Server</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Maintenance OK</span>
                </div>
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tasks</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($healthSummary['total']) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</dt>
                    <dd class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">
                        {{ number_format($healthSummary['active']) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed</dt>
                    <dd class="mt-1 text-2xl font-semibold {{ $healthSummary['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                        {{ number_format($healthSummary['failed']) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Never Run</dt>
                    <dd class="mt-1 text-2xl font-semibold {{ $healthSummary['never_run'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-gray-100' }}">
                        {{ number_format($healthSummary['never_run']) }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Scheduled Tasks Details --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Scheduled Tasks
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Task
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Schedule
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Last Run
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Next Run
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($monitoredTasks as $task)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
                            <td class="px-6 py-4 text-sm">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $task['name'] }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                    <span>{{ $task['type'] }}</span>
                                    @if ($task['grace_time'])
                                        <span class="text-xs">(Grace: {{ $task['grace_time'] }}m)</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    {{ $task['schedule'] }}
                                    @if ($task['timezone'] && $task['timezone'] !== config('app.timezone'))
                                        <span class="text-xs block text-gray-400 dark:text-gray-500">
                                                {{ $task['timezone'] }}
                                            </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @if ($task['last_started'])
                                        <div>{{ $task['last_started']->diffForHumans() }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $task['last_started']->format($dateFormat) }}
                                        </div>
                                        @if ($task['last_finished'])
                                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                                Duration: {{ $task['last_finished']->diffInSeconds($task['last_started']) }}s
                                            </div>
                                        @endif
                                    @else
                                        Never
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @if ($task['next_run'])
                                        <div>{{ $task['next_run']->diffForHumans() }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $task['next_run']->format($dateFormat) }}
                                        </div>
                                    @else
                                        Unknown
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="space-y-1">
                                    @if ($task['last_failed'] && (!$task['last_finished'] || $task['last_failed']->isAfter($task['last_finished'])))
                                        <div class="flex items-center gap-2">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Failed
                                                </span>
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                    {{ $task['last_failed']->diffForHumans() }}
                                                </span>
                                        </div>
                                        @if ($task['latest_log']?->meta['failure_message'] ?? null)
                                            <div class="text-xs text-red-600 dark:text-red-400 max-w-xs truncate group-hover:whitespace-normal">
                                                {{ $task['latest_log']->meta['failure_message'] }}
                                            </div>
                                        @endif
                                    @elseif ($task['last_finished'])
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Success
                                            </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                Never Run
                                            </span>
                                    @endif

                                    <div class="flex flex-wrap gap-1">
                                        @if ($task['runs_on_one_server'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    Single Server
                                                </span>
                                        @endif

                                        @if ($task['runs_in_maintenance'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    Maintenance OK
                                                </span>
                                        @endif

                                        @if ($task['registered_on_oh_dear'])
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                    Oh Dear
                                                </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tasks['unnamed']->isNotEmpty() || $tasks['duplicate']->isNotEmpty())
                <div class="mt-6 space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                    @if ($tasks['unnamed']->isNotEmpty())
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Unnamed Tasks</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">These tasks cannot be monitored because no name could be determined for them.</p>
                            <div class="space-y-1">
                                @foreach ($tasks['unnamed'] as $task)
                                    <div class="text-sm text-gray-600 dark:text-gray-300">{{ $task->type() }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($tasks['duplicate']->isNotEmpty())
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">Duplicate Tasks</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">These tasks could not be monitored because they have duplicate names.</p>
                            <div class="space-y-1">
                                @foreach ($tasks['duplicate'] as $task)
                                    <div class="text-sm text-gray-600 dark:text-gray-300">{{ $task->name() }} ({{ $task->type() }})</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
