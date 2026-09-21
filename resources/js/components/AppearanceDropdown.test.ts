import { fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, test } from 'vitest';

import AppearanceDropdown from './AppearanceDropdown.svelte';

describe('AppearanceDropdown', () => {
    afterEach(() => {
        document.documentElement.classList.remove('dark');
        localStorage.clear();
        document.cookie = 'appearance=;path=/;max-age=0';
    });

    test('marks the active option and syncs the theme colour when a mode is picked', async () => {
        document.head.innerHTML = `
            <meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
            <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#222836">
        `;
        render(AppearanceDropdown);

        await fireEvent.click(screen.getByRole('button', { name: 'Change appearance' }));
        expect(screen.getByRole('menuitemradio', { name: /System/ }).getAttribute('aria-checked')).toBe('true');

        await fireEvent.click(screen.getByRole('menuitemradio', { name: /Dark/ }));

        const metas = document.head.querySelectorAll('meta[name="theme-color"]');
        expect(metas).toHaveLength(1);
        expect(metas[0].getAttribute('media')).toBeNull();
        expect(metas[0].getAttribute('content')).toBe('#222836');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        await fireEvent.click(screen.getByRole('button', { name: 'Change appearance' }));
        expect(screen.getByRole('menuitemradio', { name: /Dark/ }).getAttribute('aria-checked')).toBe('true');
        expect(screen.getByRole('menuitemradio', { name: /System/ }).getAttribute('aria-checked')).toBe('false');
    });
});
