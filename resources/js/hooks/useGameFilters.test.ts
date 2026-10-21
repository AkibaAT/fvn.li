import { describe, expect, test } from 'vitest';
import type { CurrentFilters } from '@/types';
import { buildGamesQuery, serializeFilters } from './useGameFilters.svelte';

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

describe('buildGamesQuery', () => {
    const serverEcho: CurrentFilters = {
        search: '',
        selectedLanguages: ['eng'],
        excludedTags: ['9'],
        sort: 'first_visible_at',
        direction: 'desc',
        perPage: 12,
        page: 1,
        noDefaults: false,
        usingDefaultLanguages: true,
        usingDefaultExcludedTags: true,
    };

    test('leaves server defaults out of the URL so preference defaults stay implicit', () => {
        const query = buildGamesQuery(serverEcho, { nsfw: true }).toString();

        expect(query).toBe('nsfw=true');
    });

    test('keeps explicit preference overrides and non-default list settings', () => {
        const params = buildGamesQuery(serverEcho, { selectedLanguages: ['jpn'], sort: 'rating_score', direction: 'asc', perPage: 24 });

        expect(params.getAll('selectedLanguages[]')).toEqual(['jpn']);
        expect(params.has('excludedTags[]')).toBe(false);
        expect(params.get('sort')).toBe('rating_score');
        expect(params.get('direction')).toBe('asc');
        expect(params.get('perPage')).toBe('24');
    });

    test('treats relevance as the default sort only while searching', () => {
        const searching = { ...serverEcho, search: 'fox', sort: 'relevance' };

        expect(buildGamesQuery(searching, {}).has('sort')).toBe(false);
        expect(buildGamesQuery(serverEcho, { sort: 'relevance' }).get('sort')).toBe('relevance');
    });

    test('keeps the page for list-only changes and resets it for filter changes', () => {
        const onPageThree = { ...serverEcho, page: 3 };

        expect(buildGamesQuery(onPageThree, { sort: 'rating_score' }).get('page')).toBe('3');
        expect(buildGamesQuery(onPageThree, { showFree: true }).has('page')).toBe(false);
    });

    test('opts out of preference defaults when a defaulted list is emptied', () => {
        const params = buildGamesQuery(serverEcho, { selectedLanguages: [] });

        expect(params.get('noDefaults')).toBe('true');
        expect(params.has('selectedLanguages[]')).toBe(false);
    });
});
