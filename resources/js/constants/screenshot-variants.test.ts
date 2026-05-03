import { describe, expect, test } from 'vitest';
import { SCREENSHOT_VARIANTS, type OptimizedScreenshotVariants } from './screenshot-variants';

describe('screenshot variants', () => {
    test('keeps screenshot variant names aligned with backend processing names', () => {
        expect(SCREENSHOT_VARIANTS).toEqual({
            SMALL: 'small',
            DEFAULT: 'default',
            LARGE: 'large',
        });

        const variants: OptimizedScreenshotVariants = {
            [SCREENSHOT_VARIANTS.SMALL]: { path: 'small.webp', width: 320 },
            [SCREENSHOT_VARIANTS.DEFAULT]: { path: 'default.webp', width: 1280 },
        };

        expect(variants.small?.path).toBe('small.webp');
        expect(variants.default?.width).toBe(1280);
    });
});
