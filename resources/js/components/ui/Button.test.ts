import { render, screen } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import Button from './Button.svelte';

describe('Button', () => {
    test('renders inertia=false hrefs as plain anchors for redirect endpoints', () => {
        render(Button, {
            props: {
                href: '/auth/google/redirect',
                inertia: false,
                'aria-label': 'Continue with Google',
            },
        });

        const link = screen.getByRole('link', { name: 'Continue with Google' });

        expect(link).toBeInstanceOf(HTMLAnchorElement);
        expect(link.getAttribute('href')).toBe('/auth/google/redirect');
    });
});
