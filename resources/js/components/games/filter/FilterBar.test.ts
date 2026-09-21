import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
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

        await fireEvent.click(screen.getByRole('button', { name: /^clear$/i }));

        await waitFor(() => {
            const url = visit.mock.calls.at(-1)?.[0] ?? '';
            expect(url).toContain('noDefaults=true');
            expect(url).not.toContain('selectedStatuses');
            expect(url).not.toContain('nsfw');
        });
    });
});
