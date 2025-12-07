/**
 * Screenshot variant constants
 * These match the variants defined in ProcessGameScreenshots.php
 */
export const SCREENSHOT_VARIANTS = {
    SMALL: 'small',
    DEFAULT: 'default',
    LARGE: 'large',
} as const;

export type ScreenshotVariant = typeof SCREENSHOT_VARIANTS[keyof typeof SCREENSHOT_VARIANTS];

/**
 * Single optimized variant data
 */
export interface OptimizedScreenshotVariant {
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
