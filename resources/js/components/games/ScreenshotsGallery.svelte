<script lang="ts">
    import { authenticatedFetch } from '@/utils/csrf';
    import { toast } from '@/utils/toast';
    import { Button, Card } from '@/components/ui';
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
        const formData = new FormData();
        imageFiles.forEach((file) => formData.append('screenshots[]', file));

        try {
            const res = await authenticatedFetch(route('browser-api.my-games.screenshots.upload', { game: gameSlug }), {
                method: 'POST',
                body: formData,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                toast.error(data?.message || 'Failed to upload screenshots');
                return;
            }
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
            const res = await authenticatedFetch(route('browser-api.my-games.screenshots.delete', { game: gameSlug }), {
                method: 'DELETE',
                body: JSON.stringify({ index }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                toast.error(data?.message || 'Failed to delete screenshot');
                return;
            }
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
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <span>Uploading...</span>
                    {:else}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
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
            <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/30">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Content Warning</h3>
                        <div class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                            <p>Screenshots are blurred as they may contain sensitive or NSFW content. Click on any screenshot to view it in full.</p>
                        </div>
                    </div>
                </div>
            </div>
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
                                class="absolute top-2 right-2 z-10 rounded-full bg-red-600 p-2 text-white shadow-lg transition-colors hover:bg-red-700 disabled:cursor-wait disabled:opacity-70"
                                aria-label="Delete screenshot"
                            >
                                {#if deletingScreenshotIndex === index}
                                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                {:else}
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                {/if}
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        {:else}
            <div class="py-12 text-center text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
                <p class="mt-2">No screenshots yet. Click "Add Screenshots" to upload some.</p>
            </div>
        {/if}
    </Card>
{/if}
