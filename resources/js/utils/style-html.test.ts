import { describe, expect, it } from 'vitest';

import { escapeStyleElementText } from '@/utils/style-html';

describe('style-html', () => {
    it('escapes style tag breakouts before raw html insertion', () => {
        expect(escapeStyleElementText('.x::before{content:"</style><script>alert(1)</script>"}')).toBe(
            '.x::before{content:"&lt;/style&gt;&lt;script&gt;alert(1)&lt;/script&gt;"}',
        );
    });

    it('escapes ampersands before angle brackets', () => {
        expect(escapeStyleElementText('.x::before{content:"&</style>"}')).toBe('.x::before{content:"&amp;&lt;/style&gt;"}');
    });
});
