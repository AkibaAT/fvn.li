<div class="bg-gray-100 dark:bg-gray-900">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="sticky top-0 z-10 mb-4 flex items-center justify-between bg-gray-100 py-4 dark:bg-gray-900">
            <a href="{{ route('games.index') }}"
               class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        <!-- Stats Overview -->
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
                            Listing rate: {{ (($gameStats['visible'] / max($gameStats['total'], 1)) * 100) }}%
                        </dd>
                    </div>
                </dl>
                @if($gameStats['latest_update'])
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
                                    <div class="flex items-center gap-0.5 text-yellow-400">
                                        @for($i = 0; $i < 5; $i++)
                                            <svg class="h-5 w-5 {{ $i < floor($ratingStats['average_rating']) ? 'fill-current' : 'fill-gray-300 dark:fill-gray-600' }}"
                                                 viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
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
                                    <div class="flex items-center gap-0.5 text-yellow-400">
                                        @for($i = 0; $i < 5; $i++)
                                            <svg class="h-5 w-5 {{ $i < floor($ratingStats['visible_games']['average_rating']) ? 'fill-current' : 'fill-gray-300 dark:fill-gray-600' }}"
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

                @if($ratingStats['latest'])
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

        <!-- Rating Trends -->
        <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                Rating Trends
            </h2>

            <!-- We'll add JavaScript Chart Logic Here -->
            <div id="rating-trends-chart" class="h-64"></div>
        </div>

        <!-- Tasks Health Summary -->
        <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Scheduled Tasks Health
                </h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Task Status:</span>
                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900 dark:text-blue-200">Single Server</span>
                    <span class="rounded-full bg-purple-100 px-2 py-1 text-xs text-purple-800 dark:bg-purple-900 dark:text-purple-200">Maintenance OK</span>
                </div>
            </div>

            <!-- Health Summary Stats -->
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
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

        <!-- Tasks List -->
        <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                Scheduled Tasks
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Task</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Run</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Next Run</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    @foreach($monitoredTasks as $task)
                        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-6 py-4 text-sm">
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $task['name'] }}
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $task['type'] }}</span>
                                    @if($task['grace_time'])
                                        <span class="text-xs">(Grace: {{ $task['grace_time'] }}m)</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    {{ $task['schedule'] }}
                                    @if($task['timezone'] && $task['timezone'] !== 'UTC')
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">
                                                {{ $task['timezone'] }}
                                            </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @if($task['last_started'])
                                        <div>{{ \Carbon\Carbon::parse($task['last_started'])->diffForHumans() }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ \Carbon\Carbon::parse($task['last_started'])->format($dateFormat) }}
                                        </div>
                                        @if($task['last_finished'])
                                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                                Duration: {{ \Carbon\Carbon::parse($task['last_started'])->diffInSeconds(\Carbon\Carbon::parse($task['last_finished'])) }}s
                                            </div>
                                        @endif
                                    @else
                                        Never
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="text-gray-500 dark:text-gray-400">
                                    @if($task['next_run'])
                                        <div>{{ \Carbon\Carbon::parse($task['next_run'])->diffForHumans() }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ \Carbon\Carbon::parse($task['next_run'])->format($dateFormat) }}
                                        </div>
                                    @else
                                        Unknown
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        @php
                                            $hasFailedRecently = $task['last_failed'] && (!$task['last_finished'] || \Carbon\Carbon::parse($task['last_failed'])->gt(\Carbon\Carbon::parse($task['last_finished'])));
                                            $hasRunRecently = $task['last_finished'] && \Carbon\Carbon::parse($task['last_finished'])->gt(now()->subDay());
                                            $statusColor = $hasFailedRecently ? 'red' : ($hasRunRecently ? 'green' : ($task['last_started'] ? 'yellow' : 'gray'));
                                            $statusText = $hasFailedRecently ? 'Failed' : ($hasRunRecently ? 'Active' : ($task['last_started'] ? 'Inactive' : 'Never Run'));
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5
                                            {{ $statusColor === 'red' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                               ($statusColor === 'green' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                               ($statusColor === 'gray' ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' :
                                               'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200')) }}">
                                            {{ $statusText }}
                                        </span>
                                        @if($task['last_failed'] && $hasFailedRecently)
                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                    {{ \Carbon\Carbon::parse($task['last_failed'])->diffForHumans() }}
                                                </span>
                                        @endif
                                    </div>

                                    @if($task['latest_log'] && isset($task['latest_log']['meta']['failure_message']))
                                        <div class="max-w-xs truncate text-xs text-red-600 group-hover:whitespace-normal dark:text-red-400">
                                            {{ $task['latest_log']['meta']['failure_message'] }}
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap gap-1">
                                        @if($task['runs_on_one_server'])
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    Single Server
                                                </span>
                                        @endif

                                        @if($task['runs_in_maintenance'])
                                            <span class="inline-flex rounded-full bg-purple-100 px-2 text-xs font-semibold leading-5 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    Maintenance OK
                                                </span>
                                        @endif

                                        @if($task['registered_on_oh_dear'])
                                            <span class="inline-flex rounded-full bg-indigo-100 px-2 text-xs font-semibold leading-5 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                    Oh Dear
                                                </span>
                                        @endif

                                        @if($task['grace_time'])
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 text-xs font-semibold leading-5 text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                    Grace: {{ $task['grace_time'] }}m
                                                </span>
                                        @endif

                                        @if($task['last_skipped'])
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    Skipped
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
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chart = echarts.init(document.getElementById('rating-trends-chart'));
            const isDark = document.documentElement.classList.contains('dark');

            const monthlyTrendData = @json($ratingStats['monthly_trend']);
            const visibleGamesTrendData = @json($ratingStats['visible_games_monthly_trend']);

            const option = {
                tooltip: {
                    trigger: 'axis',
                    formatter: '{b}: {c} ratings',
                    backgroundColor: isDark ? 'rgba(31, 41, 55, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                    borderColor: isDark ? '#374151' : '#E5E7EB',
                    textStyle: {
                        color: isDark ? '#9CA3AF' : '#6B7280'
                    }
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    data: monthlyTrendData.map(item => {
                        const date = new Date(item.month);
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    axisLabel: {
                        color: isDark ? '#9CA3AF' : '#6B7280',
                        rotate: 45
                    },
                    axisLine: {
                        lineStyle: {
                            color: isDark ? '#374151' : '#E5E7EB'
                        }
                    }
                },
                yAxis: {
                    type: 'value',
                    axisLabel: {
                        color: isDark ? '#9CA3AF' : '#6B7280'
                    },
                    splitLine: {
                        lineStyle: {
                            color: isDark ? '#374151' : '#E5E7EB',
                            type: 'dashed'
                        }
                    }
                },
                series: [
                    {
                        name: 'All Ratings',
                        type: 'line',
                        data: monthlyTrendData.map(item => item.count),
                        smooth: true,
                        itemStyle: {
                            color: '#EAB308'
                        },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                offset: 0,
                                color: isDark ? 'rgba(234, 179, 8, 0.2)' : 'rgba(234, 179, 8, 0.1)'
                            }, {
                                offset: 1,
                                color: isDark ? 'rgba(234, 179, 8, 0)' : 'rgba(234, 179, 8, 0)'
                            }])
                        }
                    },
                    {
                        name: 'Listed Games',
                        type: 'line',
                        data: visibleGamesTrendData.map(item => item.count),
                        smooth: true,
                        itemStyle: {
                            color: '#22C55E'
                        },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
                                offset: 0,
                                color: isDark ? 'rgba(34, 197, 94, 0.2)' : 'rgba(34, 197, 94, 0.1)'
                            }, {
                                offset: 1,
                                color: isDark ? 'rgba(34, 197, 94, 0)' : 'rgba(34, 197, 94, 0)'
                            }])
                        }
                    }
                ]
            };

            chart.setOption(option);

            // Handle window resize
            window.addEventListener('resize', function() {
                chart.resize();
            });

            // Handle dark mode changes
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class') {
                        const isDark = document.documentElement.classList.contains('dark');
                        chart.setOption({
                            tooltip: {
                                backgroundColor: isDark ? 'rgba(31, 41, 55, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                                borderColor: isDark ? '#374151' : '#E5E7EB',
                                textStyle: {
                                    color: isDark ? '#9CA3AF' : '#6B7280'
                                }
                            },
                            xAxis: {
                                axisLabel: {
                                    color: isDark ? '#9CA3AF' : '#6B7280'
                                },
                                axisLine: {
                                    lineStyle: {
                                        color: isDark ? '#374151' : '#E5E7EB'
                                    }
                                }
                            },
                            yAxis: {
                                axisLabel: {
                                    color: isDark ? '#9CA3AF' : '#6B7280'
                                },
                                splitLine: {
                                    lineStyle: {
                                        color: isDark ? '#374151' : '#E5E7EB'
                                    }
                                }
                            }
                        });
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });
        });
    </script>
@endpush
