<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import TrashIcon from '@/components/icons/Trash.svelte';
    import { deleteMyGameScreenshot, uploadMyGameScreenshots } from '@/api/my-games';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { toast } from '@/utils/toast';
    import { Alert, Button, Card } from '@/components/ui';
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
    let uploadingScreenshots = $state(false);
    let deletingScreenshotIndex = $state<number | null>(null);
    let displayedScreenshots = $derived(screenshots ?? []);

    async function handleScreenshotUpload(files: FileList) {
        if (typeof window === 'undefined') return;
        if (uploadingScreenshots || deletingScreenshotIndex !== null) return;
        const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            alert('Please upload image files');
            return;
        }

        uploadingScreenshots = true;

        try {
            await uploadMyGameScreenshots(gameSlug, imageFiles);
            if (!(await refreshPage(['game', 'metaTags']))) return;
            toast.success('Screenshots uploaded successfully');
        } catch (e: unknown) {
            console.error('Failed to upload screenshots', e);
            toast.error(e instanceof Error ? e.message : 'Failed to upload screenshots');
        } finally {
            uploadingScreenshots = false;
        }
    }

    async function handleScreenshotDelete(index: number) {
        if (typeof window === 'undefined') return;
        if (uploadingScreenshots || deletingScreenshotIndex !== null) return;
        if (!confirm('Delete this screenshot?')) return;

        deletingScreenshotIndex = index;
        try {
            await deleteMyGameScreenshot(gameSlug, index, displayedScreenshots[index]?.id);
            if (!(await refreshPage(['game', 'metaTags']))) return;
            toast.success('Screenshot deleted successfully');
        } catch (e: unknown) {
            console.error('Failed to delete screenshot', e);
            toast.error(e instanceof Error ? e.message : 'Failed to delete screenshot');
        } finally {
            deletingScreenshotIndex = null;
        }
    }
</script>

{#if (displayedScreenshots && displayedScreenshots.length > 0) || canEdit}
    <Card id="screenshots" variant="flat" class="mb-6 scroll-mt-28">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-fg">Screenshots</h2>
            {#if canEdit}
                <label
                    aria-busy={uploadingScreenshots}
                    class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-on-accent transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-accent {uploadingScreenshots
                        ? 'cursor-wait bg-accent opacity-70'
                        : 'cursor-pointer bg-accent hover:opacity-90'}"
                >
                    {#if uploadingScreenshots}
                        <LoadingSpinner size="sm" currentColor isBusy={false} />
                        <span>Uploading...</span>
                    {:else}
                        <PlusIcon class="h-4 w-4" />
                        <span>Add Screenshots</span>
                    {/if}
                    <input
                        type="file"
                        aria-label="Add screenshots"
                        accept="image/*"
                        multiple
                        disabled={uploadingScreenshots || deletingScreenshotIndex !== null}
                        onchange={(e) => {
                            const input = e.target as HTMLInputElement;
                            if (input.files) handleScreenshotUpload(input.files);
                            input.value = '';
                        }}
                        class="sr-only"
                    />
                </label>
            {/if}
        </div>

        {#if shouldBlur}
            <Alert title="Content Warning" role="status" class="mb-4">
                Screenshots are blurred as they may contain sensitive or NSFW content. Click on any screenshot to view it in full.
            </Alert>
        {/if}

        {#if displayedScreenshots && displayedScreenshots.length > 0}
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4" id="screenshots-gallery">
                {#each displayedScreenshots as screenshot, index (`${screenshot.url}-${index}`)}
                    {@const thumbnailUrl = getThumbnailUrl(screenshot)}
                    {@const fullUrl = screenshot.url}
                    <div class="relative h-32 w-full">
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
                                disabled={uploadingScreenshots || deletingScreenshotIndex !== null}
                                tone="danger"
                                size="icon-sm"
                                class="absolute top-2 right-2 z-10 rounded-full"
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
            <div class="py-12 text-center text-fg-muted">
                <p>No screenshots yet. Click "Add Screenshots" to upload some.</p>
            </div>
        {/if}
    </Card>
{/if}
