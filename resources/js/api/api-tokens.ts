import http from '@/utils/http';

export interface ApiToken {
    id: number;
    name: string;
    abilities: string[];
    created_at: string;
    last_used_at: string | null;
    expires_at: string;
    is_extendable: boolean;
}

export async function listApiTokens(): Promise<{ tokens: ApiToken[]; abilities: string[] }> {
    const { data } = await http.get<{ data: ApiToken[]; abilities: string[] }>(route('browser-api.api-tokens.index'));
    return { tokens: data.data, abilities: data.abilities };
}

export async function createApiToken(name: string, abilities: string[]): Promise<{ token: ApiToken; secret: string }> {
    const { data } = await http.post<{ data: ApiToken; token: string }>(route('browser-api.api-tokens.store'), { name, abilities });
    return { token: data.data, secret: data.token };
}

export async function extendApiToken(token: number): Promise<ApiToken> {
    const { data } = await http.post<{ data: ApiToken }>(route('browser-api.api-tokens.extend', token));
    return data.data;
}

export async function revokeApiToken(token: number): Promise<void> {
    await http.delete(route('browser-api.api-tokens.destroy', token));
}
