<x-layouts.app :metaTags="$metaTags">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metaTags['title'] ?? 'Edit Game' }}</h1>
        <a href="{{ route('user.games.index') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            ← Back to My Games
        </a>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Game Information -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Game Information</h2>

                <div class="space-y-4">
                    @if ($game->thumb_url)
                        <div>
                            <img src="{{ $game->thumb_url }}" alt="{{ $game->name }}" class="w-full rounded-lg">
                        </div>
                    @endif

                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">{{ $game->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $game->status }}</p>
                    </div>

                    @if ($game->description)
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($game->description, 200) }}</p>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        <a href="{{ route('track.external-project', ['game_id' => $game->id, 'url' => $game->url]) }}" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            itch.io
                        </a>
                        <a href="{{ route('games.show', $game->slug) }}" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View on Site
                        </a>
                    </div>

                    <!-- Analytics Overview -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Analytics (Last 30 Days)</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ $clickStats['page_views_unique'] ?? 0 }}</div>
                                <div class="text-xs text-blue-600 dark:text-blue-400">Page Views</div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-purple-900 dark:text-purple-100">{{ $clickStats['external_project_unique'] ?? 0 }}</div>
                                <div class="text-xs text-purple-600 dark:text-purple-400">itch.io Visits</div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg text-center">
                                <div class="text-lg font-bold text-green-900 dark:text-green-100">{{ array_sum(array_column($clickStats['custom_links'] ?? [], 'unique_clicks')) }}</div>
                                <div class="text-xs text-green-600 dark:text-green-400">Downloads</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <!-- Edit Form -->
        <div class="lg:col-span-2">
            <!-- Downloads Management Form -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Downloads</h2>

                <form action="{{ route('user.games.update', $game) }}" method="POST" id="links-form">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div id="links-container">
                            @php
                                $existingLinks = old('links', $game->additional_links ?? []);
                                if (empty($existingLinks)) {
                                    $existingLinks = [['id' => '', 'name' => '', 'url' => '', 'platform' => '']];
                                }
                            @endphp

                            @foreach ($existingLinks as $index => $link)
                                <div class="link-item border border-gray-200 dark:border-gray-600 rounded-lg p-4" data-index="{{ $index }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="drag-handle cursor-move h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Link {{ $index + 1 }}</span>
                                        </div>
                                        <button type="button" class="remove-link text-red-500 hover:text-red-700" title="Remove link">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <input type="hidden" name="links[{{ $index }}][id]" value="{{ $link['id'] ?? '' }}">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Link Name <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                name="links[{{ $index }}][name]"
                                                value="{{ $link['name'] ?? '' }}"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                placeholder="e.g., Direct Download, Mirror Link"
                                                required
                                            >
                                            @error("links.{$index}.name")
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Platform
                                            </label>
                                            <select
                                                name="links[{{ $index }}][platform]"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                            >
                                                <option value="">Select Platform</option>
                                                @foreach ($platforms as $key => $label)
                                                    <option value="{{ $key }}" {{ ($link['platform'] ?? '') === $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            URL <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="url"
                                            name="links[{{ $index }}][url]"
                                            value="{{ $link['url'] ?? '' }}"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                            placeholder="https://example.com/download"
                                            required
                                        >
                                        @error("links.{$index}.url")
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if (!empty($link['last_edited_at']))
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Last edited: {{ \Carbon\Carbon::parse($link['last_edited_at'])->diffForHumans() }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center">
                            <button type="button" id="add-link" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Link
                            </button>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Maximum 15 links</span>
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Important Notes</h4>
                                    <ul class="mt-1 text-sm text-yellow-700 dark:text-yellow-400 list-disc list-inside space-y-1">
                                        <li>These links will be displayed publicly on your game's page</li>
                                        <li>Make sure all links are accessible and lead to safe downloads</li>
                                        <li>You are responsible for maintaining the availability of these links</li>
                                        <li>Use drag handles to reorder links</li>
                                        <li>Remove all links to disable the downloads section</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Save Changes
                            </button>
                            <a href="{{ route('user.games.index') }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Analytics Charts -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mt-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Analytics Charts (Last 30 Days)</h2>

        <!-- Chart Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button onclick="showChart('overview')" id="tab-overview" class="chart-tab active border-b-2 border-blue-500 py-2 px-1 text-sm font-medium text-blue-600 dark:text-blue-400">
                    Overview
                </button>
                <button onclick="showChart('pageviews')" id="tab-pageviews" class="chart-tab border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    Page Views
                </button>
                <button onclick="showChart('external')" id="tab-external" class="chart-tab border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                    itch.io Visits
                </button>
                @if (!empty($clickStats['custom_links']))
                    <button onclick="showChart('downloads')" id="tab-downloads" class="chart-tab border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        Downloads
                    </button>
                @endif
            </nav>
        </div>

        <!-- Chart Containers -->
        <div class="chart-container" id="chart-overview">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="relative h-[300px] w-full">
                    <div
                        x-data="{
                            chart: null,
                            isLoading: false,
                            initialized: false
                        }"
                        x-init="
                            // Initialize immediately for overview (default tab)
                            window.chartInitialized.then(() => {
                                $nextTick(() => {
                                    if (!initialized) {
                                        isLoading = true;
                                        chart = window.initializeMultiSeriesChart(
                                            $refs.chartContainer,
                                            [
                                                {
                                                    name: 'Page Views (Unique)',
                                                    data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['page_views_unique']])),
                                                    color: '#3b82f6'
                                                },
                                                {
                                                    name: 'itch.io Visits (Unique)',
                                                    data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['external_project_unique']])),
                                                    color: '#8b5cf6'
                                                },
                                                {
                                                    name: 'Downloads (Unique)',
                                                    data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['custom_links_unique']])),
                                                    color: '#10b981'
                                                }
                                            ],
                                            { animation: false }
                                        );
                                        isLoading = false;
                                        initialized = true;
                                    }
                                });
                            });
                        "
                        @init-chart.window="
                            if ($event.detail.chartType === 'overview' && !initialized) {
                                isLoading = true;
                                window.chartInitialized.then(() => {
                                    chart = window.initializeMultiSeriesChart(
                                        $refs.chartContainer,
                                        [
                                            {
                                                name: 'Page Views (Unique)',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['page_views_unique']])),
                                                color: '#3b82f6'
                                            },
                                            {
                                                name: 'itch.io Visits (Unique)',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['external_project_unique']])),
                                                color: '#8b5cf6'
                                            },
                                            {
                                                name: 'Downloads (Unique)',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['custom_links_unique']])),
                                                color: '#10b981'
                                            }
                                        ],
                                        { animation: false }
                                    );
                                    isLoading = false;
                                    initialized = true;
                                });
                            }
                        "
                        @disconnect.window="chart?.dispose()"
                        class="h-full w-full"
                        x-ref="chartContainer"
                    >
                        <template x-if="isLoading">
                            <div class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-container hidden" id="chart-pageviews">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="relative h-[300px] w-full">
                    <div
                        x-data="{
                            chart: null,
                            isLoading: false,
                            initialized: false
                        }"
                        @init-chart.window="
                            if ($event.detail.chartType === 'pageviews' && !initialized) {
                                isLoading = true;
                                window.chartInitialized.then(() => {
                                    chart = window.initializeMultiSeriesChart(
                                        $refs.chartContainer,
                                        [
                                            {
                                                name: 'Unique Views',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['page_views_unique']])),
                                                color: '#3b82f6'
                                            },
                                            {
                                                name: 'Total Views',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['page_views_total']])),
                                                color: '#93c5fd'
                                            }
                                        ],
                                        { title: 'Page Views Breakdown', animation: false }
                                    );
                                    isLoading = false;
                                    initialized = true;
                                });
                            }
                        "
                        @disconnect.window="chart?.dispose()"
                        class="h-full w-full"
                        x-ref="chartContainer"
                    >
                        <template x-if="isLoading">
                            <div class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="chart-container hidden" id="chart-external">
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="relative h-[300px] w-full">
                    <div
                        x-data="{
                            chart: null,
                            isLoading: false,
                            initialized: false
                        }"
                        @init-chart.window="
                            if ($event.detail.chartType === 'external' && !initialized) {
                                isLoading = true;
                                window.chartInitialized.then(() => {
                                    chart = window.initializeMultiSeriesChart(
                                        $refs.chartContainer,
                                        [
                                            {
                                                name: 'Unique Visits',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['external_project_unique']])),
                                                color: '#8b5cf6'
                                            },
                                            {
                                                name: 'Total Visits',
                                                data: @js(collect($dailyStats)->map(fn($day) => ['month' => $day['date'], 'count' => $day['external_project_total']])),
                                                color: '#c4b5fd'
                                            }
                                        ],
                                        { title: 'itch.io Visits Breakdown', animation: false }
                                    );
                                    isLoading = false;
                                    initialized = true;
                                });
                            }
                        "
                        @disconnect.window="chart?.dispose()"
                        class="h-full w-full"
                        x-ref="chartContainer"
                    >
                        <template x-if="isLoading">
                            <div class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($clickStats['custom_links']))
            <div class="chart-container hidden" id="chart-downloads">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <div class="relative h-[300px] w-full">
                        <div
                            x-data="{
                                chart: null,
                                isLoading: false,
                                initialized: false
                            }"
                            @init-chart.window="
                                if ($event.detail.chartType === 'downloads' && !initialized) {
                                    isLoading = true;
                                    window.chartInitialized.then(() => {
                                        // Create series for each download link
                                        const linkSeries = [];
                                        const linkColors = ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#ec4899'];

                                        @foreach ($linkStats as $index => $link)
                                            // Create daily data array for this link
                                            const link{{ $index }}Data = [];
                                            @foreach ($dailyStats as $dayIndex => $day)
                                                const date{{ $index }}_{{ $dayIndex }} = '{{ $day['date'] }}';
                                                const count{{ $index }}_{{ $dayIndex }} = @js($link['daily_clicks'][$day['date']] ?? 0);
                                                link{{ $index }}Data.push({ month: date{{ $index }}_{{ $dayIndex }}, count: count{{ $index }}_{{ $dayIndex }} });
                                            @endforeach

                                            linkSeries.push({
                                                name: '{{ addslashes($link['link_name']) }}',
                                                data: link{{ $index }}Data,
                                                color: linkColors[{{ $index }} % linkColors.length]
                                            });
                                        @endforeach

                                        chart = window.initializeMultiSeriesChart(
                                            $refs.chartContainer,
                                            linkSeries,
                                            { title: 'Downloads per Link', animation: false }
                                        );
                                        isLoading = false;
                                        initialized = true;
                                    });
                                }
                            "
                            @disconnect.window="chart?.dispose()"
                            class="h-full w-full"
                            x-ref="chartContainer"
                        >
                            <template x-if="isLoading">
                                <div class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                    <div class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Key Insights -->
        <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-3">📊 Key Insights</h3>
            <div class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                @php
                    $totalPageViews = $clickStats['page_views_total'] ?? 0;
                    $totalExternalVisits = $clickStats['external_project_total'] ?? 0;
                    $totalDownloads = array_sum(array_column($clickStats['custom_links'] ?? [], 'total_clicks'));

                    $conversionToExternal = $totalPageViews > 0 ? round(($totalExternalVisits / $totalPageViews) * 100, 1) : 0;
                    $conversionToDownload = $totalPageViews > 0 ? round(($totalDownloads / $totalPageViews) * 100, 1) : 0;
                @endphp

                @if ($totalPageViews > 0)
                    <div>• <strong>{{ $conversionToExternal }}%</strong> of page viewers visit your itch.io page</div>
                    @if ($totalDownloads > 0)
                        <div>• <strong>{{ $conversionToDownload }}%</strong> of page viewers download your game</div>
                    @endif

                    @if (!empty($linkStats))
                        @php
                            $bestPerformingLink = collect($linkStats)->sortByDesc('unique_clicks')->first();
                        @endphp
                        @if ($bestPerformingLink && $bestPerformingLink['unique_clicks'] > 0)
                            <div>• <strong>"{{ $bestPerformingLink['link_name'] }}"</strong> is your most popular download</div>
                        @endif
                    @endif

                    @php
                        $recentDays = collect($dailyStats)->slice(-7);
                        $avgDailyViews = $recentDays->avg('page_views_unique');
                    @endphp
                    @if ($avgDailyViews > 0)
                        <div>• Averaging <strong>{{ round($avgDailyViews, 1) }}</strong> unique views per day this week</div>
                    @endif
                @else
                    <div>• No analytics data available yet. Share your game to start seeing insights!</div>
                @endif
            </div>
        </div>

        @if (isset($clickStats['last_page_view']))
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                Last page view: {{ \Carbon\Carbon::parse($clickStats['last_page_view'])->diffForHumans() }}
            </div>
        @endif
            </div>
        </div>
    </div>

    @vite(['resources/js/charts-entry.ts'])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('links-container');
            const addButton = document.getElementById('add-link');
            let linkIndex = {{ count($existingLinks) }};

            // Platform options for new links
            const platformOptions = {!! json_encode($platforms) !!};

            // Add new link
            addButton.addEventListener('click', function() {
                if (container.children.length >= 15) {
                    alert('Maximum 15 links allowed');
                    return;
                }

                // Update button state
                if (container.children.length >= 14) {
                    addButton.disabled = true;
                    addButton.classList.add('opacity-50', 'cursor-not-allowed');
                }

                const linkHtml = createLinkHtml(linkIndex, '', '', '', '');
                container.insertAdjacentHTML('beforeend', linkHtml);
                linkIndex++;
                updateLinkNumbers();
                attachEventListeners();
            });

            // Create link HTML
            function createLinkHtml(index, id, name, url, platform, lastEditedAt = null) {
                let platformOptionsHtml = '<option value="">Select Platform</option>';
                for (const [key, label] of Object.entries(platformOptions)) {
                    const selected = platform === key ? 'selected' : '';
                    platformOptionsHtml += `<option value="${key}" ${selected}>${label}</option>`;
                }

                let lastEditedHtml = '';
                if (lastEditedAt) {
                    const editedDate = new Date(lastEditedAt);
                    const now = new Date();
                    const diffMs = now - editedDate;
                    const diffMins = Math.floor(diffMs / 60000);
                    const diffHours = Math.floor(diffMs / 3600000);
                    const diffDays = Math.floor(diffMs / 86400000);

                    let timeAgo;
                    if (diffMins < 1) {
                        timeAgo = 'just now';
                    } else if (diffMins < 60) {
                        timeAgo = `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
                    } else if (diffHours < 24) {
                        timeAgo = `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
                    } else {
                        timeAgo = `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
                    }

                    lastEditedHtml = `
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Last edited: ${timeAgo}
                        </div>
                    `;
                }

                return `
                    <div class="link-item border border-gray-200 dark:border-gray-600 rounded-lg p-4" data-index="${index}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <svg class="drag-handle cursor-move h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Link ${index + 1}</span>
                            </div>
                            <button type="button" class="remove-link text-red-500 hover:text-red-700" title="Remove link">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>

                        <input type="hidden" name="links[${index}][id]" value="${id}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Link Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="links[${index}][name]"
                                    value="${name}"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="e.g., Direct Download, Mirror Link"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Platform
                                </label>
                                <select
                                    name="links[${index}][platform]"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                >
                                    ${platformOptionsHtml}
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                URL <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="url"
                                name="links[${index}][url]"
                                value="${url}"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                placeholder="https://example.com/download"
                                required
                            >
                        </div>

                        ${lastEditedHtml}
                    </div>
                `;
            }

            // Remove link
            function attachEventListeners() {
                document.querySelectorAll('.remove-link').forEach(button => {
                    button.addEventListener('click', function() {
                        if (container.children.length <= 1) {
                            // Keep at least one link item, but clear its values
                            const linkItem = this.closest('.link-item');
                            linkItem.querySelectorAll('input[type="text"], input[type="url"]').forEach(input => input.value = '');
                            linkItem.querySelector('select').selectedIndex = 0;
                        } else {
                            this.closest('.link-item').remove();
                            updateLinkNumbers();
                        }
                    });
                });
            }

            // Update link numbers
            function updateLinkNumbers() {
                document.querySelectorAll('.link-item').forEach((item, index) => {
                    item.querySelector('span').textContent = `Link ${index + 1}`;

                    // Update input names
                    item.querySelectorAll('input, select').forEach(input => {
                        const name = input.name;
                        if (name && name.includes('[')) {
                            const newName = name.replace(/\[\d+\]/, `[${index}]`);
                            input.name = newName;
                        }
                    });
                });

                // Update add button state
                const linkCount = container.children.length;
                if (linkCount >= 15) {
                    addButton.disabled = true;
                    addButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    addButton.disabled = false;
                    addButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Initial event listeners
            attachEventListeners();

            // Simple drag and drop reordering
            let draggedElement = null;

            container.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('drag-handle')) {
                    draggedElement = e.target.closest('.link-item');
                    draggedElement.style.opacity = '0.5';
                }
            });

            container.addEventListener('dragend', function(e) {
                if (draggedElement) {
                    draggedElement.style.opacity = '';
                    draggedElement = null;
                    updateLinkNumbers();
                }
            });

            container.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedElement) {
                    const afterElement = getDragAfterElement(container, e.clientY);
                    if (afterElement == null) {
                        container.appendChild(draggedElement);
                    } else {
                        container.insertBefore(draggedElement, afterElement);
                    }
                }
            });

            // Make drag handles draggable
            document.querySelectorAll('.drag-handle').forEach(handle => {
                handle.closest('.link-item').draggable = true;
            });

            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.link-item:not(.dragging)')];

                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;

                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }

        });

        // Simple chart tab switching
        function showChart(chartType) {
            // Hide all chart containers
            document.querySelectorAll('.chart-container').forEach(container => {
                container.classList.add('hidden');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.chart-tab').forEach(tab => {
                tab.classList.remove('active', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                tab.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            });

            // Show selected chart container
            const chartContainer = document.getElementById('chart-' + chartType);
            if (chartContainer) {
                chartContainer.classList.remove('hidden');

                // Dispatch event to initialize chart if not already initialized
                window.dispatchEvent(new CustomEvent('init-chart', {
                    detail: { chartType: chartType }
                }));
            }

            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + chartType);
            if (activeTab) {
                activeTab.classList.add('active', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
            }
        }
    </script>

    <style>
        .chart-tab.active {
            border-color: #3b82f6 !important;
            color: #2563eb !important;
        }

        .dark .chart-tab.active {
            color: #60a5fa !important;
        }
    </style>
</x-layouts.app>
