import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import Pagination from './Pagination.svelte';

const meta = (overrides: Record<string, number>) => ({ current_page: 1, last_page: 1, total: 3, per_page: 5, from: 1, to: 3, ...overrides });

describe('Pagination', () => {
    test('renders nothing for a single page that no per-page option can split', () => {
        const { container } = render(Pagination, { props: { layout: 'full', meta: meta({}), onChange: vi.fn(), onPerPageChange: vi.fn() } });

        expect(container.textContent?.trim()).toBe('');
    });

    test('keeps only the per-page selector when a smaller page size would paginate', () => {
        render(Pagination, {
            props: { layout: 'full', meta: meta({ total: 8, per_page: 10, to: 8 }), onChange: vi.fn(), onPerPageChange: vi.fn(), label: 'reviews' },
        });

        expect(screen.getByLabelText('Number of reviews per page')).toBeTruthy();
        expect(screen.queryByLabelText('Select page number')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Go to page 2' })).toBeNull();
    });

    test('shows page controls when there is more than one page', () => {
        render(Pagination, { props: { layout: 'full', meta: meta({ last_page: 3, total: 15 }), onChange: vi.fn() } });

        expect((screen.getByLabelText('Select page number') as HTMLSelectElement).value).toBe('1');
        expect(screen.getByRole('button', { name: 'Go to page 2' })).toBeTruthy();
    });

    test('pages layout renders crawlable links when page URLs are available', async () => {
        const onChange = vi.fn();
        render(Pagination, {
            props: {
                layout: 'pages',
                meta: meta({ current_page: 2, last_page: 3, total: 15 }),
                onChange,
                buildPageUrl: (page: number) => `/games?page=${page}`,
            },
        });

        const next = screen.getByRole('link', { name: 'Next page' });
        expect(next.getAttribute('href')).toBe('/games?page=3');
        expect(screen.getByRole('link', { name: 'Go to page 1' }).getAttribute('href')).toBe('/games?page=1');
        expect(screen.getByRole('link', { name: 'Go to page 2' }).getAttribute('aria-current')).toBe('page');

        await fireEvent.click(next);
        expect(onChange).toHaveBeenCalledWith(3);
    });

    test('pages layout falls back to buttons without page URLs', () => {
        render(Pagination, { props: { layout: 'pages', meta: meta({ current_page: 1, last_page: 3, total: 15 }), onChange: vi.fn() } });

        expect(screen.queryByRole('link')).toBeNull();
        expect((screen.getByRole('button', { name: 'Previous page' }) as HTMLButtonElement).disabled).toBe(true);
    });
});
