import { describe, expect, it } from 'vitest';

import { escapeHtml, highlightPlainText, renderTrustedMarksOnly } from '@/utils/safe-highlight';

describe('safe-highlight', () => {
    it('escapes plain html', () => {
        expect(escapeHtml('<strong>"hello"</strong>')).toBe('&lt;strong&gt;&quot;hello&quot;&lt;/strong&gt;');
    });

    it('highlights matches without trusting source html', () => {
        expect(highlightPlainText('hello <img src=x onerror=alert(1)>', 'hello')).toBe('<mark>hello</mark> &lt;img src=x onerror=alert(1)&gt;');
    });

    it('escapes text when there is no query', () => {
        expect(highlightPlainText('<strong>hello</strong>', '')).toBe('&lt;strong&gt;hello&lt;/strong&gt;');
    });

    it('preserves only exact mark tags from backend highlights', () => {
        expect(renderTrustedMarksOnly('<mark>hello</mark> <img src=x onerror=alert(1)>')).toBe(
            '<mark>hello</mark> &lt;img src=x onerror=alert(1)&gt;',
        );
    });

    it('does not trust mark tags with attributes', () => {
        expect(renderTrustedMarksOnly('<mark onclick=alert(1)>hello</mark>')).toBe('&lt;mark onclick=alert(1)&gt;hello</mark>');
    });
});
