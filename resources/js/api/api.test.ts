import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { submitBugReport } from './bug-reports';
import { fetchAdditionRequests } from './dashboard';
import { fetchRandomGameSlug, toggleIgnoredGame } from './games';
import { submitReviewReport } from './reviews';

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal(
        'route',
        vi.fn((name: string) => `/${name}`),
    );
});

describe('typed API modules', () => {
    test('submits bug reports through the shared transport', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Saved' } });

        await expect(
            submitBugReport({ page_url: '/games', page_title: 'Games', description: 'Detailed report', request_parameters: {} }),
        ).resolves.toBe('Saved');
        expect(http.post).toHaveBeenCalledWith('/browser-api.bug-reports.store', {
            page_url: '/games',
            page_title: 'Games',
            description: 'Detailed report',
            request_parameters: {},
        });
    });

    test('passes dashboard filters as typed request params', async () => {
        http.get.mockResolvedValueOnce({ data: { success: true, requests: [{ id: 1 }] } });

        await expect(fetchAdditionRequests({ status: 'pending', search: 'itch.io' })).resolves.toEqual([{ id: 1 }]);
        expect(http.get).toHaveBeenCalledWith('/browser-api.dashboard.addition-requests.index', {
            params: { status: 'pending', search: 'itch.io' },
        });
    });

    test('normalizes ignore-toggle response fields', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, is_ignored: true, ignored_game_ids: [4, 8] } });

        await expect(toggleIgnoredGame(8)).resolves.toEqual({ isIgnored: true, ignoredGameIds: [4, 8] });
    });

    test('returns null when the random-game endpoint has no slug', async () => {
        http.get.mockResolvedValueOnce({ data: {} });

        await expect(fetchRandomGameSlug()).resolves.toBeNull();
    });

    test('submits review reports through the typed endpoint', async () => {
        http.post.mockResolvedValueOnce({ data: { success: true, message: 'Reported' } });

        await expect(submitReviewReport(12, 'spam', 'Repeated advertising')).resolves.toBe('Reported');
        expect(http.post).toHaveBeenCalledWith('/browser-api.review-reports.store', {
            reason: 'spam',
            details: 'Repeated advertising',
        });
    });
});
