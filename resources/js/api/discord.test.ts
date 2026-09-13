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

    test('saves each servers config in order and snapshots nested changes before waiting', async () => {
        let finishFirst!: () => void;
        http.put.mockImplementationOnce(() => new Promise<void>((resolve) => (finishFirst = resolve)));
        http.put.mockResolvedValue({ data: {} });
        const first = updateDiscordServerConfig(3, { include_ratings: true });
        await vi.waitFor(() => expect(http.put).toHaveBeenCalledTimes(1));
        const embed = { title: 'Second edit' };
        const second = updateDiscordServerConfig(3, { new_game_embed: embed });
        embed.title = 'Third edit';
        const third = updateDiscordServerConfig(3, { new_game_embed: embed });
        await updateDiscordServerConfig(4, { is_active: false });
        expect(http.put).toHaveBeenCalledTimes(2);
        finishFirst();
        await Promise.all([first, second, third]);
        expect(http.put.mock.calls.map((call) => call[1])).toEqual([
            { include_ratings: true },
            { is_active: false },
            { new_game_embed: { title: 'Second edit' } },
            { new_game_embed: { title: 'Third edit' } },
        ]);
    });

    test('reports a failed config save and continues with queued changes', async () => {
        let failFirst!: (error: Error) => void;
        http.put.mockImplementationOnce(() => new Promise<void>((_, reject) => (failFirst = reject)));
        http.put.mockResolvedValue({ data: {} });
        const first = updateDiscordServerConfig(3, { include_ratings: true });
        const failure = expect(first).rejects.toThrow('Save failed');
        const second = updateDiscordServerConfig(3, { include_ratings: false });
        await vi.waitFor(() => expect(http.put).toHaveBeenCalledTimes(1));
        failFirst(new Error('Save failed'));
        await failure;
        await second;
        expect(http.put).toHaveBeenLastCalledWith('/browser-api.discord.servers.config', { include_ratings: false });
    });

    test('queues override changes independently and continues after failure', async () => {
        let failFirst!: (error: Error) => void;
        http.put.mockImplementationOnce(() => new Promise<void>((_, reject) => (failFirst = reject)));
        http.put.mockResolvedValue({ data: { override: { id: 12 } } });
        const first = updateGameOverride(3, 12, { channel_id: '111' });
        const failure = expect(first).rejects.toThrow('Save failed');
        await vi.waitFor(() => expect(http.put).toHaveBeenCalledTimes(1));
        const change = { channel_id: '222' };
        const second = updateGameOverride(3, 12, change);
        change.channel_id = '333';
        const third = updateGameOverride(3, 12, change);
        await updateGameOverride(3, 13, { is_ignored: true });
        expect(http.put).toHaveBeenCalledTimes(2);
        failFirst(new Error('Save failed'));
        await failure;
        await Promise.all([second, third]);
        expect(http.put.mock.calls.map((call) => call[1])).toEqual([
            { channel_id: '111' },
            { is_ignored: true },
            { channel_id: '222' },
            { channel_id: '333' },
        ]);
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
