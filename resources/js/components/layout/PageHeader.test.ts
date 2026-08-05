import { render, screen } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import PageHeader from './PageHeader.svelte';

describe('PageHeader', () => {
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
