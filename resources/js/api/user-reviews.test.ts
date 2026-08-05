import { beforeEach, describe, expect, test, vi } from 'vitest';

const http = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@/utils/http', () => ({ default: http }));

import { deleteUserReview, submitUserReview } from './user-reviews';

const route = vi.fn((name: string) => `/${name}`);

beforeEach(() => {
    vi.clearAllMocks();
    vi.stubGlobal('route', route);
});

describe('user-reviews API', () => {
    test('submits a review and returns the response body', async () => {
        const payload = { rating: 4, review: 'Great pacing', has_spoilers: false };
        const body = { message: 'Saved', review: { id: 1, ...payload, published_at: 'p', updated_at: 'u' } };
        http.post.mockResolvedValueOnce({ data: body });

        await expect(submitUserReview(7, payload)).resolves.toEqual(body);
        expect(route).toHaveBeenCalledWith('browser-api.user-reviews.store', { game: 7 });
        expect(http.post).toHaveBeenCalledWith('/browser-api.user-reviews.store', payload);
    });

    test('deletes a review and unwraps the message string', async () => {
        http.delete.mockResolvedValueOnce({ data: { message: 'Removed' } });

        await expect(deleteUserReview(9)).resolves.toBe('Removed');
        expect(route).toHaveBeenCalledWith('browser-api.user-reviews.destroy', { game: 9 });
        expect(http.delete).toHaveBeenCalledWith('/browser-api.user-reviews.destroy');
    });

    test('propagates transport rejections', async () => {
        const failure = new Error('network down');
        http.post.mockRejectedValueOnce(failure);

        await expect(submitUserReview(7, { rating: 1, review: 'x', has_spoilers: true })).rejects.toBe(failure);
    });
});
