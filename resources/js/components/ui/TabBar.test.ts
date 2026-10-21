import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';
import TabBar from './TabBar.svelte';

describe('TabBar', () => {
    const tabs = [
        { id: 'all', label: 'All', count: 3, href: '/lists?type=all' },
        { id: 'public', label: 'Public', count: 1, href: '/lists?type=public' },
    ];

    test('renders link tabs with counts and routes plain clicks through onSelect', async () => {
        const onSelect = vi.fn();
        render(TabBar, { props: { tabs, active: 'all', onSelect, ariaLabel: 'List visibility' } });

        const publicTab = screen.getByRole('tab', { name: 'Public (1)' });
        expect(publicTab.getAttribute('href')).toBe('/lists?type=public');
        expect(screen.getByRole('tab', { name: 'All (3)' }).getAttribute('aria-selected')).toBe('true');

        const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        publicTab.dispatchEvent(click);

        expect(onSelect).toHaveBeenCalledWith('public');
        expect(click.defaultPrevented).toBe(true);
    });

    test('is a single tab stop with arrow-key navigation', async () => {
        render(TabBar, { props: { tabs: [{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }], active: 'a', onSelect: vi.fn() } });

        const [first, second] = screen.getAllByRole('tab');
        expect(second.tabIndex).toBe(-1);

        first.focus();
        await fireEvent.keyDown(first, { key: 'ArrowRight' });

        expect(document.activeElement).toBe(second);
        expect(first.tabIndex).toBe(-1);
    });
});
