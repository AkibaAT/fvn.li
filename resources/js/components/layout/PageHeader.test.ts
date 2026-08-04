import { render, screen } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import PageHeader from './PageHeader.svelte';

describe('PageHeader', () => {
    test('renders one semantic title with optional supporting content', () => {
        render(PageHeader, {
            props: {
                title: 'Public Visual Novel Lists',
                description: 'Discover lists shared by the community',
                backHref: '/lists',
                backLabel: 'Back to my lists',
            },
        });

        expect(screen.getByRole('heading', { level: 1, name: 'Public Visual Novel Lists' })).toBeTruthy();
        expect(screen.getByText('Discover lists shared by the community')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Back to my lists' }).getAttribute('href')).toMatch(/\/lists$/);
    });

    test('can let short descriptions use the available width', () => {
        render(PageHeader, {
            props: {
                title: 'Furry visual novel catalogue',
                description: 'Browse visual novels, get notified about updates, explore routes, and organize your reading lists.',
                descriptionWidth: 'full',
            },
        });

        expect(screen.getByText(/Browse visual novels/).classList.contains('max-w-3xl')).toBe(false);
    });
});
