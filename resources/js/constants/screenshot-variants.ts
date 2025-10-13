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
 * Helper type for the optimized screenshot structure
 */
export interface OptimizedScreenshotVariant {
    path: string;
    width?: number;
    height?: number;
    mime_type?: string;
}

export interface OptimizedScreenshotVariants {
    [SCREENSHOT_VARIANTS.SMALL]?: OptimizedScreenshotVariant;
    [SCREENSHOT_VARIANTS.DEFAULT]?: OptimizedScreenshotVariant;
    [SCREENSHOT_VARIANTS.LARGE]?: OptimizedScreenshotVariant;
}
