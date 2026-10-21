import { describe, expect, test } from 'vitest';
import type { FilterOptions } from '@/types';
import { availableFilterFields, buildFilterChips, clearAllFiltersPatch, fieldClearPatch, findFilterField } from './filter-fields';

const options: FilterOptions = {
    statuses: { Released: 'Released' },
    gameEngines: {},
    platforms: { windows: 'Windows' },
    storePlatforms: {},
    languages: { eng: { ref_name: 'English', flag_code: 'us' } },
    gameJams: {},
    tags: { '1': 'Romance' },
    privateTags: {},
    readingTimeOptions: { short: 'Short (< 10k words)' },
};

describe('filter field catalog', () => {
    test('builds one chip per active value with a patch that removes only that value', () => {
        const chips = buildFilterChips({ selectedLanguages: ['eng'], selectedTags: ['1', '2'], readingTime: 'short', showSale: true }, options);

        expect(chips.map((chip) => chip.label)).toEqual(['Romance', '2', 'English', 'Short (< 10k words)', 'On Sale']);
        expect(chips.find((chip) => chip.label === 'English')?.flagCode).toBe('us');
        expect(chips[0].clear).toEqual({ selectedTags: ['2'] });
        expect(chips.at(-1)?.clear).toEqual({ showSale: false });
    });

    test('labels excluded values for assistive tech while keeping the visible label', () => {
        const [chip] = buildFilterChips({ excludedTags: ['1'] }, options);

        expect(chip).toMatchObject({ label: 'Romance', accessibleLabel: 'Excluding Romance', excluded: true, clear: { excludedTags: [] } });
        expect(chip.field.id).toBe('tags');
    });

    test('clear patches cover every field, including include/exclude pairs and visibility', () => {
        expect(fieldClearPatch(findFilterField('tags')!)).toEqual({ selectedTags: [], excludedTags: [] });
        expect(clearAllFiltersPatch()).toMatchObject({ search: '', showIgnored: false, readingTime: '', sfw: false, selectedPrivateTags: [] });
        expect(buildFilterChips(clearAllFiltersPatch(), options)).toEqual([]);
    });

    test('hides private tags until the user has some', () => {
        expect(availableFilterFields(options).map((field) => field.id)).not.toContain('privateTag');
        expect(availableFilterFields({ ...options, privateTags: { '5': 'Favorites' } }).map((field) => field.id)).toContain('privateTag');
    });
});
