import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/svelte';
import { expect, test } from 'vitest';
import PrivateTagChips from './PrivateTagChips.svelte';

test('lists each private tag', () => {
    render(PrivateTagChips, {
        tags: [
            { id: 1, name: 'Favorites' },
            { id: 2, name: 'Re-read' },
        ],
    });
    const list = screen.getByRole('list', { name: 'Private tags' });
    expect(list).toHaveTextContent('Favorites');
    expect(list).toHaveTextContent('Re-read');
});

test('renders nothing without tags', () => {
    render(PrivateTagChips, { tags: [] });
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
});
