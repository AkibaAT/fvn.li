import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    get: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { fetchGameContentView, revertGameContent, updateGameContent, updateGameName, updateGameViewMode } from './game-content';

const route = vi.fn((name: string) => `/${name}`);

const failure = { success: false as const, message: 'Not allowed' };

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('game-content API', () => {
    test('fetches the content view and unwraps the data envelope', async () => {
        http.get.mockResolvedValueOnce({ data: { success: true, data: { current_view_mode: 'custom' } } });

        await expect(fetchGameContentView(3)).resolves.toEqual({ current_view_mode: 'custom' });
        expect(route).toHaveBeenCalledWith('browser-api.games.content.view', { game: 3 });
        expect(http.get).toHaveBeenCalledWith('/browser-api.games.content.view');
    });

    test('updates the view mode with a put payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true, data: { view_mode: 'original' } } });

        await expect(updateGameViewMode(4, 'original')).resolves.toEqual({ view_mode: 'original' });
        expect(route).toHaveBeenCalledWith('browser-api.games.content.view-mode', { game: 4 });
        expect(http.put).toHaveBeenCalledWith('/browser-api.games.content.view-mode', { view_mode: 'original' });
    });

    test('view-mode updates tolerate an absent data payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true } });

        await expect(updateGameViewMode(4, 'custom')).resolves.toEqual({});
    });

    test('updates content with a put payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true, data: { content: '# Hello' } } });

        await expect(updateGameContent(5, '# Hello')).resolves.toEqual({ content: '# Hello' });
        expect(route).toHaveBeenCalledWith('browser-api.games.content.update', { game: 5 });
        expect(http.put).toHaveBeenCalledWith('/browser-api.games.content.update', { content: '# Hello' });
    });

    test('reverts content with a post payload', async () => {
        const payload = { revert_name: true, revert_screenshots: false, revert_thumbnail: true };
        http.post.mockResolvedValueOnce({ data: { success: true, data: { has_custom_page: false, content: '' } } });

        await expect(revertGameContent(6, payload)).resolves.toEqual({ has_custom_page: false, content: '' });
        expect(route).toHaveBeenCalledWith('browser-api.games.content.revert', { game: 6 });
        expect(http.post).toHaveBeenCalledWith('/browser-api.games.content.revert', payload);
    });

    test('updates the name with a put payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true, data: { name: 'New Name' } } });

        await expect(updateGameName(8, 'New Name')).resolves.toEqual({ name: 'New Name' });
        expect(route).toHaveBeenCalledWith('browser-api.games.name.update', { game: 8 });
        expect(http.put).toHaveBeenCalledWith('/browser-api.games.name.update', { name: 'New Name' });
    });

    test.each([
        ['fetchGameContentView', () => fetchGameContentView(3), http.get],
        ['updateGameViewMode', () => updateGameViewMode(4, 'custom'), http.put],
        ['updateGameContent', () => updateGameContent(5, 'x'), http.put],
        ['revertGameContent', () => revertGameContent(6, { revert_name: false, revert_screenshots: false, revert_thumbnail: false }), http.post],
        ['updateGameName', () => updateGameName(8, 'x'), http.put],
    ])('%s throws the server message on an unsuccessful body', async (_name, call, verb) => {
        verb.mockResolvedValueOnce({ data: failure });

        await expect(call()).rejects.toThrow('Not allowed');
    });

    test('guards fall back to a default message', async () => {
        http.put.mockResolvedValueOnce({ data: { success: false } });

        await expect(updateGameName(8, 'x')).rejects.toThrow('Failed to update name');
    });

    test('propagates transport rejections', async () => {
        const error = new Error('network down');
        http.get.mockRejectedValueOnce(error);

        await expect(fetchGameContentView(3)).rejects.toBe(error);
    });
});
