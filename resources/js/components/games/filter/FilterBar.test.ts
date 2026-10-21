import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import FilterBar from './FilterBar.svelte';
import type { CurrentFilters, FilterOptions } from '@/types';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({
    router: { visit },
}));

const filterOptions: FilterOptions = {
    statuses: { Released: 'Released', 'In development': 'In development' },
    gameEngines: { RenPy: "Ren'Py", TyranoScript: 'TyranoScript' },
    platforms: { windows: 'Windows', linux: 'Linux', mac: 'macOS', android: 'Android', web: 'Web' },
    storePlatforms: { itch_io: 'itch.io', steam: 'Steam', other: 'Other' },
    languages: {
        eng: { ref_name: 'English', flag_code: 'us' },
        jpn: { ref_name: 'Japanese', flag_code: 'jp' },
    },
    gameJams: {},
    tags: { '1': 'Romance (42)', '2': 'Horror (7)' },
    privateTags: {},
    readingTimeOptions: {
        short: 'Short (< 10k words)',
        medium: 'Medium (10k-50k words)',
        long: 'Long (> 50k words)',
    },
};

function renderBar(currentFilters: CurrentFilters = {}) {
    return render(FilterBar, { props: { filters: filterOptions, currentFilters } });
}

async function openMenu() {
    await fireEvent.click(screen.getByRole('button', { name: 'Add filter' }));
}

describe('FilterBar', () => {
    test('opens the field menu grouped by category and drills into a value picker', async () => {
        renderBar();

        await openMenu();
        expect(screen.getByRole('menu', { name: 'Available filters' })).toBeTruthy();
        expect(screen.getByRole('menuitem', { name: /tags/i })).toBeTruthy();
        expect(screen.getByRole('menuitem', { name: /content rating/i })).toBeTruthy();

        await fireEvent.click(screen.getByRole('menuitem', { name: /^status/i }));
        const option = screen.getByRole('option', { name: 'Released' });
        await fireEvent.click(option);

        await waitFor(() => {
            expect(visit).toHaveBeenCalledWith(
                expect.stringContaining('selectedStatuses%5B%5D=Released'),
                expect.objectContaining({ preserveState: true }),
            );
        });
    });

    test('offers private tags only when the user has some, and filters by their id', async () => {
        renderBar();
        await openMenu();
        expect(screen.queryByRole('menuitem', { name: /private tags/i })).toBeNull();
        cleanup();

        render(FilterBar, { props: { filters: { ...filterOptions, privateTags: { '5': 'Favorites', '9': 'Re-read' } }, currentFilters: {} } });
        await openMenu();
        await fireEvent.click(screen.getByRole('menuitem', { name: /private tags/i }));
        await fireEvent.click(screen.getByRole('option', { name: 'Re-read' }));

        await waitFor(() => {
            expect(visit).toHaveBeenCalledWith(
                expect.stringContaining('selectedPrivateTags%5B%5D=9'),
                expect.objectContaining({ preserveState: true }),
            );
        });
    });

    test('shows selected private tags as removable chips', () => {
        render(FilterBar, {
            props: {
                filters: { ...filterOptions, privateTags: { '5': 'Favorites' } },
                currentFilters: { selectedPrivateTags: ['5'] },
            },
        });

        expect(screen.getByRole('button', { name: 'Edit filter: Favorites' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Remove Favorites' })).toBeTruthy();
    });

    test('supports switching a status chip back off from the picker', async () => {
        renderBar({ selectedStatuses: ['Released'] });

        expect(screen.getByText('Released')).toBeTruthy();

        await openMenu();
        await fireEvent.click(screen.getByRole('menuitem', { name: /^status/i }));
        await fireEvent.click(screen.getByRole('option', { name: 'Released' }));

        await waitFor(() => {
            expect(visit).toHaveBeenCalledWith(expect.not.stringContaining('selectedStatuses'), expect.anything());
        });
    });

    test('tags picker separates include and exclude operators', async () => {
        renderBar();

        await openMenu();
        await fireEvent.click(screen.getByRole('menuitem', { name: /^tags/i }));
        await fireEvent.click(screen.getByRole('button', { name: 'Exclude' }));

        await fireEvent.click(screen.getByRole('option', { name: 'Romance (42)' }));

        await waitFor(() => {
            expect(visit).toHaveBeenCalledWith(expect.stringContaining('excludedTags%5B%5D=1'), expect.anything());
        });
    });

    test('removing a chip navigates without that value', async () => {
        renderBar({ selectedTags: ['1'] });

        await fireEvent.click(screen.getByRole('button', { name: 'Remove Romance (42)' }));

        await waitFor(() => {
            expect(visit).toHaveBeenCalledWith(expect.not.stringContaining('selectedTags'), expect.anything());
        });
    });

    test('chip click opens the matching picker view directly', async () => {
        renderBar({ selectedStatuses: ['Released'] });

        await fireEvent.click(screen.getByRole('button', { name: 'Edit filter: Released' }));

        expect(screen.getByRole('option', { name: 'In development' })).toBeTruthy();
    });

    test('clear all resets every facet through the hook', async () => {
        renderBar({ selectedStatuses: ['Released'], nsfw: true });

        await fireEvent.click(screen.getByRole('button', { name: 'Clear all filters' }));

        await waitFor(() => {
            const url = visit.mock.calls.at(-1)?.[0] ?? '';
            expect(url).toContain('noDefaults=true');
            expect(url).not.toContain('selectedStatuses');
            expect(url).not.toContain('nsfw');
        });
    });

    test('names excluded tag chips as exclusions for assistive tech', () => {
        renderBar({ selectedTags: ['2'], excludedTags: ['1'] });

        expect(screen.getByRole('button', { name: 'Edit filter: Excluding Romance (42)' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Remove Excluding Romance (42)' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Edit filter: Horror (7)' })).toBeTruthy();
    });

    test('removing an excluded tag chip leaves included tags untouched', async () => {
        renderBar({ selectedTags: ['2'], excludedTags: ['1'] });

        await fireEvent.click(screen.getByRole('button', { name: 'Remove Excluding Romance (42)' }));

        await waitFor(() => {
            const url = visit.mock.calls.at(-1)?.[0] ?? '';
            expect(url).toContain('selectedTags%5B%5D=2');
            expect(url).not.toContain('excludedTags%5B%5D=1');
        });
    });

    test('shows ignored-games visibility as a chip that clear all resets', async () => {
        renderBar({ showIgnored: true });

        expect(screen.getByRole('button', { name: 'Edit filter: Showing ignored' })).toBeTruthy();

        await openMenu();
        expect(screen.getByRole('menuitem', { name: 'Visibility, 1 active' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Clear all filters' }));
        await waitFor(() => {
            expect(visit.mock.calls.at(-1)?.[0]).not.toContain('showIgnored');
        });
    });

    test('moves focus into the picker and back to the field that opened it', async () => {
        renderBar();
        await openMenu();

        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: 'Tags' })));

        await fireEvent.click(screen.getByRole('menuitem', { name: /^status/i }));
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('option', { name: 'In development' })));

        await fireEvent.click(screen.getByRole('button', { name: 'Back to filter menu' }));
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('menuitem', { name: /^status/i })));
    });

    test('focuses the search input for long option lists', async () => {
        render(FilterBar, {
            props: {
                filters: { ...filterOptions, tags: Object.fromEntries(Array.from({ length: 12 }, (_, i) => [String(i), `Tag ${i}`])) },
                currentFilters: {},
            },
        });
        await openMenu();
        await fireEvent.click(screen.getByRole('menuitem', { name: 'Tags' }));

        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('textbox', { name: 'Search tags' })));
        await fireEvent.keyDown(screen.getByRole('textbox', { name: 'Search tags' }), { key: 'ArrowDown' });
        expect(document.activeElement).toBe(screen.getByRole('option', { name: 'Tag 0' }));
    });

    test('arrow keys move between menu items and options', async () => {
        renderBar();
        await openMenu();

        const items = screen.getAllByRole('menuitem');
        await waitFor(() => expect(document.activeElement).toBe(items[0]));
        await fireEvent.keyDown(items[0], { key: 'ArrowDown' });
        expect(document.activeElement).toBe(items[1]);
        await fireEvent.keyDown(items[1], { key: 'End' });
        expect(document.activeElement).toBe(items.at(-1));
        expect(items.filter((item) => item.tabIndex === 0)).toEqual([items.at(-1)]);

        await fireEvent.keyDown(items.at(-1)!, { key: 'ArrowDown' });
        expect(document.activeElement).toBe(items[0]);
    });

    test('Escape closes the builder and returns focus to the trigger', async () => {
        renderBar();
        const trigger = screen.getByRole('button', { name: 'Add filter' });
        trigger.focus();
        await fireEvent.click(trigger);

        await fireEvent.keyDown(document.activeElement!, { key: 'Escape' });

        await waitFor(() => {
            expect(screen.queryByRole('dialog', { name: 'Filter builder' })).toBeNull();
            expect(document.activeElement).toBe(trigger);
        });
    });

    test('closes the builder when focus moves outside it', async () => {
        renderBar({ selectedStatuses: ['Released'] });
        await openMenu();

        await fireEvent.focusOut(screen.getAllByRole('menuitem')[0], { relatedTarget: screen.getByRole('button', { name: 'Clear all filters' }) });

        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Filter builder' })).toBeNull());
    });
});
