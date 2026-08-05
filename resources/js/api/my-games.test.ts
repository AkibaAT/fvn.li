import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { deleteMyGameScreenshot, syncItchioGames, updateMyGameLinks, uploadMyGameScreenshots } from './my-games';

const route = vi.fn((name: string) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('updateMyGameLinks', () => {
    test('puts links and timezone offset to the game route', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true } });

        const links = [{ name: 'Steam', url: 'https://store.steampowered.com/app/1' }];
        await expect(updateMyGameLinks('my-vn', links, -120)).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('browser-api.my-games.update', { game: 'my-vn' });
        expect(http.put).toHaveBeenCalledWith('/browser-api.my-games.update', { links, timezone_offset: -120 });
    });

    test('throws the server message, falling back to a default', async () => {
        http.put.mockResolvedValueOnce({ data: { success: false, message: 'Nope' } });
        await expect(updateMyGameLinks('my-vn', [], 0)).rejects.toThrow('Nope');

        http.put.mockResolvedValueOnce({ data: { success: false } });
        await expect(updateMyGameLinks('my-vn', [], 0)).rejects.toThrow('Failed to save changes');
    });

    test('propagates transport rejections', async () => {
        http.put.mockRejectedValueOnce(new Error('network down'));
        await expect(updateMyGameLinks('my-vn', [], 0)).rejects.toThrow('network down');
    });
});

describe('uploadMyGameScreenshots', () => {
    test('posts the files as screenshots[] form data and returns the response', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, screenshots: ['a.png'], new_screenshots: ['b.png'] } });

        const files = [new File(['a'], 'a.png', { type: 'image/png' }), new File(['b'], 'b.png', { type: 'image/png' })];
        await expect(uploadMyGameScreenshots('my-vn', files)).resolves.toEqual({
            success: true,
            screenshots: ['a.png'],
            new_screenshots: ['b.png'],
        });
        expect(route).toHaveBeenCalledWith('browser-api.my-games.screenshots.upload', { game: 'my-vn' });

        const [url, body] = http.post.mock.calls[0] as [string, FormData];
        expect(url).toBe('/browser-api.my-games.screenshots.upload');
        expect(body).toBeInstanceOf(FormData);
        expect(body.getAll('screenshots[]')).toEqual(files);
    });

    test('throws the server message, falling back to a default', async () => {
        http.post.mockResolvedValueOnce({ data: { success: false, message: 'Too large' } });
        await expect(uploadMyGameScreenshots('my-vn', [])).rejects.toThrow('Too large');

        http.post.mockResolvedValueOnce({ data: { success: false } });
        await expect(uploadMyGameScreenshots('my-vn', [])).rejects.toThrow('Failed to upload screenshots');
    });
});

describe('deleteMyGameScreenshot', () => {
    test('sends the index as the delete request body', async () => {
        http.delete.mockResolvedValueOnce({ data: { success: true, screenshots: ['a.png'] } });

        await expect(deleteMyGameScreenshot('my-vn', 2)).resolves.toEqual({ success: true, screenshots: ['a.png'] });
        expect(route).toHaveBeenCalledWith('browser-api.my-games.screenshots.delete', { game: 'my-vn' });
        expect(http.delete).toHaveBeenCalledWith('/browser-api.my-games.screenshots.delete', { data: { index: 2 } });
    });

    test('throws the server message, falling back to a default', async () => {
        http.delete.mockResolvedValueOnce({ data: { success: false, message: 'Missing' } });
        await expect(deleteMyGameScreenshot('my-vn', 0)).rejects.toThrow('Missing');

        http.delete.mockResolvedValueOnce({ data: { success: false } });
        await expect(deleteMyGameScreenshot('my-vn', 0)).rejects.toThrow('Failed to delete screenshot');
    });
});

describe('syncItchioGames', () => {
    test('posts an empty body and returns the server message', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Synced 3 games' } });

        await expect(syncItchioGames()).resolves.toBe('Synced 3 games');
        expect(route).toHaveBeenCalledWith('user.itchio-games.sync');
        expect(http.post).toHaveBeenCalledWith('/user.itchio-games.sync', {});
    });

    test('throws the server message, falling back to a default', async () => {
        http.post.mockResolvedValueOnce({ data: { success: false, message: 'Token expired' } });
        await expect(syncItchioGames()).rejects.toThrow('Token expired');

        http.post.mockResolvedValueOnce({ data: { success: false, message: '' } });
        await expect(syncItchioGames()).rejects.toThrow('Could not sync your itch.io games.');
    });
});
