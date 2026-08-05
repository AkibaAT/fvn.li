// @vitest-environment node
import { render } from 'svelte/server';
import { describe, expect, test } from 'vitest';

import SeoHead from './SeoHead.svelte';

describe('SeoHead (server render)', () => {
    test('emits no head tags so the Blade shell stays the only server-side source', () => {
        const { head } = render(SeoHead, {
            props: { metaTags: { browserTitle: 'Visual Novels', title: 'Fallback', noindex: true } },
        });

        expect(head).not.toContain('<title');
        expect(head).not.toContain('robots');
    });
});
