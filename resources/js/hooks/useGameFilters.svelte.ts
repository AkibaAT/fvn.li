import { router } from '@inertiajs/svelte';
import { SvelteURLSearchParams } from 'svelte/reactivity';
import type { CurrentFilters, FilterOptions } from '@/types';

interface UseGameFiltersProps {
    getCurrentFilters: () => CurrentFilters;
    getFilters: () => FilterOptions;
    onGamesPage?: boolean;
}

export function useGameFilters({ getCurrentFilters, getFilters, onGamesPage = false }: UseGameFiltersProps) {
    const updateFilters = (newFilters: Partial<CurrentFilters>) => {
        const paginationKeys = ['page', 'perPage', 'sort', 'direction'];

        const hasFilterChanges = Object.keys(newFilters).some((key) => !paginationKeys.includes(key));

        const params = {
            ...getCurrentFilters(),
            ...newFilters,
            ...((Array.isArray(newFilters.selectedLanguages) && newFilters.selectedLanguages.length === 0) ||
            (Array.isArray(newFilters.excludedTags) && newFilters.excludedTags.length === 0)
                ? { noDefaults: true }
                : {}),
            ...(hasFilterChanges ? { page: 1 } : {}),
        };

        Object.keys(params).forEach((key) => {
            const value = params[key as keyof CurrentFilters];
            if (Array.isArray(value) && value.length === 0) {
                delete params[key as keyof CurrentFilters];
            } else if (value === false || value === '' || value === null || value === undefined) {
                delete params[key as keyof CurrentFilters];
            }
        });

        const searchParams = new SvelteURLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (Array.isArray(value)) {
                value.forEach((v) => searchParams.append(`${key}[]`, String(v)));
            } else if (value !== undefined && value !== null) {
                searchParams.set(key, String(value));
            }
        }

        const url = `/games?${searchParams.toString()}`;

        if (onGamesPage) {
            router.visit(url, { replace: true, preserveState: true, preserveScroll: true });
        } else {
            router.visit(url);
        }
    };

    const toggleFilter = (type: string, value: string) => {
        const propertyMap: Record<string, keyof CurrentFilters> = {
            status: 'selectedStatuses',
            engine: 'selectedEngines',
            platform: 'selectedPlatforms',
            storePlatform: 'selectedStorePlatforms',
            language: 'selectedLanguages',
            gameJam: 'selectedGameJams',
            tag: 'selectedTags',
            excludeTag: 'excludedTags',
        };

        const propertyName = propertyMap[type];
        if (!propertyName) return;

        const currentArray = (getCurrentFilters()[propertyName] as string[]) || [];
        const newArray = currentArray.includes(value) ? currentArray.filter((item) => item !== value) : [...currentArray, value];

        updateFilters({ [propertyName]: newArray });
    };

    const clearFilters = () => {
        updateFilters({
            search: '',
            selectedStatuses: [],
            selectedEngines: [],
            selectedPlatforms: [],
            selectedStorePlatforms: [],
            selectedLanguages: [],
            selectedGameJams: [],
            selectedTags: [],
            excludedTags: [],
            readingTime: '',
            nsfw: false,
            sfw: false,
            showPaid: false,
            showFree: false,
            showDemo: false,
            showSale: false,
            delisted: false,
            noDefaults: true,
        });
    };

    const hasActiveFilters = () => {
        const {
            selectedStatuses,
            selectedEngines,
            selectedPlatforms,
            selectedStorePlatforms,
            selectedLanguages,
            selectedGameJams,
            selectedTags,
            excludedTags,
            readingTime,
            nsfw,
            sfw,
            showPaid,
            showFree,
            showDemo,
            showSale,
            delisted,
        } = getCurrentFilters();

        return Boolean(
            (selectedStatuses && selectedStatuses.length > 0) ||
            (selectedEngines && selectedEngines.length > 0) ||
            (selectedPlatforms && selectedPlatforms.length > 0) ||
            (selectedStorePlatforms && selectedStorePlatforms.length > 0) ||
            (selectedLanguages && selectedLanguages.length > 0) ||
            (selectedGameJams && selectedGameJams.length > 0) ||
            (selectedTags && selectedTags.length > 0) ||
            (excludedTags && excludedTags.length > 0) ||
            readingTime ||
            nsfw ||
            sfw ||
            showPaid ||
            showFree ||
            showDemo ||
            showSale ||
            delisted,
        );
    };

    type ActiveChip = {
        key: string;
        type: string;
        value?: string;
        label: string;
        flagCode?: string;
        onClear?: () => void;
    };

    const buildActiveFilterChips = (): ActiveChip[] => {
        const chips: ActiveChip[] = [];

        const pushArrayChips = (
            values: string[] | undefined,
            options: Record<string, string | { ref_name?: string; name?: string; flag_code?: string }> | undefined,
            keyPrefix: string,
            clearFn: (value: string) => void,
        ) => {
            if (!values || values.length === 0) return;
            values.forEach((value) => {
                const opt = options ? options[value] : undefined;
                const label = typeof opt === 'string' ? opt : opt?.ref_name || opt?.name || value;
                chips.push({
                    key: `${keyPrefix}:${value}`,
                    type: keyPrefix,
                    value,
                    label,
                    flagCode:
                        keyPrefix === 'language'
                            ? typeof options?.[value] === 'object'
                                ? (options?.[value] as { flag_code?: string })?.flag_code
                                : undefined
                            : undefined,
                    onClear: () => clearFn(value),
                });
            });
        };

        pushArrayChips(getCurrentFilters().selectedStatuses, getFilters().statuses, 'status', (v) => toggleFilter('status', v));
        pushArrayChips(getCurrentFilters().selectedEngines, getFilters().gameEngines, 'engine', (v) => toggleFilter('engine', v));
        pushArrayChips(getCurrentFilters().selectedPlatforms, getFilters().platforms, 'platform', (v) => toggleFilter('platform', v));
        pushArrayChips(getCurrentFilters().selectedStorePlatforms, getFilters().storePlatforms, 'storePlatform', (v) =>
            toggleFilter('storePlatform', v),
        );
        pushArrayChips(getCurrentFilters().selectedLanguages, getFilters().languages, 'language', (v) => toggleFilter('language', v));
        pushArrayChips(getCurrentFilters().selectedGameJams, getFilters().gameJams, 'gameJam', (v) => toggleFilter('gameJam', v));
        pushArrayChips(getCurrentFilters().selectedTags, getFilters().tags, 'tag', (v) => toggleFilter('tag', v));

        // Excluded tags
        if (getCurrentFilters().excludedTags && getCurrentFilters().excludedTags!.length > 0) {
            getCurrentFilters().excludedTags!.forEach((value) => {
                const label = getFilters().tags?.[value] ?? value;
                chips.push({
                    key: `excludeTag:${value}`,
                    type: 'excludeTag',
                    value,
                    label: `Exclude: ${label}`,
                    onClear: () => toggleFilter('excludeTag', value),
                });
            });
        }

        // Reading time
        if (getCurrentFilters().readingTime) {
            const rt = getCurrentFilters().readingTime!;
            const readingTimeLabels: Record<string, string> = {
                short: 'Short (< 10k words)',
                medium: 'Medium (10k-50k words)',
                long: 'Long (> 50k words)',
            };
            chips.push({
                key: 'readingTime',
                type: 'readingTime',
                label: readingTimeLabels[rt] || rt,
                onClear: () => updateFilters({ readingTime: '' }),
            });
        }

        // Booleans
        if (getCurrentFilters().sfw)
            chips.push({
                key: 'sfw',
                type: 'sfw',
                label: 'SFW',
                onClear: () => updateFilters({ sfw: false }),
            });
        if (getCurrentFilters().nsfw)
            chips.push({
                key: 'nsfw',
                type: 'nsfw',
                label: 'NSFW',
                onClear: () => updateFilters({ nsfw: false }),
            });
        if (getCurrentFilters().showFree)
            chips.push({
                key: 'free',
                type: 'free',
                label: 'Free',
                onClear: () => updateFilters({ showFree: false }),
            });
        if (getCurrentFilters().showPaid)
            chips.push({
                key: 'paid',
                type: 'paid',
                label: 'Paid',
                onClear: () => updateFilters({ showPaid: false }),
            });
        if (getCurrentFilters().showDemo)
            chips.push({
                key: 'demo',
                type: 'demo',
                label: 'Has Demo',
                onClear: () => updateFilters({ showDemo: false }),
            });
        if (getCurrentFilters().showSale)
            chips.push({
                key: 'sale',
                type: 'sale',
                label: 'On Sale',
                onClear: () => updateFilters({ showSale: false }),
            });
        if (getCurrentFilters().delisted)
            chips.push({
                key: 'delisted',
                type: 'delisted',
                label: 'Delisted',
                onClear: () => updateFilters({ delisted: false }),
            });

        return chips;
    };

    const getActiveFilterCount = () => {
        return buildActiveFilterChips().length;
    };

    return {
        updateFilters,
        toggleFilter,
        clearFilters,
        hasActiveFilters,
        buildActiveFilterChips,
        getActiveFilterCount,
    };
}
