import http from '@/utils/http';

export interface PrivateTag {
    id: number;
    name: string;
    games_count: number;
}

export interface PrivateTagGame {
    id: number;
    name: string;
    slug: string;
}

export async function listPrivateTags(): Promise<PrivateTag[]> {
    const { data } = await http.get<{ data: PrivateTag[] }>(route('browser-api.private-tags.index'));
    return data.data;
}

export async function createPrivateTag(name: string): Promise<PrivateTag> {
    const { data } = await http.post<{ data: PrivateTag }>(route('browser-api.private-tags.store'), { name });
    return data.data;
}

export async function renamePrivateTag(tag: number, name: string): Promise<void> {
    await http.patch(route('browser-api.private-tags.update', tag), { name });
}

export async function deletePrivateTag(tag: number): Promise<void> {
    await http.delete(route('browser-api.private-tags.destroy', tag));
}

export async function privateTagGames(tag: number, pageUrl?: string): Promise<{ games: PrivateTagGame[]; next: string | null }> {
    const { data } = await http.get<{ data: PrivateTagGame[]; links: { next: string | null } }>(
        pageUrl ?? route('browser-api.private-tags.games', tag),
    );
    return { games: data.data, next: data.links.next };
}

export async function gamePrivateTags(game: number): Promise<PrivateTag[]> {
    const { data } = await http.get<{ data: PrivateTag[] }>(route('browser-api.games.private-tags.index', game));
    return data.data;
}

export async function setGamePrivateTag(game: number, tag: number, attached: boolean): Promise<void> {
    const url = route(attached ? 'browser-api.games.private-tags.attach' : 'browser-api.games.private-tags.detach', { game, tag });
    if (attached) await http.put(url);
    else await http.delete(url);
}
