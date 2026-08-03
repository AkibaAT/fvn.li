import { render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import HomepageAuthLink from './HomepageAuthLink.svelte';

describe('HomepageAuthLink', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
    });

    test('links authenticated users to their dashboard', () => {
        render(HomepageAuthLink, { props: { isAuthenticated: true } });

        expect(screen.getByRole('link', { name: 'Dashboard' }).getAttribute('href')).toBe('http://localhost:3000/dashboard');
        expect(screen.queryByRole('link', { name: 'Log In' })).toBeNull();
    });

    test('links guests to login', () => {
        render(HomepageAuthLink, { props: { isAuthenticated: false } });

        expect(screen.getByRole('link', { name: 'Log In' }).getAttribute('href')).toBe('http://localhost:3000/login');
        expect(screen.queryByRole('link', { name: 'Dashboard' })).toBeNull();
    });
});
