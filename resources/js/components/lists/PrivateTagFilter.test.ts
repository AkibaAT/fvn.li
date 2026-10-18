import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen } from '@testing-library/svelte';
import { expect, test, vi } from 'vitest';
import PrivateTagFilter from './PrivateTagFilter.svelte';

const options = [
    { id: 1, name: 'Favorites', count: 3 },
    { id: 2, name: 'Re-read', count: 1 },
];

test('marks selected tags as pressed and toggles on click', async () => {
    const onToggle = vi.fn();
    render(PrivateTagFilter, { options, selected: [2], onToggle, onClear: vi.fn() });
    expect(screen.getByRole('button', { name: 'Favorites 3' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Re-read 1' })).toHaveAttribute('aria-pressed', 'true');
    await fireEvent.click(screen.getByRole('button', { name: 'Favorites 3' }));
    expect(onToggle).toHaveBeenCalledWith(1);
});

test('offers clear only while a tag is selected', async () => {
    const onClear = vi.fn();
    const { rerender } = render(PrivateTagFilter, { options, selected: [], onToggle: vi.fn(), onClear });
    expect(screen.queryByRole('button', { name: 'Clear' })).not.toBeInTheDocument();
    await rerender({ options, selected: [1], onToggle: vi.fn(), onClear });
    await fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
    expect(onClear).toHaveBeenCalled();
});
