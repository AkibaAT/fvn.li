<script lang="ts">
    import PlusIcon from '@/components/icons/Plus.svelte';
    import TrashIcon from '@/components/icons/Trash.svelte';
    import { deleteMyGameScreenshot, uploadMyGameScreenshots } from '@/api/my-games';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { toast } from '@/utils/toast';
    import { Alert, Button, Card } from '@/components/ui';
    import { resolveDeletedScreenshots, resolveUploadedScreenshots, type Screenshot } from './screenshotState';
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
        onUpdate?: (thumbnail: string | null, screenshots: Screenshot[]) => void;
    }

    let { screenshots, blur = false, onOpenLightbox, canEdit = false, gameSlug, gameName, onUpdate }: Props = $props();

    const shouldBlur = $derived(blur && !canEdit);
    let uploadingScreenshots = $state(false);
    let deletingScreenshotIndex = $state<number | null>(null);
    let displayedScreenshots = $derived(screenshots ?? []);

    async function handleScreenshotUpload(files: FileList) {
        if (typeof window === 'undefined') return;
        if (uploadingScreenshots) return;
        const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            alert('Please upload image files');
            return;
        }

        uploadingScreenshots = true;

        try {
            const data = await uploadMyGameScreenshots(gameSlug, imageFiles);
            const updatedScreenshots = resolveUploadedScreenshots(displayedScreenshots, data.screenshots, data.new_screenshots);
            displayedScreenshots = updatedScreenshots;
            onUpdate?.(null, updatedScreenshots);
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
            const data = await deleteMyGameScreenshot(gameSlug, index);
            const updatedScreenshots = resolveDeletedScreenshots(displayedScreenshots, index, data.screenshots);
            displayedScreenshots = updatedScreenshots;
            onUpdate?.(null, updatedScreenshots);
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
    <Card id="screenshots" class="mb-6 scroll-mt-28">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Screenshots</h2>
            {#if canEdit}
                <label
                    aria-busy={uploadingScreenshots}
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-white transition-colors {uploadingScreenshots
                        ? 'cursor-wait bg-blue-500'
                        : 'cursor-pointer bg-blue-600 hover:bg-blue-700'}"
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
                        accept="image/*"
                        multiple
                        disabled={uploadingScreenshots}
                        onchange={(e) => {
                            const input = e.target as HTMLInputElement;
                            if (input.files) handleScreenshotUpload(input.files);
                            input.value = '';
                        }}
                        class="hidden"
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
                    <div class="group relative h-32 w-full">
                        <a
                            href={fullUrl}
                            class="block h-full overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition-all hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
                            onclick={(e) => {
                                e.preventDefault();
                                onOpenLightbox?.(index);
                            }}
                        >
                            <div class="absolute inset-0">
                                <img
                                    src={thumbnailUrl}
                                    alt={gameScreenshotAltText(gameName, index, displayedScreenshots.length)}
                                    class="h-full w-full object-cover {shouldBlur ? 'blur-sm transition-all duration-300 hover:blur-none' : ''}"
                                />
                            </div>
                            <div class="absolute inset-0 bg-black/20 opacity-0 transition-opacity group-hover:opacity-100"></div>
                        </a>
                        {#if canEdit}
                            <Button
                                onclick={() => handleScreenshotDelete(index)}
                                disabled={uploadingScreenshots || deletingScreenshotIndex !== null}
                                tone="danger"
                                size="icon-sm"
                                class="absolute top-2 right-2 z-10 rounded-full shadow-lg"
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
            <div class="py-12 text-center text-gray-500 dark:text-gray-400">
                <p>No screenshots yet. Click "Add Screenshots" to upload some.</p>
            </div>
        {/if}
    </Card>
{/if}
