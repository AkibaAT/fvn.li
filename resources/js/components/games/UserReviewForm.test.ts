import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const httpMock = vi.hoisted(() => ({
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/svelte', () => ({
    page: {
        props: {
            auth: {
                user: { id: 1 },
            },
        },
    },
}));

vi.mock('@/utils/http', () => ({
    default: httpMock,
}));

import UserReviewForm from './UserReviewForm.svelte';

describe('UserReviewForm', () => {
    beforeEach(() => {
        httpMock.post.mockReset();
        httpMock.delete.mockReset();
        vi.stubGlobal(
            'route',
            vi.fn((name: string, params: { game: number }) => `/${name}/${params.game}`),
        );
    });

    test('exposes form editing without rendering its own prompt', async () => {
        const { component, container } = render(UserReviewForm, {
            props: {
                gameId: 42,
            },
        });

        expect(screen.queryByRole('button', { name: 'Write a review' })).toBeNull();

        component.startEditing();
        await tick();

        expect(screen.getByRole('heading', { name: 'Write a Review' })).toBeTruthy();
        expect(container.querySelector('form')).toBeTruthy();
    });

    test('submits and updates a review through the shared HTTP client', async () => {
        const onReviewChange = vi.fn();
        const submittedReview = {
            id: 7,
            rating: 5,
            review: 'Excellent route.',
            has_spoilers: false,
            published_at: '2026-08-04T12:00:00Z',
            updated_at: '2026-08-04T12:00:00Z',
        };
        const updatedReview = { ...submittedReview, rating: 3, review: 'Still good.' };
        httpMock.post
            .mockResolvedValueOnce({ data: { message: 'Review submitted!', review: submittedReview } })
            .mockResolvedValueOnce({ data: { message: 'Review updated!', review: updatedReview } });

        const { component } = render(UserReviewForm, { props: { gameId: 42, onReviewChange } });
        component.startEditing();
        await tick();

        await fireEvent.click(screen.getByRole('button', { name: '5 stars' }));
        await fireEvent.input(screen.getByLabelText('Review (optional)'), { target: { value: 'Excellent route.' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

        await waitFor(() => expect(httpMock.post).toHaveBeenCalledTimes(1));
        expect(httpMock.post).toHaveBeenCalledWith('/browser-api.user-reviews.store/42', {
            rating: 5,
            review: 'Excellent route.',
            has_spoilers: false,
        });
        expect(onReviewChange).toHaveBeenCalledWith(true);
        expect(screen.getByText('Your Review')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Edit' }));
        await fireEvent.click(screen.getByRole('button', { name: '3 stars' }));
        await fireEvent.input(screen.getByLabelText('Review (optional)'), { target: { value: 'Still good.' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Update Review' }));

        await waitFor(() => expect(httpMock.post).toHaveBeenCalledTimes(2));
        expect(httpMock.post).toHaveBeenLastCalledWith('/browser-api.user-reviews.store/42', {
            rating: 3,
            review: 'Still good.',
            has_spoilers: false,
        });
    });

    test('deletes a saved review through the shared HTTP client', async () => {
        const onReviewChange = vi.fn();
        httpMock.delete.mockResolvedValue({ data: { message: 'Review deleted.' } });

        render(UserReviewForm, {
            props: {
                gameId: 42,
                onReviewChange,
                initialReview: {
                    id: 7,
                    rating: 4,
                    review: 'Good game.',
                    has_spoilers: false,
                    published_at: '2026-08-04T12:00:00Z',
                    updated_at: '2026-08-04T12:00:00Z',
                },
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

        await waitFor(() => expect(httpMock.delete).toHaveBeenCalledWith('/browser-api.user-reviews.destroy/42'));
        expect(onReviewChange).toHaveBeenCalledWith(false);
        expect(screen.queryByText('Your Review')).toBeNull();
    });

    test('shows the server error when submission is rejected', async () => {
        httpMock.post.mockRejectedValue({ response: { data: { message: 'You are not allowed to submit reviews.' } } });

        const { component } = render(UserReviewForm, { props: { gameId: 42 } });
        component.startEditing();
        await tick();

        await fireEvent.click(screen.getByRole('button', { name: '4 stars' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

        expect(await screen.findByText('You are not allowed to submit reviews.')).toBeTruthy();
    });
});
