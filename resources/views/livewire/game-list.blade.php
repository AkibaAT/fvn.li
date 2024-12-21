<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('livewire.game-list.search-controls')
        @include('livewire.game-list.active-filters')

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($games as $game)
                <x-game-card :game="$game"
                             :selected-statuses="$selectedStatuses"
                             :selected-engines="$selectedEngines"
                             :selected-platforms="$selectedPlatforms"
                             :nsfw="$nsfw" />
            @endforeach
        </div>

        @include('livewire.game-list.pagination')
    </div>

    @include('livewire.game-list.filters-dialog')
    @include('livewire.game-list.sort-dialog')

    <script>
        ['sort-modal', 'filters-modal'].forEach(id => {
            document.getElementById(id).addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.currentTarget.close();
                }
            });
        });
    </script>

    @include('components.meta-data-refresh')
</div>
