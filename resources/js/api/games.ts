import http from '@/utils/http';

export interface RatingHistoryEntry {
    id: number;
    rating: number;
    published_at: string | null;
    is_reviewed: boolean;
    review?: string | null;
    event_id?: number | null;
    is_visible: boolean;
    game: { id: number; name: string; slug: string; primary_url?: string | null; platform?: string; is_visible?: boolean };
}

export async function toggleIgnoredGame(gameId: number): Promise<{ isIgnored: boolean; ignoredGameIds: number[] }> {
    const { data } = await http.post<{ success: boolean; is_ignored: boolean; ignored_game_ids: number[]; message?: string }>(
        route('user.ignored-games.toggle'),
        { game_id: gameId },
    );
    if (!data.success) throw new Error(data.message || 'Failed to toggle ignore status.');
    return { isIgnored: data.is_ignored, ignoredGameIds: data.ignored_game_ids };
}

export async function fetchRandomGameSlug(): Promise<string | null> {
    const { data } = await http.get<{ slug?: string }>(route('games.random'));
    return data.slug || null;
}

export async function fetchRaterGameHistory(raterId: number, gameId: number): Promise<RatingHistoryEntry[]> {
    const { data } = await http.get<{ ratings?: RatingHistoryEntry[] }>(route('raters.games.history', { rater: raterId, game: gameId }), {
        headers: { Accept: 'application/json' },
    });
    return data.ratings ?? [];
}

export async function uploadEditorImage(gameId: number | string, blob: Blob, filename: string): Promise<string> {
    const formData = new FormData();
    formData.append('file', blob, filename);
    formData.append('game_id', String(gameId));

    const { data } = await http.post<{ location?: string; error?: string }>(`/browser-api/upload-editor-image?t=${Date.now()}`, formData);
    if (!data.location) throw new Error(data.error || 'Upload failed - no location returned');
    return data.location;
}
