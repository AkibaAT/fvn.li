import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import {
    createGameOverride,
    deleteGameOverride,
    fetchDiscordGuilds,
    fetchDiscordServer,
    fetchDiscordServerChannels,
    fetchDiscordServerRoles,
    fetchRuleMetadata,
    previewEmbed,
    searchGames,
    sendTestNotification,
    updateDiscordServerConfig,
    updateGameOverride,
} from './discord';

const route = vi.fn((name: string) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('discord API module', () => {
    test('fetches guilds and returns the full response body', async () => {
        const body = { guilds: [{ id: '1', name: 'Den', icon: null, owner: true, has_bot: false, bot_install_url: null }], has_discord: true };
        http.get.mockResolvedValueOnce({ data: body });

        await expect(fetchDiscordGuilds()).resolves.toEqual(body);
        expect(route).toHaveBeenCalledWith('browser-api.discord.guilds');
        expect(http.get).toHaveBeenCalledWith('/browser-api.discord.guilds');
    });

    test('fetches a server and unwraps data.server', async () => {
        const server = { id: 3, discord_server_name: 'Den' };
        http.get.mockResolvedValueOnce({ data: { server } });

        await expect(fetchDiscordServer(3)).resolves.toEqual(server);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.show', { server: 3 });
        expect(http.get).toHaveBeenCalledWith('/browser-api.discord.servers.show');
    });

    test('fetches server channels and unwraps data.channels', async () => {
        const channels = [{ id: '10', name: 'general' }];
        http.get.mockResolvedValueOnce({ data: { channels } });

        await expect(fetchDiscordServerChannels(3)).resolves.toEqual(channels);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.channels', { server: 3 });
    });

    test('fetches server roles and unwraps data.roles', async () => {
        const roles = [{ id: '20', name: 'mods' }];
        http.get.mockResolvedValueOnce({ data: { roles } });

        await expect(fetchDiscordServerRoles(3)).resolves.toEqual(roles);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.roles', { server: 3 });
    });

    test('fetches rule metadata and unwraps data.fields', async () => {
        const fields = { platform: { type: 'enum', operators: ['is'], options: [{ value: 'win', label: 'Windows' }] } };
        http.get.mockResolvedValueOnce({ data: { fields } });

        await expect(fetchRuleMetadata()).resolves.toEqual(fields);
        expect(route).toHaveBeenCalledWith('browser-api.discord.rule-metadata');
    });

    test('updates server config via PUT with the payload', async () => {
        http.put.mockResolvedValueOnce({ data: {} });
        const payload = { notification_channel_id: '55', is_active: false };

        await expect(updateDiscordServerConfig(3, payload)).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.config', { server: 3 });
        expect(http.put).toHaveBeenCalledWith('/browser-api.discord.servers.config', payload);
    });

    test('sends a test notification with an empty body and returns the message', async () => {
        http.post.mockResolvedValueOnce({ data: { message: 'Sent to #general' } });

        await expect(sendTestNotification(3)).resolves.toBe('Sent to #general');
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.test-notification', { server: 3 });
        expect(http.post).toHaveBeenCalledWith('/browser-api.discord.servers.test-notification', {});
    });

    test('previews an embed and unwraps data.embed', async () => {
        const embed = { title: 'New game' };
        http.post.mockResolvedValueOnce({ data: { embed } });

        await expect(previewEmbed(3, { title: '{{name}}' }, 'new_game')).resolves.toEqual(embed);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.preview-embed', { server: 3 });
        expect(http.post).toHaveBeenCalledWith('/browser-api.discord.servers.preview-embed', {
            embed_template: { title: '{{name}}' },
            notification_type: 'new_game',
        });
    });

    test('searchGames returns a plain array response as-is', async () => {
        const games = [{ id: 1, name: 'Den', slug: 'den' }];
        http.get.mockResolvedValueOnce({ data: games });

        await expect(searchGames('den', 5)).resolves.toEqual(games);
        expect(route).toHaveBeenCalledWith('api.games.search');
        expect(http.get).toHaveBeenCalledWith('/api.games.search', { params: { q: 'den', limit: 5 } });
    });

    test('searchGames unwraps a { games } response and defaults limit to 10', async () => {
        const games = [{ id: 2, name: 'Fox', slug: 'fox' }];
        http.get.mockResolvedValueOnce({ data: { games } });

        await expect(searchGames('fox')).resolves.toEqual(games);
        expect(http.get).toHaveBeenCalledWith('/api.games.search', { params: { q: 'fox', limit: 10 } });
    });

    test('searchGames returns an empty list when the object response has no games', async () => {
        http.get.mockResolvedValueOnce({ data: {} });

        await expect(searchGames('none')).resolves.toEqual([]);
    });

    test('creates a game override and unwraps data.override', async () => {
        const override = { id: 7, game_id: 4, is_ignored: true };
        http.post.mockResolvedValueOnce({ data: { override } });
        const payload = { game_id: 4, is_ignored: true, channel_id: null };

        await expect(createGameOverride(3, payload)).resolves.toEqual(override);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.overrides.store', { server: 3 });
        expect(http.post).toHaveBeenCalledWith('/browser-api.discord.servers.overrides.store', payload);
    });

    test('updates a game override via PUT and unwraps data.override', async () => {
        const override = { id: 7, game_id: 4, is_ignored: false };
        http.put.mockResolvedValueOnce({ data: { override } });

        await expect(updateGameOverride(3, 7, { is_ignored: false })).resolves.toEqual(override);
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.overrides.update', { server: 3, override: 7 });
        expect(http.put).toHaveBeenCalledWith('/browser-api.discord.servers.overrides.update', { is_ignored: false });
    });

    test('deletes a game override', async () => {
        http.delete.mockResolvedValueOnce({ data: {} });

        await expect(deleteGameOverride(3, 7)).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('browser-api.discord.servers.overrides.delete', { server: 3, override: 7 });
        expect(http.delete).toHaveBeenCalledWith('/browser-api.discord.servers.overrides.delete');
    });
});
