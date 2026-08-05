import http from '@/utils/http';

export interface MyGameLink {
    id?: string;
    name: string;
    url: string;
    platform?: string | null;
    sort_order?: number;
    last_edited_at?: string;
    release_at?: string | null;
}

export async function updateMyGameLinks(gameSlug: string, links: MyGameLink[], timezoneOffset: number): Promise<void> {
    const { data } = await http.put<{ success?: boolean; message?: string }>(route('browser-api.my-games.update', { game: gameSlug }), {
        links,
        timezone_offset: timezoneOffset,
    });
    if (!data.success) throw new Error(data.message || 'Failed to save changes');
}

export async function uploadMyGameScreenshots(
    gameSlug: string | undefined,
    files: File[],
): Promise<{ screenshots?: unknown; new_screenshots?: unknown }> {
    const formData = new FormData();
    files.forEach((file) => formData.append('screenshots[]', file));

    const { data } = await http.post<{ success?: boolean; message?: string; screenshots?: unknown; new_screenshots?: unknown }>(
        route('browser-api.my-games.screenshots.upload', { game: gameSlug }),
        formData,
    );
    if (!data.success) throw new Error(data.message || 'Failed to upload screenshots');
    return data;
}

export async function deleteMyGameScreenshot(gameSlug: string | undefined, index: number): Promise<{ screenshots?: unknown }> {
    const { data } = await http.delete<{ success?: boolean; message?: string; screenshots?: unknown }>(
        route('browser-api.my-games.screenshots.delete', { game: gameSlug }),
        { data: { index } },
    );
    if (!data.success) throw new Error(data.message || 'Failed to delete screenshot');
    return data;
}

export async function syncItchioGames(): Promise<string> {
    const { data } = await http.post<{ success?: boolean; message: string }>(route('user.itchio-games.sync'), {});
    if (!data.success) throw new Error(data.message || 'Could not sync your itch.io games.');
    return data.message;
}
