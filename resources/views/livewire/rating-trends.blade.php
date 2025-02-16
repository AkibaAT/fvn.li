<div>
    @push('scripts')
        @vite('resources/js/charts-entry.ts')
    @endpush

    <!-- All Ratings Trend -->
    <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="space-y-8">
            <div>
                <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    All Ratings Trend
                </h2>
                <div class="relative h-[300px] w-full">
                    <div
                        x-data="{
                            chart: null,
                            isLoading: true
                        }"
                        x-init="
                            window.chartInitialized.then(() => {
                                $nextTick(() => {
                                    chart = window.initializeTrendChart(
                                        $el,
                                        @js($ratingStats['monthly_trend']),
                                        {
                                            lineColor: '#EAB308',
                                            areaColor: 'rgba(234, 179, 8, 0.1)'
                                        }
                                    );
                                    isLoading = false;
                                });
                            });
                        "
                        @disconnect.window="chart?.dispose()"
                        class="h-full w-full"
                    >
                        <template x-if="isLoading">
                            <div
                                class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                <div
                                    class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Listed Games Ratings Trend -->
    <div class="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="space-y-8">
            <div>
                <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Listed Games Ratings Trend
                </h2>
                <div class="relative h-[300px] w-full">
                    <div
                        x-data="{
                            chart: null,
                            isLoading: true
                        }"
                        x-init="
                            window.chartInitialized.then(() => {
                                $nextTick(() => {
                                    chart = window.initializeTrendChart(
                                        $el,
                                        @js($ratingStats['visible_games_monthly_trend']),
                                        {
                                            lineColor: '#22C55E',
                                            areaColor: 'rgba(34, 197, 94, 0.1)'
                                        }
                                    );
                                    isLoading = false;
                                });
                            });
                        "
                        @disconnect.window="chart?.dispose()"
                        class="h-full w-full"
                    >
                        <template x-if="isLoading">
                            <div
                                class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-gray-800/50">
                                <div
                                    class="h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
