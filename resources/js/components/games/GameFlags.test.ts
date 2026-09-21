import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import GameFlags from './GameFlags.svelte';

const baseGame = {
    id: 1,
    name: 'Test Game',
    effective_name: 'Test Game',
    slug: 'test-game',
    status: 'Released',
    is_nsfw: false,
    is_paid: false,
    has_demo: false,
    is_on_sale: false,
};

describe('GameFlags', () => {
    test('keeps the fixed flag order and skips default states', () => {
        render(GameFlags, {
            props: {
                game: { ...baseGame, status: 'In Development', is_nsfw: true, is_paid: true, has_demo: true, is_on_sale: true },
            },
        });

        const labels = screen.getAllByRole('button').map((button) => button.textContent?.trim());
        expect(labels).toEqual(['In dev', '18+', 'Paid', 'Demo', 'Sale']);
    });

    test('renders no flags for a released or published, SFW, free game', () => {
        render(GameFlags, { props: { game: baseGame } });
        expect(screen.queryAllByRole('button')).toHaveLength(0);

        render(GameFlags, { props: { game: { ...baseGame, id: 2, status: 'Published' } } });
        expect(screen.queryAllByRole('button')).toHaveLength(0);
    });

    test('marks filters active and forwards the original status value', async () => {
        const onStatusClick = vi.fn();
        const onNsfwToggle = vi.fn();

        render(GameFlags, {
            props: {
                game: { ...baseGame, status: 'Abandoned', is_nsfw: true },
                selectedStatuses: ['Abandoned'],
                nsfw: true,
                onStatusClick,
                onNsfwToggle,
            },
        });

        const status = screen.getByRole('button', { name: 'Filter by status: Abandoned' });
        expect(status.textContent).toBe('Abandoned');
        expect(status.getAttribute('aria-pressed')).toBe('true');

        const nsfw = screen.getByRole('button', { name: 'Filter by NSFW content' });
        expect(nsfw.textContent).toBe('18+');
        expect(nsfw.getAttribute('aria-pressed')).toBe('true');

        await fireEvent.click(status);
        await fireEvent.click(nsfw);
        expect(onStatusClick).toHaveBeenCalledWith('Abandoned');
        expect(onNsfwToggle).toHaveBeenCalledTimes(1);
    });
});
