import { describe, expect, it } from 'vitest';

import { escapeStyleElementText } from '@/utils/style-html';

describe('style-html', () => {
    it('neutralizes style tag breakouts before raw html insertion', () => {
        expect(escapeStyleElementText('.x::before{content:"</style><script>alert(1)</script>"}')).toBe(
            '.x::before{content:"<\\/style><script>alert(1)<\\/script>"}',
        );
    });

    it('preserves valid CSS selectors and ampersands', () => {
        expect(escapeStyleElementText('.header > .title { color: red; } .card & { border: 0; }')).toBe(
            '.header > .title { color: red; } .card & { border: 0; }',
        );
    });
});
