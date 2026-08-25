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
});
