import { describe, expect, test } from 'vitest';
import { serializeUrlFilters } from './useUrlSyncedFilters.svelte';

describe('serializeUrlFilters', () => {
    test('preserves explicit booleans while omitting inactive optional filters', () => {
        const params = serializeUrlFilters({
            page: 2,
            showOnlyReviews: false,
            platform: '',
            stars: null,
            sortField: 'rating',
        });

        expect(params.toString()).toBe('page=2&showOnlyReviews=false&sortField=rating');
    });
});
