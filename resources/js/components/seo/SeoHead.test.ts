import { describe, expect, test } from 'vitest';

import { jsonForScriptTag } from './SeoHead.svelte';

describe('jsonForScriptTag', () => {
    test('escapes script-breaking characters while staying valid JSON', () => {
        const payload = {
            '@context': 'https://schema.org',
            '@type': 'VideoGame',
            description: '</script><script>alert("xss")</script>&\'',
        };

        const json = jsonForScriptTag(payload);

        expect(json).not.toContain('</script>');
        expect(json).not.toContain('<script>');
        expect(JSON.parse(json)).toEqual(payload);
    });
});
