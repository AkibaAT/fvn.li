import { describe, expect, test } from 'vitest';

import { getSearchFilterParams } from './useSearch.svelte';

describe('getSearchFilterParams', () => {
    test('preserves filters while dropping stale search pagination', () => {
        expect(getSearchFilterParams('?page=4&perPage=16&platform%5B%5D=windows&sort=rating_score&search=old')).toEqual({
            perPage: '16',
            'platform[]': 'windows',
            sort: 'rating_score',
        });
    });

    test('drops page when clearing search as well as changing search', () => {
        expect(getSearchFilterParams('?page=3&language%5B%5D=eng')).toEqual({
            'language[]': 'eng',
        });
    });
});
