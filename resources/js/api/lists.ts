import http from '@/utils/http';

function assertSuccess(data: { success: boolean; message?: string }, fallback: string): void {
    if (!data.success) throw new Error(data.message || fallback);
}

export interface VnListSummary {
    id: number;
    name: string;
    type: string;
    is_default?: boolean;
    is_public?: boolean;
    user?: { id: number; name: string };
}

export interface ListEntryFormPayload {
    game_version_id: string;
    personal_notes: string;
    private_notes: string;
    started_at: string;
    completed_at: string;
    target_list_id: string;
}

export async function storeVnList(payload: { name: string; description?: string; is_public: boolean; game_id?: number }): Promise<{
    message: string;
    list: VnListSummary;
}> {
    const { data } = await http.post<{ success: boolean; message: string; list: VnListSummary }>(route('api.vn-lists.store'), payload);
    assertSuccess(data, 'Failed to create list');
    return data;
}

export async function updateVnList(
    listId: number,
    payload: { name: string; description: string; is_public: boolean },
): Promise<{ message?: string; vnList?: { name: string; description?: string; is_public: boolean } }> {
    const { data } = await http.put<{ success: boolean; message?: string; vnList?: { name: string; description?: string; is_public: boolean } }>(
        route('api.vn-lists.update', listId),
        payload,
    );
    assertSuccess(data, 'Failed to update list');
    return data;
}

export async function destroyVnList(listId: number): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('api.vn-lists.destroy', listId));
    assertSuccess(data, 'Failed to delete list');
}

export async function toggleVnListVisibility(listId: number): Promise<{ is_public: boolean; message?: string }> {
    const { data } = await http.post<{ success: boolean; message?: string; is_public: boolean }>(route('api.vn-lists.toggle-visibility', listId));
    assertSuccess(data, 'Failed to update list visibility');
    return data;
}

export async function toggleAllListUpdates(
    listId: number,
    receiveUpdates: boolean,
): Promise<{ message?: string; is_receiving_updates: boolean; updated_game_ids?: number[] }> {
    const { data } = await http.patch<{ success: boolean; message?: string; is_receiving_updates: boolean; updated_game_ids?: number[] }>(
        route('api.vn-lists.toggle-all-updates', listId),
        { is_receiving_updates: receiveUpdates },
    );
    assertSuccess(data, 'Failed to update notifications');
    return data;
}

export async function updateListEntry<TEntry = unknown, TProgress = unknown>(
    entryId: number,
    payload: ListEntryFormPayload,
): Promise<{ entry?: TEntry; progress?: TProgress }> {
    const { data } = await http.put<{ success: boolean; message?: string; entry?: TEntry; progress?: TProgress }>(
        route('api.list-entries.update', entryId),
        payload,
    );
    assertSuccess(data, 'Failed to update entry');
    return data;
}

export async function moveListEntry(entryId: number, targetListId: string): Promise<void> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('api.list-entries.move', entryId), {
        target_list_id: targetListId,
    });
    assertSuccess(data, 'Failed to move entry');
}

export async function destroyListEntry(entryId: number): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('api.list-entries.destroy', entryId));
    assertSuccess(data, 'Failed to remove entry');
}

export async function reorderListEntries(listId: number, entryIds: number[]): Promise<{ message?: string }> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('api.lists.reorder', listId), { entry_ids: entryIds });
    assertSuccess(data, 'Failed to reorder entries');
    return data;
}

export async function toggleUserProgressUpdates(
    gameId: number,
    receiveUpdates: boolean,
): Promise<{ message?: string; is_receiving_updates: boolean }> {
    const { data } = await http.patch<{ success: boolean; message?: string; is_receiving_updates: boolean }>(
        route('api.user-progress.toggle-updates', gameId),
        { is_receiving_updates: receiveUpdates },
    );
    assertSuccess(data, 'Failed to toggle notifications');
    return data;
}

export async function fetchUserLists(): Promise<VnListSummary[]> {
    const { data } = await http.get<{ success: boolean; message?: string; lists?: VnListSummary[] }>(route('browser-api.user.lists'));
    assertSuccess(data, 'Failed to load lists');
    return data.lists ?? [];
}

export async function fetchGameListMemberships(gameId: number): Promise<number[]> {
    const { data } = await http.get<{ success: boolean; message?: string; list_ids?: number[] }>(route('browser-api.games.lists', gameId));
    assertSuccess(data, 'Failed to load list memberships');
    return data.list_ids ?? [];
}

export async function addGameToDefaultList(gameId: number, listType: string): Promise<string> {
    const { data } = await http.post<{ success: boolean; message: string }>(route('api.games.add-to-list', gameId), { list_type: listType });
    assertSuccess(data, 'Failed to update list');
    return data.message;
}

export async function addGameToCustomList(listId: number, gameId: number): Promise<string> {
    const { data } = await http.post<{ success: boolean; message: string }>(route('api.list-entries.add-to-custom', listId), {
        game_id: gameId,
    });
    assertSuccess(data, 'Failed to update list');
    return data.message;
}
