import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import Flag from './Flag.svelte';

describe('Flag', () => {
    test('renders the shared outlined treatment', () => {
        render(Flag, { props: { label: 'In dev' } });

        const flag = screen.getByRole('button', { name: 'In dev' });

        expect(flag.textContent).toBe('In dev');
        expect([...flag.classList]).toEqual(
            expect.arrayContaining(['inline-flex', 'uppercase', 'border-border-strong', 'text-fg-muted', 'text-[10px]', 'rounded-[3px]']),
        );
        expect(flag.getAttribute('aria-pressed')).toBe('false');
    });

    test('marks the selected state with the --text border and text', () => {
        render(Flag, { props: { label: 'Paid', active: true } });

        const flag = screen.getByRole('button', { name: 'Paid' });

        expect(flag.classList.contains('border-fg')).toBe(true);
        expect(flag.classList.contains('text-fg')).toBe(true);
        expect(flag.classList.contains('border-border-strong')).toBe(false);
        expect(flag.getAttribute('aria-pressed')).toBe('true');
    });

    test('exposes an accessible name and fires the filter toggle', async () => {
        const onclick = vi.fn();
        render(Flag, { props: { label: '18+', ariaLabel: 'Filter by NSFW content', onclick } });

        const flag = screen.getByRole('button', { name: 'Filter by NSFW content' });
        await fireEvent.click(flag);

        expect(onclick).toHaveBeenCalledTimes(1);
    });
});
