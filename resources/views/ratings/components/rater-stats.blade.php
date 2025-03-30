<div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
    @php
        $rater = App\Models\Rater::find($raterId);
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            {{ $rater->alias }}'s Ratings
        </h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @php
            $statItems = [
                [
                    'label' => 'Total Games Rated',
                    'value' => number_format($stats['all_games']['unique_games']),
                    'sub' => number_format($stats['visible_games']['unique_games']) . ' listed'
                ],
                [
                    'label' => 'Average Rating',
                    'value' => number_format($stats['all_games']['average_rating'], 1),
                    'sub' => number_format($stats['visible_games']['average_rating'], 1) . ' for listed games'
                ],
                [
                    'label' => 'Review Rate',
                    'value' => number_format($stats['all_games']['review_percentage']) . '%',
                    'sub' => number_format($stats['visible_games']['review_percentage']) . '% for listed games'
                ],
                [
                    'label' => 'Rating Period',
                    'value' => Carbon\Carbon::parse($stats['first_rating'])->format('M j, Y'),
                    'sub' => 'to ' . Carbon\Carbon::parse($stats['latest_rating'])->format('M j, Y')
                ],
            ];
        @endphp

        @foreach ($statItems as $item)
            <div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $item['label'] }}
                </div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $item['value'] }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $item['sub'] }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Rating Distribution Charts --}}
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ([
            'All Games' => $stats['all_games']['rating_distribution'],
            'Listed Games' => $stats['visible_games']['rating_distribution']
        ] as $title => $distribution)
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    {{ $title }} Rating Distribution
                </h3>
                <div class="space-y-2">
                    @foreach ($distribution as $rating => $count)
                        @php
                            $percentage = $stats[$title === 'All Games' ? 'all_games' : 'visible_games']['total_ratings'] > 0
                                ? ($count / $stats[$title === 'All Games' ? 'all_games' : 'visible_games']['total_ratings'] * 100)
                                : 0;
                        @endphp
                        <div>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400 w-16">
                                    {{ $rating }} Stars
                                </span>
                                <div class="flex-1 mx-2">
                                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-400 dark:bg-yellow-500"
                                             style="width: {{ $percentage }}%">
                                        </div>
                                    </div>
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400 w-20 text-right">
                                    {{ number_format($count) }}
                                    ({{ number_format($percentage, 1) }}%)
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
