import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { unignoreGame, updateExcludedTags, updateLanguagePreferences, updateNotificationPreferences } from './user-preferences';

const route = vi.fn((name: string) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('unignoreGame', () => {
    test('sends the game id as the delete request body', async () => {
        http.delete.mockResolvedValueOnce({ data: { success: true } });

        await expect(unignoreGame(42)).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('user.ignored-games.destroy');
        expect(http.delete).toHaveBeenCalledWith('/user.ignored-games.destroy', { data: { game_id: 42 } });
    });

    test('throws the server message, falling back to a default', async () => {
        http.delete.mockResolvedValueOnce({ data: { success: false, message: 'Not ignored' } });
        await expect(unignoreGame(42)).rejects.toThrow('Not ignored');

        http.delete.mockResolvedValueOnce({ data: { success: false } });
        await expect(unignoreGame(42)).rejects.toThrow('Failed to remove game from ignore list');
    });
});

describe('updateLanguagePreferences', () => {
    test('puts the preferred languages payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true } });

        await expect(updateLanguagePreferences(['en', 'ja'])).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('user.language-preferences.update');
        expect(http.put).toHaveBeenCalledWith('/user.language-preferences.update', { preferred_languages: ['en', 'ja'] });
    });

    test('throws the server message, falling back to a default', async () => {
        http.put.mockResolvedValueOnce({ data: { success: false, message: 'Invalid language' } });
        await expect(updateLanguagePreferences(['xx'])).rejects.toThrow('Invalid language');

        http.put.mockResolvedValueOnce({ data: { success: false } });
        await expect(updateLanguagePreferences([])).rejects.toThrow('Failed to save language preferences');
    });
});

describe('updateExcludedTags', () => {
    test('puts the excluded tag ids payload', async () => {
        http.put.mockResolvedValueOnce({ data: { success: true } });

        await expect(updateExcludedTags([3, 7])).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('user.excluded-tags.update');
        expect(http.put).toHaveBeenCalledWith('/user.excluded-tags.update', { excluded_tags: [3, 7] });
    });

    test('throws the server message, falling back to a default', async () => {
        http.put.mockResolvedValueOnce({ data: { success: false, message: 'Unknown tag' } });
        await expect(updateExcludedTags([999])).rejects.toThrow('Unknown tag');

        http.put.mockResolvedValueOnce({ data: { success: false } });
        await expect(updateExcludedTags([])).rejects.toThrow('Failed to save excluded tags');
    });
});

describe('updateNotificationPreferences', () => {
    const preferences = {
        browser_notifications_enabled: true,
        discord_notifications_enabled: false,
        notification_digest: 'daily',
    };

    test('posts the preferences object as the request body', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true } });

        await expect(updateNotificationPreferences(preferences)).resolves.toBeUndefined();
        expect(route).toHaveBeenCalledWith('browser-api.dashboard.notifications.update');
        expect(http.post).toHaveBeenCalledWith('/browser-api.dashboard.notifications.update', preferences);
    });

    test('throws when success is absent from the response', async () => {
        http.post.mockResolvedValueOnce({ data: {} });
        await expect(updateNotificationPreferences(preferences)).rejects.toThrow('Request failed');
    });

    test('throws the server message, falling back to a default', async () => {
        http.post.mockResolvedValueOnce({ data: { success: false, message: 'Digest invalid' } });
        await expect(updateNotificationPreferences(preferences)).rejects.toThrow('Digest invalid');

        http.post.mockResolvedValueOnce({ data: { success: false } });
        await expect(updateNotificationPreferences(preferences)).rejects.toThrow('Request failed');
    });
});
