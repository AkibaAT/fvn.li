import { describe, expect, test } from 'vitest';

import { jsonForScriptTag } from './SeoHead.svelte';

describe('jsonForScriptTag', () => {
    test('escapes script-breaking characters for JSON-LD injection', () => {
        const json = jsonForScriptTag({
            '@context': 'https://schema.org',
            '@type': 'VideoGame',
            description: '</script><script>alert("xss")</script>&\'',
        });

        expect(json).toContain('\\u003C/script\\u003E\\u003Cscript\\u003Ealert(\\\\u0022xss\\\\u0022)\\u003C/script\\u003E\\u0026\\u0027');
        expect(json).not.toContain('</script>');
        expect(json).not.toContain('<script>');
    });
});
