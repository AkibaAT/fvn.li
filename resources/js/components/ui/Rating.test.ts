import { render, screen } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import Rating from './Rating.svelte';

describe('Rating', () => {
    test('renders the accent star, value, and review count', () => {
        const { container } = render(Rating, { props: { score: 4.6, count: 128 } });

        const rating = screen.getByRole('img', { name: 'Rated 4.6 out of 5 from 128 reviews' });

        expect(rating.textContent).toContain('4.6');
        expect(rating.textContent).toContain('(128)');

        const star = container.querySelector('svg');
        expect(star?.classList.contains('text-accent')).toBe(true);
        expect(star?.classList.contains('h-[11px]')).toBe(true);

        const value = screen.getByText('4.6');
        expect(value.classList.contains('font-semibold')).toBe(true);
        expect(value.classList.contains('text-fg')).toBe(true);

        const count = screen.getByText('(128)');
        expect(count.classList.contains('text-fg-faint')).toBe(true);
    });

    test('omits the review count when there are no reviews', () => {
        render(Rating, { props: { score: 3.5, count: 0 } });

        const rating = screen.getByRole('img', { name: 'Rated 3.5 out of 5' });
        expect(rating.textContent).toContain('3.5');
        expect(rating.textContent).not.toContain('(');
    });

    test('renders nothing when there is no rating', () => {
        const { container } = render(Rating, { props: { score: null, count: 0 } });
        expect(container.querySelector('[role="img"]')).toBeNull();

        const { container: zero } = render(Rating, { props: { score: 0, count: 4 } });
        expect(zero.querySelector('[role="img"]')).toBeNull();
    });
});
