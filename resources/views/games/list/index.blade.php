<div class="bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto">
        @include('games.list.partials.search-controls')
        @include('games.list.partials.active-filters')

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($games as $game)
                <x-games::game-card :game="$game"
                             :selected-statuses="$selectedStatuses"
                             :selected-engines="$selectedEngines"
                             :selected-platforms="$selectedPlatforms"
                             :selected-languages="$selectedLanguages"
                             :nsfw="$nsfw"
                             :sfw="$sfw"
                             :user-lists="$userLists ?? null"/>
            @endforeach
        </div>

        @include('games.list.partials.pagination')
    </div>

    @include('games.list.partials.filters-dialog')
    @include('games.list.partials.sort-dialog')

    <script>
        ['sort-modal', 'filters-modal'].forEach(id => {
            document.getElementById(id).addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.currentTarget.close();
                }
            });
        });
    </script>

    @include('components.ui.meta-data-refresh')
</div>

@push('scripts')
    @vite('resources/js/list-buttons.ts')
    @vite('resources/js/toggle-notifications.ts')
@endpush
