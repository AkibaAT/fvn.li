import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import {
    addGameToCustomList,
    addGameToDefaultList,
    destroyListEntry,
    destroyVnList,
    fetchGameListMemberships,
    fetchUserLists,
    moveListEntry,
    reorderListEntries,
    storeVnList,
    toggleAllListUpdates,
    toggleUserProgressUpdates,
    toggleVnListVisibility,
    updateListEntry,
    updateVnList,
} from './lists';
import type { ListEntryFormPayload } from './lists';

const route = vi.fn((name: string, _params?: unknown) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

const entryPayload: ListEntryFormPayload = {
    game_version_id: '3',
    personal_notes: 'notes',
    private_notes: 'secret',
    started_at: '2026-01-01',
    completed_at: '2026-02-01',
    target_list_id: '7',
};

type HttpMethod = keyof typeof http;

const cases: Array<{
    name: string;
    call: () => Promise<unknown>;
    method: HttpMethod;
    routeArgs: unknown[];
    httpArgs: unknown[];
}> = [
    {
        name: 'storeVnList',
        call: () => storeVnList({ name: 'Backlog', is_public: true, game_id: 9 }),
        method: 'post',
        routeArgs: ['api.vn-lists.store'],
        httpArgs: ['/api.vn-lists.store', { name: 'Backlog', is_public: true, game_id: 9 }],
    },
    {
        name: 'updateVnList',
        call: () => updateVnList(5, { name: 'Renamed', description: 'desc', is_public: false }),
        method: 'put',
        routeArgs: ['api.vn-lists.update', 5],
        httpArgs: ['/api.vn-lists.update', { name: 'Renamed', description: 'desc', is_public: false }],
    },
    {
        name: 'destroyVnList',
        call: () => destroyVnList(5),
        method: 'delete',
        routeArgs: ['api.vn-lists.destroy', 5],
        httpArgs: ['/api.vn-lists.destroy'],
    },
    {
        name: 'toggleVnListVisibility',
        call: () => toggleVnListVisibility(5),
        method: 'post',
        routeArgs: ['api.vn-lists.toggle-visibility', 5],
        httpArgs: ['/api.vn-lists.toggle-visibility'],
    },
    {
        name: 'toggleAllListUpdates',
        call: () => toggleAllListUpdates(5, true),
        method: 'patch',
        routeArgs: ['api.vn-lists.toggle-all-updates', 5],
        httpArgs: ['/api.vn-lists.toggle-all-updates', { receive_updates: true }],
    },
    {
        name: 'updateListEntry',
        call: () => updateListEntry(11, entryPayload),
        method: 'put',
        routeArgs: ['api.list-entries.update', 11],
        httpArgs: ['/api.list-entries.update', entryPayload],
    },
    {
        name: 'moveListEntry',
        call: () => moveListEntry(11, '7'),
        method: 'post',
        routeArgs: ['api.list-entries.move', 11],
        httpArgs: ['/api.list-entries.move', { target_list_id: '7' }],
    },
    {
        name: 'destroyListEntry',
        call: () => destroyListEntry(11),
        method: 'delete',
        routeArgs: ['api.list-entries.destroy', 11],
        httpArgs: ['/api.list-entries.destroy'],
    },
    {
        name: 'reorderListEntries',
        call: () => reorderListEntries(5, [3, 1, 2]),
        method: 'post',
        routeArgs: ['api.lists.reorder', 5],
        httpArgs: ['/api.lists.reorder', { entry_ids: [3, 1, 2] }],
    },
    {
        name: 'toggleUserProgressUpdates',
        call: () => toggleUserProgressUpdates(9, false),
        method: 'patch',
        routeArgs: ['api.user-progress.toggle-updates', 9],
        httpArgs: ['/api.user-progress.toggle-updates', { receive_updates: false }],
    },
    {
        name: 'fetchUserLists',
        call: () => fetchUserLists(),
        method: 'get',
        routeArgs: ['browser-api.user.lists'],
        httpArgs: ['/browser-api.user.lists'],
    },
    {
        name: 'fetchGameListMemberships',
        call: () => fetchGameListMemberships(9),
        method: 'get',
        routeArgs: ['browser-api.games.lists', 9],
        httpArgs: ['/browser-api.games.lists'],
    },
    {
        name: 'addGameToDefaultList',
        call: () => addGameToDefaultList(9, 'playing'),
        method: 'post',
        routeArgs: ['api.games.add-to-list', 9],
        httpArgs: ['/api.games.add-to-list', { list_type: 'playing' }],
    },
    {
        name: 'addGameToCustomList',
        call: () => addGameToCustomList(5, 9),
        method: 'post',
        routeArgs: ['api.list-entries.add-to-custom', 5],
        httpArgs: ['/api.list-entries.add-to-custom', { game_id: 9 }],
    },
];

describe('lists API module', () => {
    test.each(cases)('$name hits the route with the expected verb and payload', async ({ call, method, routeArgs, httpArgs }) => {
        http[method].mockResolvedValueOnce({ data: { success: true, message: 'ok', list: { id: 1 } } });

        await call();
        expect(route).toHaveBeenCalledWith(...routeArgs);
        expect(http[method]).toHaveBeenCalledWith(...httpArgs);
    });

    test.each(cases)('$name throws the server message when success is missing', async ({ call, method }) => {
        http[method].mockResolvedValueOnce({ data: { message: 'List not found.' } });

        await expect(call()).rejects.toThrow('List not found.');
    });

    test('guards fall back to a default message', async () => {
        http.delete.mockResolvedValueOnce({ data: { success: false } });

        await expect(destroyVnList(404)).rejects.toThrow('Failed to delete list');
    });

    test('fetchUserLists unwraps the lists array and tolerates an absent field', async () => {
        http.get.mockResolvedValueOnce({ data: { success: true, lists: [{ id: 1, name: 'Backlog', type: 'custom' }] } });
        await expect(fetchUserLists()).resolves.toEqual([{ id: 1, name: 'Backlog', type: 'custom' }]);

        http.get.mockResolvedValueOnce({ data: { success: true } });
        await expect(fetchUserLists()).resolves.toEqual([]);
    });

    test('fetchGameListMemberships unwraps the id list', async () => {
        http.get.mockResolvedValueOnce({ data: { success: true, list_ids: [4, 8] } });

        await expect(fetchGameListMemberships(9)).resolves.toEqual([4, 8]);
    });

    test('add-to-list functions resolve to the server message', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Added to Reading.' } });
        await expect(addGameToDefaultList(9, 'reading')).resolves.toBe('Added to Reading.');

        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Game removed from list.' } });
        await expect(addGameToCustomList(5, 9)).resolves.toBe('Game removed from list.');
    });

    test('storeVnList returns the created list', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Created', list: { id: 7, name: 'New', type: 'custom' } } });

        await expect(storeVnList({ name: 'New', is_public: false })).resolves.toEqual({
            success: true,
            message: 'Created',
            list: { id: 7, name: 'New', type: 'custom' },
        });
    });
});
