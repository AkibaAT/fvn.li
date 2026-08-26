import { fireEvent, render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import RatingRow from './RatingRow.svelte';

describe('RatingRow', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'route',
            vi.fn(() => '/games/example'),
        );
    });

    test('keeps an FVN.li spoiler hidden until it is revealed', async () => {
        const { container } = render(RatingRow, {
            props: {
                row: {
                    id: 1,
                    score: 5,
                    review: '<p>Secret route.</p>',
                    game: { id: 1, name: 'Example', slug: 'example' },
                    isFvnReview: true,
                    hasSpoilers: true,
                },
                reviewStyle: 'max-width: 75%;',
            },
        });

        expect(screen.queryByText('Secret route.')).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Contains spoilers. Click to reveal.' }));

        expect(screen.getByText('Secret route.')).toBeTruthy();
        expect(container.querySelector('.fvn-review')?.getAttribute('style')).toContain('max-width: 75%');
    });

    test('links a site reviewer to their reviews and shows the FVN.li badge', () => {
        render(RatingRow, {
            props: {
                row: {
                    id: 2,
                    score: 4,
                    review: '<p>Site take.</p>',
                    game: { id: 1, name: 'Example', slug: 'example' },
                    user: { id: 9, name: 'Site Reviewer' },
                    isFvnReview: true,
                },
                reviewStyle: '',
                showRater: true,
            },
        });

        expect(screen.getByText('Site Reviewer')).toBeTruthy();
        expect(screen.getByText('FVN.li')).toBeTruthy();
        expect(route).toHaveBeenCalledWith('users.reviews', 9);
    });

    test('links a linked itch rater to the site user without the FVN.li badge', () => {
        render(RatingRow, {
            props: {
                row: {
                    id: 3,
                    score: 4,
                    review: '<p>Imported.</p>',
                    game: { id: 1, name: 'Example', slug: 'example' },
                    user: { id: 9, name: 'Linked Reviewer' },
                    rater: { id: 4, name: 'Itch Handle', externalPlatform: 'itch_io' },
                    isFvnReview: false,
                },
                reviewStyle: '',
                showRater: true,
            },
        });

        expect(screen.getByText('Linked Reviewer')).toBeTruthy();
        expect(screen.queryByText('FVN.li')).toBeNull();
        expect(route).toHaveBeenCalledWith('users.reviews', 9);
    });

    test('shows the review platform next to the game title on a user review list', () => {
        const { rerender } = render(RatingRow, {
            props: {
                row: {
                    id: 4,
                    score: 5,
                    game: { id: 1, name: 'Example', slug: 'example' },
                    isFvnReview: true,
                    sourcePlatform: 'fvn_li',
                },
                reviewStyle: '',
            },
        });

        expect(screen.getByText('FVN.li')).toBeTruthy();

        rerender({
            row: {
                id: 5,
                score: 4,
                game: { id: 1, name: 'Example', slug: 'example' },
                isFvnReview: false,
                sourcePlatform: 'itch_io',
            },
            reviewStyle: '',
        });

        expect(screen.queryByText('FVN.li')).toBeNull();
        expect(screen.getByTitle('From itch.io')).toBeTruthy();
    });
});
