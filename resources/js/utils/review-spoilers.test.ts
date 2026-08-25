import { describe, expect, test } from 'vitest';
import { revealInlineSpoiler } from './review-spoilers';

describe('revealInlineSpoiler', () => {
    test('keeps an activated inline spoiler revealed', () => {
        document.body.innerHTML = '<span class="spoiler" tabindex="0" role="button" title="Reveal"><strong>Secret</strong></span>';
        const spoiler = document.querySelector<HTMLElement>('.spoiler')!;

        expect(revealInlineSpoiler(spoiler.querySelector('strong'))).toBe(true);
        spoiler.blur();

        expect(spoiler.classList.contains('revealed')).toBe(true);
        expect(spoiler.hasAttribute('tabindex')).toBe(false);
        expect(revealInlineSpoiler(spoiler)).toBe(false);
    });
});
