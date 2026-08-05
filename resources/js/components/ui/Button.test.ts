import { render, screen } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import Button from './Button.svelte';

describe('Button', () => {
    test('uses the requested tone, variant, and size classes', () => {
        render(Button, {
            props: {
                tone: 'warning',
                variant: 'outline',
                size: 'xs',
            },
        });

        const button = screen.getByRole('button');

        expect([...button.classList]).toEqual(expect.arrayContaining(['border-amber-200', 'text-amber-800', 'min-h-7', 'text-xs']));
        expect(button.classList.contains('bg-amber-600')).toBe(false);
    });

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

    test('uses the shared current-color spinner while loading', () => {
        render(Button, { props: { loading: true } });

        const button = screen.getByRole('button');
        const spinner = screen.getByRole('status', { name: 'Loading' });

        expect(button.hasAttribute('disabled')).toBe(true);
        expect([...spinner.classList]).toEqual(expect.arrayContaining(['h-4', 'w-4', 'border-current', 'border-t-transparent']));
    });
});
