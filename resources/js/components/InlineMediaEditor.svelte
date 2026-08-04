<script lang="ts">
    import { Button } from '@/components/ui';
    import { authenticatedFetch } from '@/utils/csrf';
    import { toast } from '@/utils/toast';
    import type { OptimizedScreenshotVariants } from '@/constants/screenshot-variants';
    import { gameCoverAltText, gameScreenshotAltText } from '@/utils/imageAltText';
    interface Screenshot {
        id?: number;
        url: string;
        thumbnail_url: string;
        optimized?: OptimizedScreenshotVariants;
    }

    interface Props {
        gameSlug: string;
        thumbnail: string | null;
        screenshots: Screenshot[];
        canEdit: boolean;
        isAdmin?: boolean;
        gameName?: string;
        onUpdate: (thumbnail: string | null, screenshots: Screenshot[]) => void;
    }

    let { gameSlug, thumbnail, screenshots, canEdit, isAdmin = false, gameName, onUpdate }: Props = $props();

    let uploadingThumbnail = $state(false);
    let uploadingScreenshots = $state(false);
    let dragOver = $state(false);
    let isEditing = $state(false);

    async function handleThumbnailUpload(file: File) {
        if (!file.type.startsWith('image/')) {
            toast.error('Please upload an image file');
            return;
        }

        uploadingThumbnail = true;
        const formData = new FormData();
        formData.append('thumbnail', file);

        try {
            const res = await authenticatedFetch(route('browser-api.my-games.thumbnail.update', { game: gameSlug }), {
                method: 'POST',
                body: formData,
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                toast.error(data?.message || 'Failed to upload thumbnail');
                return;
            }

            onUpdate(data.thumbnail_url, screenshots);
            toast.success('Thumbnail updated successfully');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload thumbnail';
            toast.error(errorMessage);
        } finally {
            uploadingThumbnail = false;
        }
    }

    async function handleThumbnailDelete() {
        try {
            const res = await authenticatedFetch(route('browser-api.my-games.thumbnail.delete', { game: gameSlug }), {
                method: 'DELETE',
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                toast.error(data?.message || 'Failed to delete thumbnail');
                return;
            }

            onUpdate(null, screenshots);
            toast.success('Thumbnail deleted successfully');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete thumbnail';
            toast.error(errorMessage);
        }
    }

    async function handleScreenshotUpload(files: FileList) {
        const imageFiles = Array.from(files).filter((file) => file.type.startsWith('image/'));
        if (imageFiles.length === 0) {
            toast.error('Please upload image files');
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

            onUpdate(thumbnail, data.screenshots || []);
            toast.success('Screenshots uploaded successfully');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to upload screenshots';
            toast.error(errorMessage);
        } finally {
            uploadingScreenshots = false;
        }
    }

    async function handleScreenshotDelete(index: number) {
        try {
            const res = await authenticatedFetch(route('browser-api.my-games.screenshots.delete', { game: gameSlug, index }), { method: 'DELETE' });
            const data = await res.json().catch(() => ({}));

            if (!res.ok || data?.success === false) {
                toast.error(data?.message || 'Failed to delete screenshot');
                return;
            }

            onUpdate(thumbnail, data.screenshots || []);
            toast.success('Screenshot deleted successfully');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Failed to delete screenshot';
            toast.error(errorMessage);
        }
    }

    function handleDragOver(e: DragEvent) {
        e.preventDefault();
        dragOver = true;
    }

    function handleDragLeave(e: DragEvent) {
        e.preventDefault();
        dragOver = false;
    }

    function handleDrop(e: DragEvent, type: 'thumbnail' | 'screenshots') {
        e.preventDefault();
        dragOver = false;
        const files = e.dataTransfer?.files;
        if (!files) return;
        if (type === 'thumbnail' && files.length === 1) {
            handleThumbnailUpload(files[0]);
        } else if (type === 'screenshots' && files.length > 0) {
            handleScreenshotUpload(files);
        }
    }
</script>

{#if canEdit}
    <div class="space-y-6">
        <!-- Edit Toggle -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Media Management</h3>
                {#if isAdmin}
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                        Admin Access
                    </span>
                {/if}
            </div>
            <Button
                type="button"
                variant={isEditing ? 'solid' : 'solid'}
                tone={isEditing ? 'neutral' : 'primary'}
                onclick={() => (isEditing = !isEditing)}
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors {isEditing
                    ? 'bg-gray-600 text-white hover:bg-gray-700'
                    : 'bg-blue-600 text-white hover:bg-blue-700'}"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d={isEditing
                            ? 'M6 18L18 6M6 6l12 12'
                            : 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'}
                    />
                </svg>
                {isEditing ? 'Cancel' : 'Edit Media'}
            </Button>
        </div>

        {#if isEditing}
            <!-- Thumbnail Management -->
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <h4 class="text-md mb-4 font-medium text-gray-900 dark:text-white">Thumbnail</h4>
                <div class="flex items-start gap-6">
                    <div
                        class="relative rounded-lg border-2 border-dashed p-4 transition-colors {dragOver
                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                            : 'border-gray-300 hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500'}"
                        ondragover={handleDragOver}
                        ondragleave={handleDragLeave}
                        ondrop={(e) => handleDrop(e, 'thumbnail')}
                        role="presentation"
                    >
                        {#if thumbnail}
                            <div class="relative">
                                <img src={thumbnail} alt={gameCoverAltText(gameName)} class="h-32 w-32 rounded-lg object-cover" />
                                <Button
                                    type="button"
                                    variant="solid"
                                    tone="danger"
                                    size="icon-sm"
                                    onclick={handleThumbnailDelete}
                                    disabled={uploadingThumbnail}
                                    class="absolute -top-2 -right-2 rounded-full bg-red-600 p-1 text-white transition-colors hover:bg-red-700"
                                    ariaLabel="Delete thumbnail"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </Button>
                            </div>
                        {:else}
                            <div class="flex h-32 w-32 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                        {/if}
                    </div>

                    <div class="flex-1">
                        <label for="thumbnail-upload" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Upload Thumbnail</label>
                        <input
                            id="thumbnail-upload"
                            type="file"
                            accept="image/*"
                            onchange={(e) => {
                                const input = e.target as HTMLInputElement;
                                if (input.files?.[0]) handleThumbnailUpload(input.files[0]);
                            }}
                            disabled={uploadingThumbnail}
                            class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-700 disabled:opacity-50"
                        />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Recommended: 16:9 aspect ratio, max 5MB. Supports JPG, PNG, WebP.</p>
                        {#if uploadingThumbnail}
                            <div class="mt-2 flex items-center text-sm text-blue-600 dark:text-blue-400">
                                <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                Uploading thumbnail...
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Screenshots Management -->
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-4 flex items-center justify-between">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white">Screenshots</h4>
                    <label
                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Add Screenshots</span>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            onchange={(e) => {
                                const input = e.target as HTMLInputElement;
                                if (input.files) handleScreenshotUpload(input.files);
                            }}
                            disabled={uploadingScreenshots}
                            class="hidden"
                        />
                    </label>
                </div>

                <div
                    class="mb-4 rounded-lg border-2 border-dashed p-6 transition-colors {dragOver
                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                        : 'border-gray-300 hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500'}"
                    ondragover={handleDragOver}
                    ondragleave={handleDragLeave}
                    ondrop={(e) => handleDrop(e, 'screenshots')}
                    role="presentation"
                >
                    {#if screenshots.length > 0}
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            {#each screenshots as screenshot, index (index)}
                                <div class="group relative">
                                    <img
                                        src={screenshot.url}
                                        alt={gameScreenshotAltText(gameName, index, screenshots.length)}
                                        class="h-32 w-full rounded-lg object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="solid"
                                        tone="danger"
                                        size="icon-md"
                                        onclick={() => handleScreenshotDelete(index)}
                                        ariaLabel={`Delete screenshot ${index + 1}`}
                                        class="absolute top-2 right-2 rounded-full bg-red-600 p-2 text-white shadow-lg transition-colors hover:bg-red-700"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            />
                                        </svg>
                                    </Button>
                                    <div class="bg-opacity-75 absolute bottom-2 left-2 rounded bg-black px-2 py-1 text-xs text-white">
                                        #{index + 1}
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="text-center">
                            <p class="mb-2 text-gray-500 dark:text-gray-400">No screenshots uploaded yet</p>
                            <p class="text-sm text-gray-400 dark:text-gray-500">Drag and drop images here or click "Add Screenshots" to upload</p>
                        </div>
                    {/if}
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Recommended: 16:9 aspect ratio, max 10MB per image. Supports JPG, PNG, WebP. Multiple files can be uploaded at once.
                </p>

                {#if uploadingScreenshots}
                    <div class="mt-4 flex items-center text-sm text-blue-600 dark:text-blue-400">
                        <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Uploading screenshots...
                    </div>
                {/if}
            </div>
        {/if}
    </div>
{/if}
