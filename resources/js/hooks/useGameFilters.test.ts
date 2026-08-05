import { describe, expect, test } from 'vitest';
import type { CurrentFilters } from '@/types';
import { serializeFilters } from './useGameFilters.svelte';

describe('serializeFilters', () => {
    test('preserves active arrays and pagination while omitting inactive values', () => {
        const params = serializeFilters({
            search: 'fox',
            selectedPlatforms: ['windows', 'linux'],
            nsfw: false,
            page: 3,
            perPage: 16,
        } as CurrentFilters);

        expect(params.get('search')).toBe('fox');
        expect(params.getAll('selectedPlatforms[]')).toEqual(['windows', 'linux']);
        expect(params.has('nsfw')).toBe(false);
        expect(params.get('page')).toBe('3');
        expect(params.get('perPage')).toBe('16');
    });
});
