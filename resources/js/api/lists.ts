import http from '@/utils/http';

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
    if (!data.success) throw new Error(data.message || 'Failed to create list');
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
    if (!data.success) throw new Error(data.message || 'Failed to update list');
    return data;
}

export async function destroyVnList(listId: number): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('api.vn-lists.destroy', listId));
    if (!data.success) throw new Error(data.message || 'Failed to delete list');
}

export async function toggleVnListVisibility(listId: number): Promise<{ is_public: boolean; message?: string }> {
    const { data } = await http.post<{ success: boolean; message?: string; is_public: boolean }>(route('api.vn-lists.toggle-visibility', listId));
    if (!data.success) throw new Error(data.message || 'Failed to update list visibility');
    return data;
}

export async function toggleAllListUpdates(
    listId: number,
    receiveUpdates: boolean,
): Promise<{ message?: string; receive_updates: boolean; updated_game_ids?: number[] }> {
    const { data } = await http.patch<{ success: boolean; message?: string; receive_updates: boolean; updated_game_ids?: number[] }>(
        route('api.vn-lists.toggle-all-updates', listId),
        { receive_updates: receiveUpdates },
    );
    if (!data.success) throw new Error(data.message || 'Failed to update notifications');
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
    if (!data.success) throw new Error(data.message || 'Failed to update entry');
    return data;
}

export async function moveListEntry(entryId: number, targetListId: string): Promise<void> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('api.list-entries.move', entryId), {
        target_list_id: targetListId,
    });
    if (!data.success) throw new Error(data.message || 'Failed to move entry');
}

export async function destroyListEntry(entryId: number): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('api.list-entries.destroy', entryId));
    if (!data.success) throw new Error(data.message || 'Failed to remove entry');
}

export async function reorderListEntries(listId: number, entryIds: number[]): Promise<{ message?: string }> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('api.lists.reorder', listId), { entry_ids: entryIds });
    if (!data.success) throw new Error(data.message || 'Failed to reorder entries');
    return data;
}

export async function toggleUserProgressUpdates(gameId: number, receiveUpdates: boolean): Promise<{ message?: string; receive_updates: boolean }> {
    const { data } = await http.patch<{ success: boolean; message?: string; receive_updates: boolean }>(
        route('api.user-progress.toggle-updates', gameId),
        { receive_updates: receiveUpdates },
    );
    if (!data.success) throw new Error(data.message || 'Failed to toggle notifications');
    return data;
}

export async function fetchUserLists(): Promise<VnListSummary[]> {
    const { data } = await http.get<{ success: boolean; message?: string; lists?: VnListSummary[] }>(route('browser-api.user.lists'));
    if (!data.success) throw new Error(data.message || 'Failed to load lists');
    return data.lists ?? [];
}

export async function fetchGameListMemberships(gameId: number): Promise<number[]> {
    const { data } = await http.get<{ success: boolean; message?: string; list_ids?: number[] }>(route('browser-api.games.lists', gameId));
    if (!data.success) throw new Error(data.message || 'Failed to load list memberships');
    return data.list_ids ?? [];
}

export async function addGameToDefaultList(gameId: number, listType: string): Promise<string> {
    const { data } = await http.post<{ success: boolean; message: string }>(route('api.games.add-to-list', gameId), { list_type: listType });
    if (!data.success) throw new Error(data.message || 'Failed to update list');
    return data.message;
}

export async function addGameToCustomList(listId: number, gameId: number): Promise<string> {
    const { data } = await http.post<{ success: boolean; message: string }>(route('api.list-entries.add-to-custom', listId), {
        game_id: gameId,
    });
    if (!data.success) throw new Error(data.message || 'Failed to update list');
    return data.message;
}
