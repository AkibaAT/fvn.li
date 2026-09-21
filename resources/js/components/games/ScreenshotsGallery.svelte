<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import TrashIcon from '@/components/icons/Trash.svelte';
    import { deleteMyGameScreenshot, uploadMyGameScreenshots } from '@/api/my-games';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { getErrorMessage, useAsyncAction } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Alert, Button, Card, EmptyState } from '@/components/ui';
    import type { Screenshot } from '@/types/game-show';
    import { gameScreenshotAltText } from '@/utils/imageAltText';

    function getThumbnailUrl(screenshot: Screenshot): string {
        return screenshot.thumbnail_url || screenshot.url;
    }

    interface Props {
        screenshots: Screenshot[];
        blur?: boolean;
        onOpenLightbox?: (index: number) => void;
        canEdit?: boolean;
        gameSlug?: string;
        gameName?: string;
    }

    let { screenshots, blur = false, onOpenLightbox, canEdit = false, gameSlug, gameName }: Props = $props();

    const shouldBlur = $derived(blur && !canEdit);
    const uploadAction = useAsyncAction();
    let deletingScreenshotIndex = $state<number | null>(null);
    let displayedScreenshots = $derived(screenshots ?? []);
    let screenshotInput = $state<HTMLInputElement | null>(null);

    const wideColumnClasses: Record<number, string> = {
        3: 'lg:grid-cols-3',
        4: 'lg:grid-cols-4',
        5: 'lg:grid-cols-5',
    };

    const wideColumns = $derived.by(() => {
        const count = displayedScreenshots.length;
        if (count <= 4) return Math.max(3, count);
        const candidates = [4, 5, 3];
        return (
            candidates.find((columns) => count % columns === 0) ??
            candidates.reduce((best, columns) => (count % columns > count % best ? columns : best))
        );
    });

    async function handleScreenshotUpload(files: FileList) {
        if (typeof window === 'undefined') return;
        if (uploadAction.isLoading || deletingScreenshotIndex !== null) return;
        const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            toast.error('Please upload image files');
            return;
        }

        const uploaded = await uploadAction.run(
            async () => {
                await uploadMyGameScreenshots(gameSlug, imageFiles);
                if (!(await refreshPage(['game', 'metaTags']))) return false;
                return true;
            },
            { fallbackError: 'Failed to upload screenshots' },
        );
        if (!uploaded) return;
        toast.success('Screenshots uploaded successfully');
    }

    async function handleScreenshotDelete(index: number) {
        if (typeof window === 'undefined') return;
        if (uploadAction.isLoading || deletingScreenshotIndex !== null) return;
        if (!confirm('Delete this screenshot?')) return;

        deletingScreenshotIndex = index;
        try {
            await deleteMyGameScreenshot(gameSlug, index, displayedScreenshots[index]?.id);
            if (!(await refreshPage(['game', 'metaTags']))) return;
            toast.success('Screenshot deleted successfully');
        } catch (error) {
            console.error('Failed to delete screenshot', error);
            toast.error(getErrorMessage(error, 'Failed to delete screenshot'));
        } finally {
            deletingScreenshotIndex = null;
        }
    }
</script>

{#if (displayedScreenshots && displayedScreenshots.length > 0) || canEdit}
    <Card id="screenshots" variant="flat" padding="lg" class="mb-6 scroll-mt-32">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-title font-semibold text-fg">Screenshots</h2>
            {#if canEdit}
                <Button
                    type="button"
                    size="sm"
                    loading={uploadAction.isLoading}
                    disabled={uploadAction.isLoading || deletingScreenshotIndex !== null}
                    onclick={() => screenshotInput?.click()}
                >
                    {#snippet icon()}<PlusIcon class="h-4 w-4" />{/snippet}
                    {uploadAction.isLoading ? 'Uploading...' : 'Add Screenshots'}
                </Button>
                <input
                    bind:this={screenshotInput}
                    type="file"
                    aria-label="Add screenshots"
                    accept="image/*"
                    multiple
                    tabindex="-1"
                    disabled={uploadAction.isLoading || deletingScreenshotIndex !== null}
                    onchange={(e) => {
                        const input = e.target as HTMLInputElement;
                        if (input.files) handleScreenshotUpload(input.files);
                        input.value = '';
                    }}
                    class="sr-only"
                />
            {/if}
        </div>

        {#if shouldBlur}
            <Alert title="Content Warning" role="status" class="mb-4">
                Screenshots are blurred as they may contain sensitive or NSFW content. Click on any screenshot to view it in full.
            </Alert>
        {/if}

        {#if displayedScreenshots && displayedScreenshots.length > 0}
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 {wideColumnClasses[wideColumns]}" id="screenshots-gallery">
                {#each displayedScreenshots as screenshot, index (`${screenshot.url}-${index}`)}
                    {@const thumbnailUrl = getThumbnailUrl(screenshot)}
                    {@const fullUrl = screenshot.url}
                    <div class="relative aspect-video w-full">
                        <a
                            href={fullUrl}
                            class="block h-full overflow-hidden rounded-md border border-border bg-surface-alt transition-colors hover:border-border-strong"
                            onclick={(e) => {
                                e.preventDefault();
                                onOpenLightbox?.(index);
                            }}
                        >
                            <div class="absolute inset-0">
                                <img
                                    src={thumbnailUrl}
                                    alt={gameScreenshotAltText(gameName, index, displayedScreenshots.length)}
                                    class="h-full w-full object-cover {shouldBlur ? 'blur-sm transition-[filter] duration-300 hover:blur-none' : ''}"
                                />
                            </div>
                        </a>
                        {#if canEdit}
                            <Button
                                onclick={() => handleScreenshotDelete(index)}
                                disabled={uploadAction.isLoading || deletingScreenshotIndex !== null}
                                tone="danger"
                                size="icon-sm"
                                class="absolute top-2 right-2 z-10"
                                aria-label="Delete screenshot"
                            >
                                {#if deletingScreenshotIndex === index}
                                    <LoadingSpinner size="sm" currentColor isBusy={false} />
                                {:else}
                                    <TrashIcon class="h-4 w-4" />
                                {/if}
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        {:else}
            <EmptyState title="No screenshots yet" description="Use Add Screenshots to upload some." class="py-8" />
        {/if}
    </Card>
{/if}
