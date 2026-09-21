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
        `linear-gradient(to right, var(--accent) 0%, var(--accent) ${(((reviewWidth || 100) - 50) / 50) * 100}%, var(--border) ${(((reviewWidth || 100) - 50) / 50) * 100}%, var(--border) 100%)`,
    );
    const fontSizeGradient = $derived(
        `linear-gradient(to right, var(--accent) 0%, var(--accent) ${(((reviewFontSize || 100) - 75) / 75) * 100}%, var(--border) ${(((reviewFontSize || 100) - 75) / 75) * 100}%, var(--border) 100%)`,
    );
    const lineHeightGradient = $derived(
        `linear-gradient(to right, var(--accent) 0%, var(--accent) ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, var(--border) ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, var(--border) 100%)`,
    );
</script>

<Card padding="lg" class={className}>
    <div class="mb-6 flex items-center justify-between">
        <h3 class="text-base font-semibold text-fg">Review Text Controls</h3>
        <Button type="button" variant="ghost" tone="neutral" size="sm" onclick={resetToDefault}>Reset to Default</Button>
    </div>
    <div class="grid grid-cols-1 gap-6">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-fg">Width</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-fg-faint">50%</span>
                <input
                    type="range"
                    aria-label="Review text width"
                    min="50"
                    max="100"
                    value={reviewWidth || 100}
                    oninput={(e) => (reviewWidth = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-md bg-surface-alt"
                    style:background={widthGradient}
                />
                <span class="text-sm text-fg-faint">100%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-fg">
                    {reviewWidth || 100}%
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-fg">Font Size</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-fg-faint">75%</span>
                <input
                    type="range"
                    aria-label="Review font size"
                    min="75"
                    max="150"
                    value={reviewFontSize || 100}
                    oninput={(e) => (reviewFontSize = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-md bg-surface-alt"
                    style:background={fontSizeGradient}
                />
                <span class="text-sm text-fg-faint">150%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-fg">
                    {reviewFontSize || 100}%
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-fg">Line Height</span>
            <div class="flex items-center gap-3">
                <span class="text-sm text-fg-faint">100%</span>
                <input
                    type="range"
                    aria-label="Review line height"
                    min="100"
                    max="300"
                    value={reviewLineHeight || 150}
                    oninput={(e) => (reviewLineHeight = parseInt((e.target as HTMLInputElement).value))}
                    class="h-2 w-24 cursor-pointer appearance-none rounded-md bg-surface-alt"
                    style:background={lineHeightGradient}
                />
                <span class="text-sm text-fg-faint">300%</span>
                <span class="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-fg">
                    {reviewLineHeight || 150}%
                </span>
            </div>
        </div>
    </div>
</Card>
