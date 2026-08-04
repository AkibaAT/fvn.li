<script lang="ts">
    import { Button } from '@/components/ui';
    import { gameScreenshotAltText, gameScreenshotThumbnailAltText } from '@/utils/imageAltText';
    import { untrack } from 'svelte';

    export type Screenshot = {
        id: number;
        url: string;
        thumbnail_url?: string;
        original_url?: string;
    };

    interface Props {
        isOpen: boolean;
        screenshots: Screenshot[];
        startIndex?: number;
        gameName?: string;
        onClose: () => void;
    }

    let { isOpen, screenshots, startIndex = 0, gameName, onClose }: Props = $props();

    let index = $state(untrack(() => startIndex));
    let isZoomed = $state(false);
    let touchStart: number | null = null;
    let touchEnd: number | null = null;

    $effect(() => {
        if (isOpen) {
            index = startIndex;
            isZoomed = false;
        }
    });

    const currentImage = $derived(!isOpen || !screenshots || screenshots.length === 0 ? '' : screenshots[index]?.url || '');

    function navigate(direction: 'prev' | 'next') {
        if (!screenshots || screenshots.length === 0) return;
        if (direction === 'prev') {
            index = index > 0 ? index - 1 : screenshots.length - 1;
        } else {
            index = index < screenshots.length - 1 ? index + 1 : 0;
        }
    }

    $effect(() => {
        if (!isOpen) return;
        function handleKeyDown(e: KeyboardEvent) {
            switch (e.key) {
                case 'Escape':
                    onClose();
                    break;
                case 'ArrowLeft':
                    navigate('prev');
                    break;
                case 'ArrowRight':
                    navigate('next');
                    break;
            }
        }
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    });

    function handleTouchStart(e: TouchEvent) {
        touchEnd = null;
        touchStart = e.targetTouches[0].clientX;
    }

    function handleTouchMove(e: TouchEvent) {
        touchEnd = e.targetTouches[0].clientX;
    }

    function handleTouchEnd() {
        if (!touchStart || !touchEnd) return;
        const distance = touchStart - touchEnd;
        if (distance > 50) navigate('next');
        else if (distance < -50) navigate('prev');
    }

    function toggleZoom() {
        isZoomed = !isZoomed;
    }
</script>

{#if isOpen && screenshots && screenshots.length > 0}
    <div class="bg-opacity-95 fixed inset-0 z-50 flex flex-col bg-black">
        <Button type="button" variant="ghost" tone="neutral" class="absolute inset-0" ariaLabel="Close lightbox" onclick={onClose}></Button>

        <div class="relative z-10 flex flex-shrink-0 items-center justify-between p-2">
            <div class="text-sm text-white">{index + 1} / {screenshots.length}</div>
            <Button
                type="button"
                variant="solid"
                tone="neutral"
                size="icon-lg"
                onclick={(e) => {
                    e.stopPropagation();
                    onClose();
                }}
                class="bg-opacity-90 hover:bg-opacity-100 cursor-pointer rounded-full bg-white p-2 text-black transition-colors"
                ariaLabel="Close lightbox"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </Button>
        </div>

        <div class="relative z-10 min-h-0 flex-1">
            {#if screenshots.length > 1}
                <Button
                    type="button"
                    variant="solid"
                    tone="neutral"
                    size="icon-lg"
                    onclick={(e) => {
                        e.stopPropagation();
                        navigate('prev');
                    }}
                    class="bg-opacity-90 hover:bg-opacity-100 absolute left-4 z-20 cursor-pointer rounded-full bg-white p-3 text-black transition-colors"
                    style="top: 50%; transform: translateY(-50%)"
                    ariaLabel="Previous screenshot"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </Button>
            {/if}

            <div class="flex h-full min-h-0 items-center justify-center p-4 sm:p-6">
                <Button
                    type="button"
                    variant="ghost"
                    tone="neutral"
                    class="flex h-full w-full items-center justify-center overflow-hidden transition-transform duration-300 {isZoomed
                        ? 'scale-150 cursor-zoom-out'
                        : 'cursor-zoom-in'}"
                    onclick={(e) => {
                        e.stopPropagation();
                        toggleZoom();
                    }}
                    ontouchstart={handleTouchStart}
                    ontouchmove={handleTouchMove}
                    ontouchend={handleTouchEnd}
                    ariaLabel={isZoomed ? 'Zoom out screenshot' : 'Zoom in screenshot'}
                    aria-pressed={isZoomed}
                >
                    <img
                        src={currentImage}
                        alt={gameScreenshotAltText(gameName, index, screenshots.length)}
                        class="block h-auto max-h-full w-auto max-w-full object-contain"
                    />
                </Button>
            </div>

            {#if screenshots.length > 1}
                <Button
                    type="button"
                    variant="solid"
                    tone="neutral"
                    size="icon-lg"
                    onclick={(e) => {
                        e.stopPropagation();
                        navigate('next');
                    }}
                    class="bg-opacity-90 hover:bg-opacity-100 absolute right-4 z-20 cursor-pointer rounded-full bg-white p-3 text-black transition-colors"
                    style="top: 50%; transform: translateY(-50%)"
                    ariaLabel="Next screenshot"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </Button>
            {/if}
        </div>

        <div class="bg-opacity-80 relative z-10 flex flex-shrink-0 items-center justify-between bg-black px-4 py-2">
            {#if screenshots.length > 1}
                <div class="mr-4 flex flex-1 justify-center overflow-hidden">
                    <div
                        class="flex gap-2 transition-transform duration-300 ease-out"
                        style="transform: translateX({-index * 72 + (Math.min(5, screenshots.length) - 1) * 36}px)"
                    >
                        {#each screenshots as screenshot, i (i)}
                            {@const thumbUrl = screenshot.thumbnail_url || screenshot.url}
                            <Button
                                type="button"
                                variant="ghost"
                                tone="neutral"
                                onclick={(e) => {
                                    e.stopPropagation();
                                    index = i;
                                }}
                                class="flex-shrink-0 cursor-pointer overflow-hidden rounded transition-all duration-300 {i === index
                                    ? 'scale-110 border-4 border-white opacity-100 shadow-lg'
                                    : 'border-2 border-transparent opacity-60 hover:scale-105 hover:opacity-100'}"
                                ariaLabel="Go to screenshot {i + 1}"
                            >
                                <img
                                    src={thumbUrl}
                                    alt={gameScreenshotThumbnailAltText(gameName, i, screenshots.length)}
                                    class="h-12 w-16 object-cover"
                                />
                            </Button>
                        {/each}
                    </div>
                </div>
            {:else}
                <div></div>
            {/if}

            <div class="flex items-center gap-3">
                <a
                    href={screenshots[index]?.original_url || screenshots[index]?.url}
                    target="_blank"
                    rel="noopener"
                    class="rounded bg-white px-3 py-1 text-sm font-medium text-black hover:bg-gray-100"
                    onclick={(e) => e.stopPropagation()}
                >
                    Open original
                </a>
            </div>
        </div>
    </div>
{/if}
