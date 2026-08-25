import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import Navigation from './Navigation.svelte';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({
    page: { url: '/games' },
    router: { visit },
}));

vi.mock('ziggy-js', () => ({
    route: (name: string) => ({ 'games.index': '/games', 'lists.public': '/lists/public', 'ratings.index': '/ratings' })[name],
}));

describe('Navigation', () => {
    test('opens and dismisses the compact menu', async () => {
        render(Navigation);

        const button = screen.getByRole('button', { name: 'Open navigation menu' });
        expect(screen.getByRole('navigation').classList.contains('sm:flex')).toBe(true);
        await fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('true');

        await fireEvent.keyDown(window, { key: 'Escape' });
        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(button);
    });
});
