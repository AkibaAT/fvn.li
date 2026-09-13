import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { flushSync } from 'svelte';

const router = vi.hoisted(() => ({ visit: vi.fn(), on: vi.fn((_event: string, _callback: () => void) => vi.fn()) }));
vi.mock('@inertiajs/svelte', () => ({ router }));
vi.mock('ziggy-js', () => ({ route: () => '/games' }));

import { getSearchFilterParams, useSearch } from './useSearch.svelte';

describe('getSearchFilterParams', () => {
    test('preserves repeated filters while dropping stale search pagination', () => {
        const params = getSearchFilterParams('?page=4&perPage=16&platform%5B%5D=windows&platform%5B%5D=linux&sort=rating_score&search=old');
        expect(params.getAll('platform[]')).toEqual(['windows', 'linux']);
        expect(params.get('perPage')).toBe('16');
        expect(params.get('sort')).toBe('rating_score');
        expect(params.has('search')).toBe(false);
        expect(params.has('page')).toBe(false);
    });
});

describe('search navigation', () => {
    let dispose: () => void;
    let search: ReturnType<typeof useSearch>;

    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
        window.history.replaceState({ page: { component: 'games/index' } }, '', '/games?platform[]=windows&platform[]=linux');
        dispose = $effect.root(() => {
            search = useSearch({ isGamesPage: true });
        });
        flushSync();
    });

    afterEach(() => dispose());

    test('typing preserves Inertia state and submits all selected filters', () => {
        search.performLiveSearch('fox');
        expect(window.history.state).toEqual({ page: { component: 'games/index' } });
        vi.advanceTimersByTime(500);
        const url = new URL(router.visit.mock.calls[0][0], window.location.origin);
        expect(url.searchParams.getAll('platform[]')).toEqual(['windows', 'linux']);
        expect(url.searchParams.get('search')).toBe('fox');
    });

    test('clearing cancels a pending search', () => {
        search.performLiveSearch('fox');
        search.handleSearchClear();
        vi.runAllTimers();
        expect(router.visit).toHaveBeenCalledTimes(1);
        expect(router.visit.mock.calls[0][0]).not.toContain('search=');
    });

    test('submitting cancels the duplicate debounced visit', () => {
        search.searchTerm = 'fox';
        search.performLiveSearch('fox');
        search.handleSearchSubmit(new Event('submit'));
        vi.runAllTimers();
        expect(router.visit).toHaveBeenCalledTimes(1);
    });

    test('navigation updates search text and does not carry another page filters into game search', () => {
        window.history.replaceState({}, '', '/games?search=fox');
        router.on.mock.calls.find(([event]) => event === 'navigate')![1]();
        expect(search.searchTerm).toBe('fox');
        window.history.replaceState({}, '', '/ratings?stars=5');
        router.on.mock.calls.find(([event]) => event === 'navigate')![1]();
        expect(search.searchTerm).toBe('');
        search.searchTerm = 'wolf';
        search.handleSearchSubmit(new Event('submit'));
        expect(router.visit.mock.calls[0][0]).toBe('/games?search=wolf');
    });

    test('a finished search does not overwrite newer typing waiting for debounce', () => {
        search.searchTerm = 'foxes';
        search.performLiveSearch('foxes');
        window.history.replaceState({}, '', '/games?search=fox');
        router.on.mock.calls.find(([event]) => event === 'navigate')![1]();
        expect(search.searchTerm).toBe('foxes');
        vi.advanceTimersByTime(500);
        expect(router.visit.mock.calls[0][0]).toContain('search=foxes');
    });

    test.each(['navigation', 'popstate', 'unmount'])('%s cancels a pending search', (action) => {
        search.performLiveSearch('fox');
        if (action === 'navigation') router.on.mock.calls[0][1]();
        if (action === 'popstate') window.dispatchEvent(new PopStateEvent('popstate'));
        if (action === 'unmount') dispose();
        vi.runAllTimers();
        expect(router.visit).not.toHaveBeenCalled();
    });
});
