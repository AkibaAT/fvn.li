import type { CurrentFilters, FilterOptions } from '@/types';

export type ArrayFilterKey =
    | 'selectedStatuses'
    | 'selectedEngines'
    | 'selectedPlatforms'
    | 'selectedStorePlatforms'
    | 'selectedLanguages'
    | 'selectedGameJams'
    | 'selectedTags'
    | 'excludedTags'
    | 'selectedPrivateTags';

type FlagFilterKey = 'sfw' | 'nsfw' | 'showFree' | 'showPaid' | 'showDemo' | 'showSale' | 'showLost' | 'showIgnored';

interface LanguageOption {
    ref_name?: string;
    name?: string;
    flag_code?: string;
}

export type FilterOptionItem = string | LanguageOption;
type FilterOptionMap = Record<string, FilterOptionItem>;
type OptionIcon = 'flag' | 'platform' | 'store';

export type FilterFieldId =
    | 'tags'
    | 'privateTag'
    | 'status'
    | 'language'
    | 'readingTime'
    | 'platform'
    | 'storePlatform'
    | 'content'
    | 'price'
    | 'engine'
    | 'gameJam'
    | 'visibility';

interface FieldBase {
    id: FilterFieldId;
    label: string;
    group: string;
}

interface MultiFilterField extends FieldBase {
    kind: 'multi';
    property: ArrayFilterKey;
    excludeProperty?: ArrayFilterKey;
    options: (filters: FilterOptions) => FilterOptionMap;
    icon?: OptionIcon;
    hideWhenEmpty?: boolean;
}

interface SingleFilterField extends FieldBase {
    kind: 'single';
    property: 'readingTime';
    options: (filters: FilterOptions) => FilterOptionMap;
}

interface FlagDef {
    key: FlagFilterKey;
    label: string;
    chipLabel: string;
}

interface FlagsFilterField extends FieldBase {
    kind: 'flags';
    flags: FlagDef[];
}

export type FilterField = MultiFilterField | SingleFilterField | FlagsFilterField;

export const FILTER_FIELDS: readonly FilterField[] = [
    {
        id: 'tags',
        label: 'Tags',
        group: 'Refine',
        kind: 'multi',
        property: 'selectedTags',
        excludeProperty: 'excludedTags',
        options: (filters) => filters.tags,
    },
    {
        id: 'privateTag',
        label: 'Private tags',
        group: 'Refine',
        kind: 'multi',
        property: 'selectedPrivateTags',
        options: (filters) => filters.privateTags,
        hideWhenEmpty: true,
    },
    { id: 'status', label: 'Status', group: 'Refine', kind: 'multi', property: 'selectedStatuses', options: (filters) => filters.statuses },
    {
        id: 'language',
        label: 'Language',
        group: 'Refine',
        kind: 'multi',
        property: 'selectedLanguages',
        options: (filters) => filters.languages,
        icon: 'flag',
    },
    {
        id: 'readingTime',
        label: 'Reading time',
        group: 'Refine',
        kind: 'single',
        property: 'readingTime',
        options: (filters) => filters.readingTimeOptions,
    },
    {
        id: 'platform',
        label: 'Platform',
        group: 'Platform & store',
        kind: 'multi',
        property: 'selectedPlatforms',
        options: (filters) => filters.platforms,
        icon: 'platform',
    },
    {
        id: 'storePlatform',
        label: 'Store',
        group: 'Platform & store',
        kind: 'multi',
        property: 'selectedStorePlatforms',
        options: (filters) => filters.storePlatforms,
        icon: 'store',
    },
    {
        id: 'content',
        label: 'Content rating',
        group: 'Content & price',
        kind: 'flags',
        flags: [
            { key: 'sfw', label: 'Safe for Work', chipLabel: 'SFW' },
            { key: 'nsfw', label: 'NSFW Content', chipLabel: 'NSFW' },
        ],
    },
    {
        id: 'price',
        label: 'Price & availability',
        group: 'Content & price',
        kind: 'flags',
        flags: [
            { key: 'showFree', label: 'Free Games', chipLabel: 'Free' },
            { key: 'showPaid', label: 'Paid Games', chipLabel: 'Paid' },
            { key: 'showDemo', label: 'Has Demo', chipLabel: 'Has Demo' },
            { key: 'showSale', label: 'On Sale', chipLabel: 'On Sale' },
            { key: 'showLost', label: 'Lost media', chipLabel: 'Lost media' },
        ],
    },
    { id: 'engine', label: 'Engine', group: 'Details', kind: 'multi', property: 'selectedEngines', options: (filters) => filters.gameEngines },
    { id: 'gameJam', label: 'Game jam', group: 'Details', kind: 'multi', property: 'selectedGameJams', options: (filters) => filters.gameJams },
    {
        id: 'visibility',
        label: 'Visibility',
        group: 'Details',
        kind: 'flags',
        flags: [{ key: 'showIgnored', label: 'Show ignored games', chipLabel: 'Showing ignored' }],
    },
];

export function findFilterField(id: string): FilterField | undefined {
    return FILTER_FIELDS.find((field) => field.id === id);
}

export function availableFilterFields(filters: FilterOptions): FilterField[] {
    return FILTER_FIELDS.filter((field) => field.kind !== 'multi' || !field.hideWhenEmpty || Object.keys(field.options(filters)).length > 0);
}

export function optionLabel(value: string, item: FilterOptionItem | undefined): string {
    if (!item) return value;
    if (typeof item === 'string') return item;
    return item.name || item.ref_name || value;
}

export function optionFlagCode(item: FilterOptionItem | undefined): string | undefined {
    return typeof item === 'object' ? item.flag_code : undefined;
}

interface FilterChip {
    key: string;
    field: FilterField;
    value?: string;
    label: string;
    accessibleLabel: string;
    excluded: boolean;
    flagCode?: string;
    clear: Partial<CurrentFilters>;
}

function arrayChips(
    field: MultiFilterField,
    property: ArrayFilterKey,
    excluded: boolean,
    current: CurrentFilters,
    options: FilterOptionMap,
): FilterChip[] {
    const values = current[property] ?? [];
    return values.map((value) => {
        const label = optionLabel(value, options[value]);
        return {
            key: `${property}:${value}`,
            field,
            value,
            label,
            accessibleLabel: excluded ? `Excluding ${label}` : label,
            excluded,
            flagCode: field.icon === 'flag' ? optionFlagCode(options[value]) : undefined,
            clear: { [property]: values.filter((item) => item !== value) },
        };
    });
}

export function fieldChips(field: FilterField, current: CurrentFilters, filters: FilterOptions): FilterChip[] {
    switch (field.kind) {
        case 'multi': {
            const options = field.options(filters);
            return [
                ...arrayChips(field, field.property, false, current, options),
                ...(field.excludeProperty ? arrayChips(field, field.excludeProperty, true, current, options) : []),
            ];
        }
        case 'single': {
            const value = current[field.property];
            if (!value) return [];
            const label = optionLabel(value, field.options(filters)[value]);
            return [{ key: field.property, field, value, label, accessibleLabel: label, excluded: false, clear: { [field.property]: '' } }];
        }
        case 'flags':
            return field.flags
                .filter((flag) => current[flag.key])
                .map((flag) => ({
                    key: flag.key,
                    field,
                    label: flag.chipLabel,
                    accessibleLabel: flag.chipLabel,
                    excluded: false,
                    clear: { [flag.key]: false },
                }));
    }
}

export function buildFilterChips(current: CurrentFilters, filters: FilterOptions): FilterChip[] {
    return FILTER_FIELDS.flatMap((field) => fieldChips(field, current, filters));
}

export function fieldClearPatch(field: FilterField): Partial<CurrentFilters> {
    switch (field.kind) {
        case 'multi':
            return { [field.property]: [], ...(field.excludeProperty ? { [field.excludeProperty]: [] } : {}) };
        case 'single':
            return { [field.property]: '' };
        case 'flags':
            return Object.fromEntries(field.flags.map((flag) => [flag.key, false]));
    }
}

export function clearAllFiltersPatch(): Partial<CurrentFilters> {
    return Object.assign({ search: '' }, ...FILTER_FIELDS.map(fieldClearPatch));
}
