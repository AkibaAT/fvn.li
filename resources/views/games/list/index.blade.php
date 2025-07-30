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
                             :selected-tags="$selectedTags"
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

        // Handle browser back/forward navigation
        window.addEventListener('popstate', function(event) {
            if (!event.isTrusted) return;

            const component = window.Livewire?.find('{{ $this->getId() }}');
            if (!component) {
                window.location.reload();
                return;
            }

            // Parse URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Define parameter configuration with defaults
            const paramConfig = {
                search: { default: '', type: 'string' },
                selectedStatuses: { default: [], type: 'array' },
                selectedEngines: { default: [], type: 'array' },
                selectedPlatforms: { default: [], type: 'array' },
                selectedLanguages: { default: [], type: 'array' },
                selectedGameJams: { default: [], type: 'array' },
                selectedTags: { default: [], type: 'array' },
                nsfw: { default: false, type: 'boolean' },
                sfw: { default: false, type: 'boolean' },
                showPaid: { default: false, type: 'boolean' },
                showFree: { default: false, type: 'boolean' },
                showDemo: { default: false, type: 'boolean' },
                showSuspended: { default: false, type: 'boolean' },
                showHidden: { default: false, type: 'boolean' },
                sortField: { default: '{{ self::DEFAULT_SORT_FIELD }}', type: 'string' },
                sortDirection: { default: '{{ self::DEFAULT_SORT_DIRECTION }}', type: 'string' },
                perPage: { default: 9, type: 'number' },
                page: { default: 1, type: 'number' }
            };

            // Build update object for batch setting
            const updates = {};

            Object.entries(paramConfig).forEach(([param, config]) => {
                let value = config.default;

                if (config.type === 'array') {
                    // Handle array parameters with indexed bracket notation like selectedLanguages[0]=value
                    const indexedValues = [];
                    const paramPrefix = `${param}[`;

                    // Check for indexed parameters
                    for (const [key, urlValue] of urlParams.entries()) {
                        if (key.startsWith(paramPrefix) && key.endsWith(']')) {
                            if (urlValue.trim() !== '') {
                                indexedValues.push(urlValue);
                            }
                        }
                    }

                    if (indexedValues.length > 0) {
                        value = indexedValues;
                    } else {
                        // Fallback to other formats
                        const singleValue = urlParams.get(param);
                        if (singleValue && singleValue.includes(',')) {
                            value = singleValue.split(',').filter(v => v.trim() !== '');
                        } else if (singleValue) {
                            value = [singleValue].filter(v => v.trim() !== '');
                        }
                    }
                } else {
                    const urlValue = urlParams.get(param);
                    if (urlValue !== null && urlValue !== '') {
                        switch (config.type) {
                            case 'boolean':
                                value = urlValue === '1' || urlValue === 'true';
                                break;
                            case 'number':
                                value = parseInt(urlValue) || config.default;
                                break;
                            default:
                                value = urlValue;
                        }
                    }
                }

                updates[param] = value;
            });

            // Apply updates using custom Livewire method
            try {
                component.call('syncFromBrowserHistory', updates);
            } catch (error) {
                console.warn('Livewire popstate sync failed, falling back to reload:', error);
                window.location.reload();
            }
        });
    </script>

    @include('components.ui.meta-data-refresh')
</div>

@push('scripts')
    @vite('resources/js/list-buttons.ts')
    @vite('resources/js/toggle-notifications.ts')
@endpush
