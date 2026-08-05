import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

const api = vi.hoisted(() => ({
    submitUserReview: vi.fn(),
    deleteUserReview: vi.fn(),
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

vi.mock('@/api/user-reviews', () => api);

import UserReviewForm from './UserReviewForm.svelte';

describe('UserReviewForm', () => {
    beforeEach(() => {
        api.submitUserReview.mockReset();
        api.deleteUserReview.mockReset();
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

    test('submits and updates a review through the typed API module', async () => {
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
        api.submitUserReview
            .mockResolvedValueOnce({ message: 'Review submitted!', review: submittedReview })
            .mockResolvedValueOnce({ message: 'Review updated!', review: updatedReview });

        const { component } = render(UserReviewForm, { props: { gameId: 42, onReviewChange } });
        component.startEditing();
        await tick();

        await fireEvent.click(screen.getByRole('button', { name: '5 stars' }));
        await fireEvent.input(screen.getByLabelText('Review (optional)'), { target: { value: 'Excellent route.' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

        await waitFor(() => expect(api.submitUserReview).toHaveBeenCalledTimes(1));
        expect(api.submitUserReview).toHaveBeenCalledWith(42, {
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

        await waitFor(() => expect(api.submitUserReview).toHaveBeenCalledTimes(2));
        expect(api.submitUserReview).toHaveBeenLastCalledWith(42, {
            rating: 3,
            review: 'Still good.',
            has_spoilers: false,
        });
    });

    test('deletes a saved review through the typed API module', async () => {
        const onReviewChange = vi.fn();
        api.deleteUserReview.mockResolvedValue('Review deleted.');

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

        await waitFor(() => expect(api.deleteUserReview).toHaveBeenCalledWith(42));
        expect(onReviewChange).toHaveBeenCalledWith(false);
        expect(screen.queryByText('Your Review')).toBeNull();
    });

    test('shows the server error when submission is rejected', async () => {
        api.submitUserReview.mockRejectedValue(new Error('You are not allowed to submit reviews.'));

        const { component } = render(UserReviewForm, { props: { gameId: 42 } });
        component.startEditing();
        await tick();

        await fireEvent.click(screen.getByRole('button', { name: '4 stars' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Submit Review' }));

        expect(await screen.findByText('You are not allowed to submit reviews.')).toBeTruthy();
    });
});
