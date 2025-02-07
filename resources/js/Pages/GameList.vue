<script lang="ts" setup>
import {computed, ref, watch} from 'vue';
import {Head, router} from '@inertiajs/vue3';
import GameCard from '@/Components/GameList/GameCard.vue';
import FilterBadge from '@/Components/GameList/FilterBadge.vue';
import SearchControls from '@/Components/GameList/SearchControls.vue';
import GameFiltersDialog from '@/Components/GameList/GameFiltersDialog.vue';
import SortDialog from '@/Components/GameList/SortDialog.vue';
import Pagination from '@/Components/GameList/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type {FilterOptions, Game} from '@/types';

interface Props {
    games: {
        data: Game[];
        links: any[]; //Links are *always* present, even in a non-paginated result
    };
    filterOptions: FilterOptions;
}

const props = defineProps<Props>();

// Default values
const search = ref('');
const selectedStatuses = ref<string[]>([]);
const selectedEngines = ref<string[]>([]);
const selectedPlatforms = ref<string[]>([]);
const selectedLanguages = ref<string[]>([]);
const nsfw = ref(false);
const sfw = ref(false);
const sortField = ref('latest_version_published_at');
const sortDirection = ref('desc');
const perPage = ref(9);
const page = ref(1);

// Parse URL parameters
const queryParams = parseQueryParams();

// Merge URL params with defaults
search.value = queryParams.search;
selectedStatuses.value = queryParams.selectedStatuses;
selectedPlatforms.value = queryParams.selectedPlatforms;
selectedLanguages.value = queryParams.selectedLanguages;
nsfw.value = queryParams.nsfw;
sfw.value = queryParams.sfw;
sortField.value = queryParams.sortField;
sortDirection.value = queryParams.sortDirection;
perPage.value = queryParams.perPage;
page.value = queryParams.page;

// Enhanced parser with array support for other parameters
function parseQueryParams() {
    const params = new URLSearchParams(window.location.search);

    const getNumber = (key: string, defaultValue: number) => {
        const value = params.get(key);
        return value !== null ? parseInt(value, 10) : defaultValue;
    };

    const getArray = (prefix: string) => {
        const items = [];
        for (const [key, value] of params) {
            if (key.startsWith(`${prefix}[`)) {
                items.push(value);
            }
        }
        return items;
    };

    return {
        page: getNumber('page', 1),
        perPage: getNumber('perPage', 9),
        search: params.get('search') || '',
        selectedLanguages: getArray('selectedLanguages'),
        selectedStatuses: getArray('selectedStatuses'),
        selectedEngines: getArray('selectedEngines'),
        selectedPlatforms: getArray('selectedPlatforms'),
        sfw: params.get('sfw') === 'true',
        nsfw: params.get('nsfw') === 'true', // If you have separate nsfw param
        sortDirection: params.get('sortDirection') || 'desc',
        sortField: params.get('sortField') || 'latest_version_published_at',
    };
}

type BaseFilterType = 'status' | 'engine' | 'platform' | 'language';

const filterMap: Record<BaseFilterType, typeof selectedStatuses> = {
    status: selectedStatuses,
    engine: selectedEngines,
    platform: selectedPlatforms,
    language: selectedLanguages,
};

// Update parameter type to string and handle type checks
const toggleFilter = (type: string, value?: string) => {
    if (type === 'nsfw') {
        nsfw.value = !nsfw.value;
        return;
    }
    if (type === 'sfw') {
        sfw.value = !sfw.value;
        return;
    }

    const baseType = type as BaseFilterType;
    const target = filterMap[baseType];
    if (!target || !value) return;

    const index = target.value.indexOf(value);
    if (index === -1) {
        target.value.push(value);
    } else {
        target.value.splice(index, 1);
    }
    page.value = 1; // Reset to page 1 when filters change
};

const activeFilters = computed(() => {
    const filters: Array<{
        id: string;
        type: BaseFilterType | 'nsfw' | 'sfw'; // Replace FilterType with BaseFilterType
        label: string;
        value?: string;
    }> = [];

    if (selectedStatuses.value.length > 0) {
        selectedStatuses.value.forEach(status => filters.push({id: status, type: 'status', label: status}));
    }

    if (selectedEngines.value.length > 0) {
        selectedEngines.value.forEach(engine => filters.push({id: engine, type: 'engine', label: engine}));
    }

    if (selectedPlatforms.value.length > 0) {
        selectedPlatforms.value.forEach(platform => filters.push({
            id: platform,
            type: 'platform',
            label: platform,
            value: platform
        }));
    }

    if (selectedLanguages.value.length > 0) {
        selectedLanguages.value.forEach(language => filters.push({
            id: language,
            type: 'language',
            label: props.filterOptions.languages[language].ref_name,
            value: props.filterOptions.languages[language].flag_code
        }));
    }

    if (nsfw.value) {
        filters.push({id: 'nsfw', type: 'nsfw', label: 'NSFW'});
    }
    if (sfw.value) {
        filters.push({id: 'sfw', type: 'sfw', label: 'SFW'});
    }

    return filters;
});


const clearFilters = () => {
    selectedStatuses.value = [];
    selectedEngines.value = [];
    selectedPlatforms.value = [];
    selectedLanguages.value = [];
    nsfw.value = false;
    sfw.value = false;
    page.value = 1; // Reset to page 1 when filters change.
    sortField.value = 'latest_version_published_at'; // Add this
    sortDirection.value = 'desc';                  // Add this
    emit('reset-filters');
};

//Keep sortBy method
const sortBy = (field: string) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    page.value = 1;  // reset to first page
};

const openDialog = (id: string) => {
    const dialog = document.getElementById(id);
    if (dialog instanceof HTMLDialogElement) {
        dialog.showModal();
    }
};

const resetSort = () => {
    sortField.value = 'latest_version_published_at';
    sortDirection.value = 'desc';
    page.value = 1; // Reset to page 1
}

// Combined watcher for all filter and sort changes.  Crucially, *exclude* props.games
watch([search, selectedStatuses, selectedEngines, selectedPlatforms,
    selectedLanguages, nsfw, sfw, sortField, sortDirection, perPage, page
], () => { //removed props.games
    router.get(
        route('games.index'),
        {
            search: search.value,
            selectedStatuses: selectedStatuses.value,
            selectedEngines: selectedEngines.value,
            selectedPlatforms: selectedPlatforms.value,
            selectedLanguages: selectedLanguages.value,
            nsfw: nsfw.value ? 'true' : 'false',  //convert to string
            sfw: sfw.value ? 'true' : 'false',
            sortField: sortField.value,
            sortDirection: sortDirection.value,
            perPage: perPage.value,
            page: page.value,
        },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        }
    );
}, {deep: true});

// Watch props.games to update the internal page value, BUT don't trigger router.get
watch(() => props.games, (newGames) => {
    if (newGames && newGames.data) {
        // Find the active link, and get the label, safely.
        const activeLink = newGames.links.find(link => link.active);
        page.value = activeLink ? parseInt(activeLink.label) || 1 : 1; //Ensure page is a number
    }
}, {deep: true}); // Add deep: true to watch for changes WITHIN props.games

const emit = defineEmits<{
    (e: 'reset-filters'): void;
}>();

</script>

<template>
    <Head>
        <title>Games List</title>
        <meta content="Browse and filter visual novel games" name="description"/>
    </Head>

    <AppLayout>
        <div class="bg-gray-100 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <SearchControls
                    v-model="search"
                    :has-filters="!!(selectedPlatforms.length || selectedStatuses.length || selectedEngines.length || nsfw)"
                    :sort-direction="sortDirection"
                    :sort-field="sortField"
                    @open-sort="() => openDialog('sort-modal')"
                    @open-filters="() => openDialog('filters-modal')"
                    @update:modelValue="search = $event; page = 1;"
                />

                <!-- Active Filters -->
                <div
                    v-if="selectedPlatforms.length || selectedStatuses.length || selectedEngines.length || selectedLanguages.length || nsfw || sfw || (sortField !== 'latest_version_published_at' || sortDirection !== 'desc')"
                    class="mb-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-300">
                            Active Filters:
                        </div>
                        <button
                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200"
                            @click="clearFilters">
                            Reset All
                        </button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-if="sortField !== 'latest_version_published_at' || sortDirection !== 'desc'"
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
                            @click="resetSort"
                        >
                            Sorted by: {{
                                sortField === 'latest_version_published_at' ? 'Latest Update' :
                                    sortField === 'initially_published_at' ? 'Initial Release' :
                                        sortField === 'english_word_count' ? 'Word Count' :
                                            sortField === 'rating_count' ? 'Review Count' :
                                                sortField === 'name' ? 'Name' : 'Unknown'
                            }} {{ sortDirection === 'asc' ? '↑' : '↓' }}
                            <span class="ml-2">×</span>
                        </button>
                        <FilterBadge v-for="filter in activeFilters"
                                     :key="filter.id"
                                     :label="filter.label"
                                     :type="filter.type"
                                     :value="filter.value"
                                     @remove="toggleFilter(filter.type, filter.value)"/>
                    </div>
                </div>

                <!-- Games Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <GameCard v-for="game in games.data"
                              :key="game.id"
                              :game="game"
                              :nsfw="nsfw"
                              :selected-engines="selectedEngines"
                              :selected-languages="selectedLanguages"
                              :selected-platforms="selectedPlatforms"
                              :selected-statuses="selectedStatuses"
                              :sfw="sfw"
                              @toggle-filter="toggleFilter"
                    />
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <select v-model="perPage"
                            class="px-4 py-2 rounded-lg border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"
                            @change="page=1">
                        <option :value="9">9 per page</option>
                        <option :value="18">18 per page</option>
                        <option :value="27">27 per page</option>
                    </select>
                    <Pagination v-if="games.links.length > 3" :links="games.links"/>
                </div>
            </div>
        </div>

        <GameFiltersDialog
            :filter-options="filterOptions"
            :nsfw="nsfw"
            :selected-engines="selectedEngines"
            :selected-languages="selectedLanguages"
            :selected-platforms="selectedPlatforms"
            :selected-statuses="selectedStatuses"
            :sfw="sfw"
            @toggle-filter="toggleFilter"
            @toggle-nsfw="() => {nsfw = !nsfw; page = 1;}"
            @toggle-sfw="() => {sfw = !sfw; page = 1;}"
            @reset-filters="emit('reset-filters')"
        />

        <SortDialog
            :sort-direction="sortDirection"
            :sort-field="sortField"
            @sort="sortBy"
        />
    </AppLayout>
</template>
