/**
 * Screenshot variant constants
 * These match the variants defined in ProcessGameScreenshots.php
 */
export const SCREENSHOT_VARIANTS = {
    SMALL: 'small',
    DEFAULT: 'default',
    LARGE: 'large',
} as const;

/**
 * Single optimized variant data
 */
interface OptimizedScreenshotVariant {
    path: string;
    width?: number;
    height?: number;
    mime_type?: string;
}

/**
 * All optimized variants for a screenshot
 */
export interface OptimizedScreenshotVariants {
    [SCREENSHOT_VARIANTS.SMALL]?: OptimizedScreenshotVariant;
    [SCREENSHOT_VARIANTS.DEFAULT]?: OptimizedScreenshotVariant;
    [SCREENSHOT_VARIANTS.LARGE]?: OptimizedScreenshotVariant;
}
