import http from '@/utils/http';

export type GameViewMode = 'custom' | 'original';

interface Envelope<T> {
    success: boolean;
    message?: string;
    data: T;
}

function unwrap<T>(body: Envelope<T>, fallback: string): T {
    if (!body.success) throw new Error(body.message || fallback);
    return body.data;
}

export interface GameViewModeData {
    view_mode?: GameViewMode;
    effective_name?: string | null;
    effective_description?: string | null;
    effective_screenshots?: unknown[];
}

export interface RevertGameContentPayload {
    revert_name: boolean;
    revert_screenshots: boolean;
    revert_thumbnail: boolean;
}

export interface RevertGameContentData {
    has_custom_page: boolean;
    content: string;
    name?: string | null;
    effective_name?: string | null;
    screenshots?: unknown[] | null;
    thumbnail_url?: string | null;
}

export async function fetchGameContentView(gameId: number): Promise<{ current_view_mode?: GameViewMode | null }> {
    const { data } = await http.get<Envelope<{ current_view_mode?: GameViewMode | null }>>(
        route('browser-api.games.content.view', { game: gameId }),
    );
    return unwrap(data, 'Failed to load view mode');
}

export async function updateGameViewMode(gameId: number, viewMode: GameViewMode): Promise<GameViewModeData> {
    const { data } = await http.put<Envelope<GameViewModeData | undefined>>(route('browser-api.games.content.view-mode', { game: gameId }), {
        view_mode: viewMode,
    });
    return unwrap(data, 'Failed to update view mode') ?? {};
}

export async function updateGameContent(gameId: number, content: string): Promise<{ content?: string }> {
    const { data } = await http.put<Envelope<{ content?: string } | undefined>>(route('browser-api.games.content.update', { game: gameId }), {
        content,
    });
    return unwrap(data, 'Failed to save') ?? {};
}

export async function revertGameContent(gameId: number, payload: RevertGameContentPayload): Promise<RevertGameContentData> {
    const { data } = await http.post<Envelope<RevertGameContentData>>(route('browser-api.games.content.revert', { game: gameId }), payload);
    return unwrap(data, 'Failed to revert content');
}

export async function updateGameName(gameId: number, name: string): Promise<{ name?: string }> {
    const { data } = await http.put<Envelope<{ name?: string }>>(route('browser-api.games.name.update', { game: gameId }), { name });
    return unwrap(data, 'Failed to update name');
}
