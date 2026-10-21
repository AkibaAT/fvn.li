import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams } from 'svelte/reactivity';
import type { CurrentFilters } from '@/types';
import { clearAllFiltersPatch, type ArrayFilterKey } from '@/components/games/filter/filter-fields';

interface UseGameFiltersProps {
    getCurrentFilters: () => CurrentFilters;
    onGamesPage?: boolean;
}

export const GAMES_PER_PAGE_OPTIONS = [12, 24, 48] as const;
export const DEFAULT_GAMES_PER_PAGE = 12;

const LIST_ONLY_KEYS: readonly string[] = ['page', 'perPage', 'sort', 'direction'];

function defaultGamesSort(search: string | undefined): string {
    return search?.trim() ? 'relevance' : 'first_visible_at';
}

export function serializeFilters(filters: CurrentFilters): SvelteURLSearchParams {
    const searchParams = new SvelteURLSearchParams();

    for (const [key, value] of Object.entries(filters)) {
        if (Array.isArray(value)) {
            value.forEach((item) => searchParams.append(`${key}[]`, String(item)));
        } else if (value !== false && value !== '' && value !== null && value !== undefined) {
            searchParams.set(key, String(value));
        }
    }

    return searchParams;
}

export function buildGamesQuery(current: CurrentFilters, changes: Partial<CurrentFilters>): SvelteURLSearchParams {
    const changedKeys = Object.keys(changes);
    const params: CurrentFilters = { ...current, ...changes };

    delete params.usingDefaultLanguages;
    delete params.usingDefaultExcludedTags;
    if (current.usingDefaultLanguages && !('selectedLanguages' in changes)) delete params.selectedLanguages;
    if (current.usingDefaultExcludedTags && !('excludedTags' in changes)) delete params.excludedTags;

    if (
        (Array.isArray(changes.selectedLanguages) && changes.selectedLanguages.length === 0) ||
        (Array.isArray(changes.excludedTags) && changes.excludedTags.length === 0)
    ) {
        params.noDefaults = true;
    }

    if (changedKeys.some((key) => !LIST_ONLY_KEYS.includes(key)) || !params.page || params.page <= 1) delete params.page;
    if (params.perPage === DEFAULT_GAMES_PER_PAGE) delete params.perPage;
    if (params.direction === 'desc') delete params.direction;
    if (params.sort === defaultGamesSort(params.search)) delete params.sort;

    return serializeFilters(params);
}

export function useGameFilters({ getCurrentFilters, onGamesPage = false }: UseGameFiltersProps) {
    const updateFilters = (changes: Partial<CurrentFilters>) => {
        const changedKeys = Object.keys(changes);
        const listOnly = changedKeys.length > 0 && changedKeys.every((key) => LIST_ONLY_KEYS.includes(key));
        const url = `/games?${buildGamesQuery(getCurrentFilters(), changes).toString()}`;

        if (onGamesPage) {
            router.visit(url, {
                preserveState: true,
                preserveScroll: true,
                ...(listOnly ? { only: ['games', 'currentFilters'] } : {}),
            });
        } else {
            router.visit(url);
        }
    };

    const buildPageUrl = (page: number) => `/games?${buildGamesQuery(getCurrentFilters(), { page }).toString()}`;

    const toggleFilter = (property: ArrayFilterKey, value: string) => {
        const values = getCurrentFilters()[property] ?? [];
        updateFilters({ [property]: values.includes(value) ? values.filter((item) => item !== value) : [...values, value] });
    };

    const clearFilters = () => updateFilters({ ...clearAllFiltersPatch(), noDefaults: true });

    return {
        updateFilters,
        toggleFilter,
        clearFilters,
        buildPageUrl,
    };
}
