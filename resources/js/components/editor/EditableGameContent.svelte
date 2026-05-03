<script lang="ts">
    import { onMount, untrack } from 'svelte';
    import TinyMCEEditor from './TinyMCEEditor.svelte';
    import http from '@/utils/http';

    interface Game {
        id: number;
        custom_description?: string | null;
        effective_description?: string;
        full_description?: string;
        description?: string;
        has_custom_page?: boolean;
        [key: string]: any;
    }

    interface Props {
        game: Game;
        class?: string;
        onContentUpdate?: (newContent: string) => void;
        onViewModeUpdate?: (data: {
            view_mode?: 'custom' | 'original';
            effective_name?: string | null;
            effective_description?: string | null;
            effective_screenshots?: unknown[];
        }) => void;
        previewingVisitorView?: boolean;
        previewContent?: string;
        onPreviewingVisitorViewChange?: (previewing: boolean) => void;
        controlsTarget?: HTMLElement | null;
    }

    let {
        game,
        class: className = '',
        onContentUpdate,
        onViewModeUpdate,
        previewingVisitorView = false,
        previewContent = '',
        onPreviewingVisitorViewChange,
        controlsTarget = null,
    }: Props = $props();

    const gameId = $derived(game.id);
    const content = $derived(game.custom_description ?? game.effective_description ?? game.full_description ?? game.description ?? '');
    const canEdit = true;
    const hasCustomPage = $derived(game.has_custom_page ?? false);

    let isEditing = $state(false);
    let editContent = $state(untrack(() => content));
    let displayContent = $state(untrack(() => content));
    let isSaving = $state(false);
    let saveStatus = $state<'idle' | 'saving' | 'saved' | 'error'>('idle');
    let isReverting = $state(false);
    let showRevertMenu = $state(false);
    let viewMode = $state<'custom' | 'original'>('original');
    let isLoadingViewMode = $state(true);
    let controlsEl = $state<HTMLElement | undefined>(undefined);

    // Teleport edit controls to external container when provided
    $effect(() => {
        if (controlsTarget && controlsEl) {
            controlsTarget.appendChild(controlsEl);
            return () => {
                // Move back on cleanup if element still exists
                if (controlsEl?.parentNode === controlsTarget) {
                    controlsTarget.removeChild(controlsEl);
                }
            };
        }
    });
    let showEditorLoading = $state(false);

    // Update display content when parent content changes
    $effect(() => {
        displayContent = content;
        editContent = content;
    });

    // Fetch current view mode when component mounts (only if user can edit)
    onMount(() => {
        if (hasCustomPage && canEdit) {
            fetchViewMode();
        } else {
            isLoadingViewMode = false;
        }
    });

    // Close revert menu when clicking outside
    $effect(() => {
        if (showRevertMenu) {
            const handleClickOutside = (event: MouseEvent) => {
                const target = event.target as Element;
                if (!target.closest('.revert-menu-container')) {
                    showRevertMenu = false;
                }
            };

            document.addEventListener('click', handleClickOutside);
            return () => document.removeEventListener('click', handleClickOutside);
        }
    });

    async function fetchViewMode() {
        try {
            const response = await http.get(route('browser-api.games.content.view', { game: gameId }));
            if (response.data.success) {
                viewMode = response.data.data.current_view_mode || 'original';
            }
        } catch (error) {
            console.error('Failed to fetch view mode:', error);
        } finally {
            isLoadingViewMode = false;
        }
    }

    async function handleViewModeChange(newMode: 'custom' | 'original') {
        try {
            const response = await http.put(route('browser-api.games.content.view-mode', { game: gameId }), {
                view_mode: newMode,
            });

            if (response.data.success) {
                viewMode = newMode;
                onViewModeUpdate?.(response.data.data || {});
            }
        } catch (error) {
            console.error('Failed to update view mode:', error);
            alert('Failed to update view mode. Please try again.');
        }
    }

    function handleEdit() {
        if (!canEdit) return;
        isEditing = true;
        editContent = displayContent;
        showEditorLoading = true;
    }

    function handleCancel() {
        isEditing = false;
        editContent = displayContent;
        saveStatus = 'idle';
    }

    async function handleSave() {
        if (!canEdit) return;

        isSaving = true;
        saveStatus = 'saving';

        try {
            const response = await http.put(route('browser-api.games.content.update', { game: gameId }), {
                content: editContent,
            });

            if (response.data.success) {
                isEditing = false;
                saveStatus = 'saved';
                displayContent = editContent;

                if (onContentUpdate) {
                    onContentUpdate(editContent);
                }

                setTimeout(() => {
                    saveStatus = 'idle';
                }, 3000);
            } else {
                throw new Error(response.data.message || 'Failed to save');
            }
        } catch (error) {
            console.error('Save error:', error);
            saveStatus = 'error';
        } finally {
            isSaving = false;
        }
    }

    async function handleRevert(options?: { name?: boolean; screenshots?: boolean; thumbnail?: boolean }) {
        if (!canEdit) return;

        const { name = false, screenshots = false, thumbnail = false } = options || {};

        let confirmMessage = 'Are you sure you want to revert to the original itch.io content?';
        if (name && !screenshots && !thumbnail) {
            confirmMessage += ' This will replace your custom name with the current one from itch.io.';
        } else if (screenshots && !name && !thumbnail) {
            confirmMessage += ' This will replace your custom screenshots with the current ones from itch.io.';
        } else if (thumbnail && !name && !screenshots) {
            confirmMessage += ' This will replace your custom thumbnail with the current one from itch.io.';
        } else if (name && screenshots && thumbnail) {
            confirmMessage +=
                ' This will replace all your custom content (name, description, screenshots, and thumbnail) with the current versions from itch.io.';
        } else {
            confirmMessage += ' This will replace your custom description with the current version from itch.io.';
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        isReverting = true;
        saveStatus = 'saving';

        try {
            const response = await http.post(route('browser-api.games.content.revert', { game: gameId }), {
                revert_name: name,
                revert_screenshots: screenshots,
                revert_thumbnail: thumbnail,
            });

            if (response.data.success) {
                saveStatus = 'saved';

                // Full revert disables custom page entirely — reload to reflect new state
                if (response.data.data.has_custom_page === false) {
                    window.location.reload();
                    return;
                }

                const revertedContent = response.data.data.content;
                displayContent = revertedContent;
                editContent = revertedContent;
                isEditing = false;

                if (onContentUpdate) {
                    onContentUpdate(revertedContent);
                }

                if (name && response.data.data.effective_name) {
                    const event = new CustomEvent('name-reverted', {
                        detail: { effectiveName: response.data.data.effective_name },
                    });
                    window.dispatchEvent(event);
                }

                if (screenshots && response.data.data.screenshots) {
                    window.location.reload();
                }

                if (thumbnail && response.data.data.thumbnail_url) {
                    const event = new CustomEvent('thumbnail-reverted', {
                        detail: { thumbnailUrl: response.data.data.thumbnail_url },
                    });
                    window.dispatchEvent(event);
                    setTimeout(() => window.location.reload(), 1000);
                }

                setTimeout(() => {
                    saveStatus = 'idle';
                }, 3000);
            } else {
                throw new Error(response.data.message || 'Failed to revert');
            }
        } catch (error) {
            console.error('Revert error:', error);
            saveStatus = 'error';
            alert('Failed to revert content. Please try again.');
        } finally {
            isReverting = false;
        }
    }

    function handleEditorUpdate(newContent: string) {
        editContent = newContent;
    }

    const renderedContent = $derived(previewingVisitorView ? previewContent : displayContent);
</script>

<div class="{controlsTarget ? '' : 'relative'} {className}">
    <!-- Edit controls rendered at top level -->
    {#if canEdit && !isEditing}
        <div class="flex items-center gap-2 {controlsTarget ? '' : 'absolute top-2 right-2'}" bind:this={controlsEl}>
            {#if hasCustomPage && !isLoadingViewMode}
                <div class="mr-2 flex items-center gap-1">
                    <span class="mr-2 text-xs text-gray-600 dark:text-gray-400">Visitors see:</span>
                    <button
                        onclick={() => handleViewModeChange('original')}
                        class="rounded px-2 py-1 text-xs transition-colors {viewMode === 'original'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                        title="Show visitors original itch.io content"
                    >
                        itch.io
                    </button>
                    <button
                        onclick={() => handleViewModeChange('custom')}
                        class="rounded px-2 py-1 text-xs transition-colors {viewMode === 'custom'
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                        title="Show visitors custom content"
                    >
                        Custom
                    </button>
                </div>
            {/if}
            {#if hasCustomPage && onPreviewingVisitorViewChange}
                <button
                    onclick={() => onPreviewingVisitorViewChange(!previewingVisitorView)}
                    class="rounded bg-gray-700 px-2 py-1 text-xs text-white shadow-md hover:bg-gray-800 dark:bg-gray-600 dark:hover:bg-gray-500"
                >
                    {previewingVisitorView ? 'Exit preview' : 'Preview visitor view'}
                </button>
            {/if}
            {#if hasCustomPage}
                <div class="revert-menu-container relative">
                    <button
                        onclick={() => {
                            showRevertMenu = !showRevertMenu;
                        }}
                        disabled={isReverting}
                        class="rounded bg-orange-600 px-2 py-1 text-xs text-white shadow-md hover:bg-orange-700 disabled:opacity-50"
                        title="Revert to original itch.io content"
                    >
                        {isReverting ? 'Reverting...' : 'Revert'}
                    </button>
                    {#if showRevertMenu}
                        <div
                            class="absolute top-full right-0 z-50 mt-1 w-48 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                        >
                            <div class="py-1">
                                <button
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ name: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Revert Name
                                </button>
                                <button
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert();
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Revert Description Only
                                </button>
                                <button
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ screenshots: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Revert Screenshots
                                </button>
                                <button
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ thumbnail: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Revert Thumbnail
                                </button>
                                <button
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ name: true, screenshots: true, thumbnail: true });
                                    }}
                                    class="block w-full border-t border-gray-200 px-4 py-2 text-left text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Revert Everything
                                </button>
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
            {#if !previewingVisitorView}
                <button onclick={handleEdit} class="rounded bg-blue-600 px-2 py-1 text-xs text-white shadow-md hover:bg-blue-700"> Edit </button>
            {/if}
        </div>
    {/if}

    <div
        class="game_description prose max-w-none text-gray-600 dark:text-gray-300 dark:prose-invert {isEditing
            ? 'rounded border-2 border-blue-300'
            : ''}"
    >
        {#if isEditing}
            {#if showEditorLoading}
                <div class="flex h-64 items-center justify-center text-sm text-gray-500 dark:text-gray-400">Loading editor...</div>
            {/if}
            <TinyMCEEditor
                content={editContent}
                onUpdate={handleEditorUpdate}
                {gameId}
                editable={true}
                placeholder="Start writing your game description..."
                height={400}
                onReady={() => {
                    showEditorLoading = false;
                }}
            />
        {:else}
            <div class="inner_column size_very_large family_grandstander" id="inner_column">
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html renderedContent || ''}
            </div>
        {/if}
    </div>

    {#if isEditing}
        <div class="mt-4 flex items-center gap-2">
            <button
                onclick={handleSave}
                disabled={isSaving || isReverting}
                class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
            >
                {isSaving ? 'Saving...' : 'Save'}
            </button>

            <button
                onclick={handleCancel}
                disabled={isSaving || isReverting}
                class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50"
            >
                Cancel
            </button>

            <button
                onclick={() => handleRevert({ name: true, screenshots: true, thumbnail: true })}
                disabled={isSaving || isReverting}
                class="rounded bg-orange-600 px-4 py-2 text-white hover:bg-orange-700 disabled:opacity-50"
                title="Revert everything to original itch.io content"
            >
                {isReverting ? 'Reverting...' : 'Revert All'}
            </button>

            {#if saveStatus === 'saved'}
                <span class="text-sm text-green-600">Saved</span>
            {/if}

            {#if saveStatus === 'error'}
                <span class="text-sm text-red-600">Error saving</span>
            {/if}
        </div>
    {/if}
</div>
