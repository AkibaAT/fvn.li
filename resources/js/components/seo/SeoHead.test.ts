import { render } from '@testing-library/svelte';
import { describe, expect, test } from 'vitest';

import SeoHead from './SeoHead.svelte';

describe('SeoHead', () => {
    test('formats the browser title exactly like the Blade shell', () => {
        render(SeoHead, { props: { metaTags: { browserTitle: 'Visual Novels', title: 'Fallback' } } });

        expect(document.title).toBe('Visual Novels - FVN.li');
        expect(document.head.querySelectorAll('title')).toHaveLength(1);
    });

    test('falls back to the title prop when metaTags carry no title', () => {
        render(SeoHead, { props: { title: 'Log in' } });

        expect(document.title).toBe('Log in - FVN.li');
    });

    test('prefers metaTags over the fallback title prop', () => {
        render(SeoHead, { props: { metaTags: { title: 'Server Title' }, title: 'Fallback' } });

        expect(document.title).toBe('Server Title - FVN.li');
    });

    test('tolerates a null metaTags prop', () => {
        render(SeoHead, { props: { metaTags: null, title: 'Safe' } });

        expect(document.title).toBe('Safe - FVN.li');
    });

    test('only renders client-owned title and noindex tags', () => {
        render(SeoHead, { props: { metaTags: { title: 'Hidden Page', noindex: true, description: 'Server owned' } } });

        expect(document.head.querySelector('meta[name="robots"]')?.getAttribute('content')).toBe('noindex');
        expect(document.head.querySelector('meta[name="description"]')).toBeNull();
        expect(document.head.querySelector('meta[property^="og:"]')).toBeNull();
        expect(document.head.querySelector('script[type="application/ld+json"]')).toBeNull();
    });
});
