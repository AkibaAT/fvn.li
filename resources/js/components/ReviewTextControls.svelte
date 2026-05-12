<script lang="ts" module>
    /**
     * Hook to get the review styles for use in other components.
     * Call this from Svelte components using $effect to stay reactive.
     */
    export function getReviewTextStyles(): {
        maxWidth: string;
        fontSize: string;
        lineHeight: string;
        margin: string;
    } {
        if (typeof window !== 'undefined') {
            const savedWidth = localStorage.getItem('reviewWidthPreference');
            const savedFontSize = localStorage.getItem('reviewFontSizePreference');
            const savedLineHeight = localStorage.getItem('reviewLineHeightPreference');

            return {
                maxWidth: `${savedWidth ? parseInt(savedWidth) : 100}%`,
                fontSize: `${savedFontSize ? parseInt(savedFontSize) : 100}%`,
                lineHeight: `${savedLineHeight ? parseInt(savedLineHeight) : 150}%`,
                margin: '0 auto',
            };
        }
        return {
            maxWidth: '100%',
            fontSize: '100%',
            lineHeight: '150%',
            margin: '0 auto',
        };
    }

    /**
     * Svelte 5 reactive hook for review text styles.
     * Returns a reactive object that updates when localStorage changes.
     */
    export function useReviewTextStyles() {
        let styles = $state(getReviewTextStyles());

        $effect(() => {
            const handleStorageChange = () => {
                const newStyles = getReviewTextStyles();
                const hasChanged = JSON.stringify(styles) !== JSON.stringify(newStyles);
                if (hasChanged) {
                    styles = newStyles;
                }
            };

            window.addEventListener('storage', handleStorageChange);
            window.addEventListener('reviewTextStylesChanged', handleStorageChange);

            return () => {
                window.removeEventListener('storage', handleStorageChange);
                window.removeEventListener('reviewTextStylesChanged', handleStorageChange);
            };
        });

        return {
            get maxWidth() {
                return styles.maxWidth;
            },
            get fontSize() {
                return styles.fontSize;
            },
            get lineHeight() {
                return styles.lineHeight;
            },
            get margin() {
                return styles.margin;
            },
        };
    }
</script>

<script lang="ts">
    import { Button, Card } from '@/components/ui';

    let { class: className = '' }: { class?: string } = $props();

    // Flag to prevent localStorage writes when responding to storage events
    let isUpdatingFromStorage = false;

    let reviewWidth = $state<number | null>(
        typeof window !== 'undefined'
            ? (() => {
                  const s = localStorage.getItem('reviewWidthPreference');
                  return s ? parseInt(s) : null;
              })()
            : null,
    );

    let reviewFontSize = $state<number | null>(
        typeof window !== 'undefined'
            ? (() => {
                  const s = localStorage.getItem('reviewFontSizePreference');
                  return s ? parseInt(s) : null;
              })()
            : null,
    );

    let reviewLineHeight = $state<number | null>(
        typeof window !== 'undefined'
            ? (() => {
                  const s = localStorage.getItem('reviewLineHeightPreference');
                  return s ? parseInt(s) : null;
              })()
            : null,
    );

    // Listen for storage changes from other tabs
    $effect(() => {
        let storageTimeoutId: number | null = null;

        const handleStorageChange = (e: StorageEvent) => {
            if (storageTimeoutId !== null) {
                clearTimeout(storageTimeoutId);
            }

            isUpdatingFromStorage = true;

            if (e.key === 'reviewWidthPreference') {
                reviewWidth = e.newValue ? parseInt(e.newValue) : null;
            } else if (e.key === 'reviewFontSizePreference') {
                reviewFontSize = e.newValue ? parseInt(e.newValue) : null;
            } else if (e.key === 'reviewLineHeightPreference') {
                reviewLineHeight = e.newValue ? parseInt(e.newValue) : null;
            }

            storageTimeoutId = window.setTimeout(() => {
                isUpdatingFromStorage = false;
                storageTimeoutId = null;
            }, 50);
        };

        window.addEventListener('storage', handleStorageChange);
        return () => {
            window.removeEventListener('storage', handleStorageChange);
            if (storageTimeoutId !== null) {
                clearTimeout(storageTimeoutId);
            }
        };
    });

    // Save review width
    $effect(() => {
        if (!isUpdatingFromStorage && typeof window !== 'undefined') {
            if (reviewWidth !== null) {
                localStorage.setItem('reviewWidthPreference', reviewWidth.toString());
            } else {
                localStorage.removeItem('reviewWidthPreference');
            }
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    });

    // Save review font size
    $effect(() => {
        if (!isUpdatingFromStorage && typeof window !== 'undefined') {
            if (reviewFontSize !== null) {
                localStorage.setItem('reviewFontSizePreference', reviewFontSize.toString());
            } else {
                localStorage.removeItem('reviewFontSizePreference');
            }
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    });

    // Save review line height
    $effect(() => {
        if (!isUpdatingFromStorage && typeof window !== 'undefined') {
            if (reviewLineHeight !== null) {
                localStorage.setItem('reviewLineHeightPreference', reviewLineHeight.toString());
            } else {
                localStorage.removeItem('reviewLineHeightPreference');
            }
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    });

    function resetToDefault() {
        reviewWidth = 100;
        reviewFontSize = 100;
        reviewLineHeight = 150;
        if (typeof window !== 'undefined') {
            localStorage.setItem('reviewWidthPreference', '100');
            localStorage.setItem('reviewFontSizePreference', '100');
            localStorage.setItem('reviewLineHeightPreference', '150');
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    }

    const widthGradient = $derived(
        `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${(((reviewWidth || 100) - 50) / 50) * 100}%, #e5e7eb ${(((reviewWidth || 100) - 50) / 50) * 100}%, #e5e7eb 100%)`,
    );
    const fontSizeGradient = $derived(
        `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${(((reviewFontSize || 100) - 75) / 75) * 100}%, #e5e7eb ${(((reviewFontSize || 100) - 75) / 75) * 100}%, #e5e7eb 100%)`,
    );
    const lineHeightGradient = $derived(
        `linear-gradient(to right, #3b82f6 0%, #3b82f6 ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, #e5e7eb ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, #e5e7eb 100%)`,
    );
</script>

<Card padding="lg" class={className}>
    <div class="mb-6 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Review Text Controls</h3>
        <Button
            type="button"
            variant="ghost"
            tone="neutral"
            size="sm"
            onclick={resetToDefault}
            class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
        >
            Reset to Default
        </Button>
    </div>
    <div class="grid grid-cols-1 gap-6">
        <!-- Width Control -->
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Width</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">50%</span>
                <input
                    type="range"
                    min="50"
                    max="100"
                    value={reviewWidth || 100}
                    oninput={(e) => (reviewWidth = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-lg bg-gray-200 dark:bg-gray-700"
                    style:background={widthGradient}
                />
                <span class="text-sm text-gray-500 dark:text-gray-400">100%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                    {reviewWidth || 100}%
                </span>
            </div>
        </div>

        <!-- Font Size Control -->
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Font Size</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">75%</span>
                <input
                    type="range"
                    min="75"
                    max="150"
                    value={reviewFontSize || 100}
                    oninput={(e) => (reviewFontSize = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-lg bg-gray-200 dark:bg-gray-700"
                    style:background={fontSizeGradient}
                />
                <span class="text-sm text-gray-500 dark:text-gray-400">150%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                    {reviewFontSize || 100}%
                </span>
            </div>
        </div>

        <!-- Line Height Control -->
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Line Height</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500 dark:text-gray-400">100%</span>
                <input
                    type="range"
                    min="100"
                    max="300"
                    value={reviewLineHeight || 150}
                    oninput={(e) => (reviewLineHeight = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-lg bg-gray-200 dark:bg-gray-700"
                    style:background={lineHeightGradient}
                />
                <span class="text-sm text-gray-500 dark:text-gray-400">300%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-gray-700 dark:text-gray-300">
                    {reviewLineHeight || 150}%
                </span>
            </div>
        </div>
    </div>
</Card>
