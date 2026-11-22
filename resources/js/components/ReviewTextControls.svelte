<script lang="ts" module>
    const DEFAULT_STYLES = {
        maxWidth: '100%',
        fontSize: '100%',
        lineHeight: '150%',
        margin: '0 auto',
    };

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
                maxWidth: `${savedWidth ? parseInt(savedWidth, 10) : 100}%`,
                fontSize: `${savedFontSize ? parseInt(savedFontSize, 10) : 100}%`,
                lineHeight: `${savedLineHeight ? parseInt(savedLineHeight, 10) : 150}%`,
                margin: '0 auto',
            };
        }
        return { ...DEFAULT_STYLES };
    }

    export function useReviewTextStyles() {
        let styles = $state({ ...DEFAULT_STYLES });

        $effect(() => {
            const handleStorageChange = () => {
                const newStyles = getReviewTextStyles();
                if (styles.maxWidth !== newStyles.maxWidth || styles.fontSize !== newStyles.fontSize || styles.lineHeight !== newStyles.lineHeight) {
                    styles = newStyles;
                }
            };

            handleStorageChange();

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

    export function useReviewStyleString() {
        const styles = useReviewTextStyles();

        return {
            get css() {
                return `max-width: ${styles.maxWidth}; font-size: ${styles.fontSize}; line-height: ${styles.lineHeight}; margin: ${styles.margin};`;
            },
        };
    }
</script>

<script lang="ts">
    import CogIcon from '@/components/icons/Cog.svelte';
    import { Button, Popover } from '@/components/ui';

    let { class: className = '' }: { class?: string } = $props();

    let popoverOpen = $state(false);
    let reviewWidth = $state<number | null>(null);
    let reviewFontSize = $state<number | null>(null);
    let reviewLineHeight = $state<number | null>(null);

    function persistPreference(key: string, value: number | null) {
        if (typeof window === 'undefined') return;
        if (value !== null) {
            localStorage.setItem(key, value.toString());
        } else {
            localStorage.removeItem(key);
        }
        window.dispatchEvent(new Event('reviewTextStylesChanged'));
    }

    $effect(() => {
        const readFromStorage = () => {
            const w = localStorage.getItem('reviewWidthPreference');
            const f = localStorage.getItem('reviewFontSizePreference');
            const l = localStorage.getItem('reviewLineHeightPreference');
            reviewWidth = w ? parseInt(w, 10) : null;
            reviewFontSize = f ? parseInt(f, 10) : null;
            reviewLineHeight = l ? parseInt(l, 10) : null;
        };

        readFromStorage();

        const handleStorageChange = (e: StorageEvent) => {
            if (e.key === 'reviewWidthPreference') {
                reviewWidth = e.newValue ? parseInt(e.newValue, 10) : null;
            } else if (e.key === 'reviewFontSizePreference') {
                reviewFontSize = e.newValue ? parseInt(e.newValue, 10) : null;
            } else if (e.key === 'reviewLineHeightPreference') {
                reviewLineHeight = e.newValue ? parseInt(e.newValue, 10) : null;
            }
        };

        window.addEventListener('storage', handleStorageChange);
        return () => {
            window.removeEventListener('storage', handleStorageChange);
        };
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

    const sliders = $derived([
        {
            label: 'Width',
            ariaLabel: 'Review text width',
            min: 50,
            max: 100,
            value: reviewWidth || 100,
            set: (value: number) => {
                reviewWidth = value;
                persistPreference('reviewWidthPreference', value);
            },
        },
        {
            label: 'Font size',
            ariaLabel: 'Review font size',
            min: 75,
            max: 150,
            value: reviewFontSize || 100,
            set: (value: number) => {
                reviewFontSize = value;
                persistPreference('reviewFontSizePreference', value);
            },
        },
        {
            label: 'Line height',
            ariaLabel: 'Review line height',
            min: 100,
            max: 300,
            value: reviewLineHeight || 150,
            set: (value: number) => {
                reviewLineHeight = value;
                persistPreference('reviewLineHeightPreference', value);
            },
        },
    ]);
</script>

{#snippet controls()}
    <div class="grid gap-4">
        {#each sliders as slider (slider.label)}
            <div class="flex items-center gap-3">
                <span class="w-24 shrink-0 text-ui font-medium text-fg">{slider.label}</span>
                <input
                    type="range"
                    aria-label={slider.ariaLabel}
                    min={slider.min}
                    max={slider.max}
                    value={slider.value}
                    oninput={(e) => slider.set(parseInt((e.target as HTMLInputElement).value))}
                    class="h-4 min-w-0 flex-1 cursor-pointer accent-accent"
                />
                <span class="w-12 shrink-0 text-right text-ui text-fg-muted tabular-nums">{slider.value}%</span>
            </div>
        {/each}
    </div>
{/snippet}

<Popover bind:open={popoverOpen} class={className}>
    <Button type="button" variant="ghost" tone="neutral" size="sm" aria-expanded={popoverOpen} onclick={() => (popoverOpen = !popoverOpen)}>
        {#snippet icon()}<CogIcon class="h-4 w-4" />{/snippet}
        Text settings
    </Button>
    {#if popoverOpen}
        <div class="popover-elevated absolute top-full right-0 z-30 mt-1 w-72 rounded-lg border border-border p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-ui font-semibold text-fg">Review text</h3>
                <Button type="button" variant="link" tone="neutral" size="sm" onclick={resetToDefault}>Reset</Button>
            </div>
            {@render controls()}
        </div>
    {/if}
</Popover>
