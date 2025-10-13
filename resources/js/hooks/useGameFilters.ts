import {router} from '@inertiajs/react';
import type {CurrentFilters, FilterOptions} from '@/types';

interface UseGameFiltersProps {
    currentFilters: CurrentFilters;
    filters: FilterOptions;
    onGamesPage?: boolean;
}

export function useGameFilters({currentFilters, filters, onGamesPage = false}: UseGameFiltersProps) {
    const updateFilters = (newFilters: Partial<CurrentFilters>) => {
        const params = {...currentFilters, ...newFilters};

        // Remove empty arrays and false values
        Object.keys(params).forEach((key) => {
            const value = params[key as keyof CurrentFilters];
            if (Array.isArray(value) && value.length === 0) {
                delete params[key as keyof CurrentFilters];
            } else if (
                value === false ||
                value === '' ||
                value === null ||
                value === undefined
            ) {
                delete params[key as keyof CurrentFilters];
            }
        });

        if (onGamesPage) {
            // Update current games page
            router.get(route('games.index'), params, {
                preserveState: true,
                preserveScroll: true,
            });
        } else {
            // Navigate to games page with filters
            router.get(route('games.index'), params, {
                preserveState: false,
            });
        }
    };

    const toggleFilter = (type: string, value: string) => {
        // Map filter types to their correct property names
        const propertyMap: Record<string, keyof CurrentFilters> = {
            status: 'selectedStatuses',
            engine: 'selectedEngines',
            platform: 'selectedPlatforms',
            language: 'selectedLanguages',
            gameJam: 'selectedGameJams',
            tag: 'selectedTags',
        };

        const propertyName = propertyMap[type];
        if (!propertyName) return;

        const currentArray = (currentFilters[propertyName] as string[]) || [];
        const newArray = currentArray.includes(value)
            ? currentArray.filter((item) => item !== value)
            : [...currentArray, value];

        updateFilters({[propertyName]: newArray});
    };

    const clearFilters = () => {
        updateFilters({
            search: '',
            selectedStatuses: [],
            selectedEngines: [],
            selectedPlatforms: [],
            selectedLanguages: [],
            selectedGameJams: [],
            selectedTags: [],
            nsfw: false,
            sfw: false,
            showPaid: false,
            showFree: false,
            showDemo: false,
        });
    };

    const hasActiveFilters = () => {
        const {
            selectedStatuses,
            selectedEngines,
            selectedPlatforms,
            selectedLanguages,
            selectedGameJams,
            selectedTags,
            nsfw,
            sfw,
            showPaid,
            showFree,
            showDemo,
        } = currentFilters;

        return Boolean(
            (selectedStatuses && selectedStatuses.length > 0) ||
            (selectedEngines && selectedEngines.length > 0) ||
            (selectedPlatforms && selectedPlatforms.length > 0) ||
            (selectedLanguages && selectedLanguages.length > 0) ||
            (selectedGameJams && selectedGameJams.length > 0) ||
            (selectedTags && selectedTags.length > 0) ||
            nsfw ||
            sfw ||
            showPaid ||
            showFree ||
            showDemo,
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

        // Helper to push chips for arrays with mapping option labels
        const pushArrayChips = (
            values: string[] | undefined,
            options: Record<string, string | {ref_name?: string; name?: string; flag_code?: string}> | undefined,
            keyPrefix: string,
            clearFn: (value: string) => void,
        ) => {
            if (!values || values.length === 0) return;
            values.forEach((value) => {
                const opt = options ? options[value] : undefined;
                const label =
                    typeof opt === 'string'
                        ? opt
                        : opt?.ref_name || opt?.name || value;
                chips.push({
                    key: `${keyPrefix}:${value}`,
                    type: keyPrefix,
                    value,
                    label,
                    flagCode:
                        keyPrefix === 'language'
                            ? typeof options?.[value] === 'object'
                                ? (options?.[value] as {flag_code?: string})?.flag_code
                                : undefined
                            : undefined,
                    onClear: () => clearFn(value),
                });
            });
        };

        pushArrayChips(
            currentFilters.selectedStatuses,
            filters.statuses,
            'status',
            (v) => toggleFilter('status', v),
        );
        pushArrayChips(
            currentFilters.selectedEngines,
            filters.gameEngines,
            'engine',
            (v) => toggleFilter('engine', v),
        );
        pushArrayChips(
            currentFilters.selectedPlatforms,
            filters.platforms,
            'platform',
            (v) => toggleFilter('platform', v),
        );
        pushArrayChips(
            currentFilters.selectedLanguages,
            filters.languages,
            'language',
            (v) => toggleFilter('language', v),
        );
        pushArrayChips(
            currentFilters.selectedGameJams,
            filters.gameJams,
            'gameJam',
            (v) => toggleFilter('gameJam', v),
        );
        pushArrayChips(currentFilters.selectedTags, filters.tags, 'tag', (v) =>
            toggleFilter('tag', v),
        );

        // Booleans
        if (currentFilters.sfw)
            chips.push({
                key: 'sfw',
                type: 'sfw',
                label: 'SFW',
                onClear: () => updateFilters({sfw: false}),
            });
        if (currentFilters.nsfw)
            chips.push({
                key: 'nsfw',
                type: 'nsfw',
                label: 'NSFW',
                onClear: () => updateFilters({nsfw: false}),
            });
        if (currentFilters.showFree)
            chips.push({
                key: 'free',
                type: 'free',
                label: 'Free',
                onClear: () => updateFilters({showFree: false}),
            });
        if (currentFilters.showPaid)
            chips.push({
                key: 'paid',
                type: 'paid',
                label: 'Paid',
                onClear: () => updateFilters({showPaid: false}),
            });
        if (currentFilters.showDemo)
            chips.push({
                key: 'demo',
                type: 'demo',
                label: 'Has Demo',
                onClear: () => updateFilters({showDemo: false}),
            });

        return chips;
    };

    const getActiveFilterCount = () => {
        return buildActiveFilterChips().length;
    };

    const getChipColorClass = (type?: string) => {
        switch (type) {
            case 'search':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'status':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'platform':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'language':
                return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300';
            case 'engine':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
            case 'tag':
                return 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300';
            case 'gameJam':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300';
            case 'sfw':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'nsfw':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
            case 'free':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'paid':
                return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
            case 'demo':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300';
            case 'suspended':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
            case 'hidden':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
        }
    };

    return {
        updateFilters,
        toggleFilter,
        clearFilters,
        hasActiveFilters,
        buildActiveFilterChips,
        getActiveFilterCount,
        getChipColorClass,
    };
}