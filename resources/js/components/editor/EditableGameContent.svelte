<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import { onMount, untrack } from 'svelte';
    import TinyMCEEditor from './TinyMCEEditor.svelte';
    import { Button } from '@/components/ui';
    import { fetchGameContentView, revertGameContent, updateGameContent, updateGameViewMode } from '@/api/game-content';

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
    let viewModeQueue = Promise.resolve();
    const refreshGame = () => refreshPage(['game', 'metaTags']);

    $effect(() => {
        displayContent = content;
        editContent = content;
    });

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
            const data = await fetchGameContentView(gameId);
            viewMode = data.current_view_mode || 'original';
        } catch (error) {
            console.error('Failed to fetch view mode:', error);
        } finally {
            isLoadingViewMode = false;
        }
    }

    function handleViewModeChange(newMode: 'custom' | 'original') {
        viewModeQueue = viewModeQueue.then(() => saveViewMode(newMode));
        return viewModeQueue;
    }

    async function saveViewMode(newMode: 'custom' | 'original') {
        try {
            const data = await updateGameViewMode(gameId, newMode);
            if (!(await refreshGame())) return;
            viewMode = newMode;
            onViewModeUpdate?.(data);
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
        if (!canEdit || isSaving) return;

        isSaving = true;
        saveStatus = 'saving';

        try {
            const data = await updateGameContent(gameId, editContent);
            if (!(await refreshGame())) return;

            isEditing = false;
            saveStatus = 'saved';
            const savedContent = data.content ?? editContent;
            displayContent = savedContent;

            if (onContentUpdate) {
                onContentUpdate(savedContent);
            }

            setTimeout(() => {
                saveStatus = 'idle';
            }, 3000);
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
            const data = await revertGameContent(gameId, {
                revert_name: name,
                revert_screenshots: screenshots,
                revert_thumbnail: thumbnail,
            });
            if (!(await refreshGame())) return;

            saveStatus = 'saved';

            isEditing = false;
            onContentUpdate?.(data.content);

            setTimeout(() => {
                saveStatus = 'idle';
            }, 3000);
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
    {#if canEdit && !isEditing}
        <div class="flex flex-wrap items-center gap-2 {controlsTarget ? '' : 'absolute top-2 right-2'}" bind:this={controlsEl}>
            {#if hasCustomPage && !isLoadingViewMode}
                <div class="mr-2 flex items-center gap-1">
                    <span class="mr-2 text-xs text-fg-muted">Visitors see:</span>
                    <Button
                        type="button"
                        variant={viewMode === 'original' ? 'solid' : 'soft'}
                        tone={viewMode === 'original' ? 'primary' : 'neutral'}
                        size="xs"
                        onclick={() => handleViewModeChange('original')}
                        title="Show visitors original itch.io content"
                    >
                        itch.io
                    </Button>
                    <Button
                        type="button"
                        variant={viewMode === 'custom' ? 'solid' : 'soft'}
                        tone={viewMode === 'custom' ? 'primary' : 'neutral'}
                        size="xs"
                        onclick={() => handleViewModeChange('custom')}
                        title="Show visitors custom content"
                    >
                        Custom
                    </Button>
                </div>
            {/if}
            {#if hasCustomPage && onPreviewingVisitorViewChange}
                <Button type="button" variant="solid" tone="neutral" size="xs" onclick={() => onPreviewingVisitorViewChange(!previewingVisitorView)}>
                    {previewingVisitorView ? 'Exit preview' : 'Preview visitor view'}
                </Button>
            {/if}
            {#if hasCustomPage}
                <div class="revert-menu-container relative">
                    <Button
                        type="button"
                        variant="solid"
                        tone="warning"
                        size="xs"
                        onclick={() => {
                            showRevertMenu = !showRevertMenu;
                        }}
                        disabled={isReverting}
                        loading={isReverting}
                        title="Revert to original itch.io content"
                    >
                        {isReverting ? 'Reverting...' : 'Revert'}
                    </Button>
                    {#if showRevertMenu}
                        <div class="absolute top-full right-0 z-50 mt-1 w-48 rounded-lg border border-border bg-surface">
                            <div class="py-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="neutral"
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ name: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm"
                                >
                                    Revert Name
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="neutral"
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert();
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm"
                                >
                                    Revert Description Only
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="neutral"
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ screenshots: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm"
                                >
                                    Revert Screenshots
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="neutral"
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ thumbnail: true });
                                    }}
                                    class="block w-full px-4 py-2 text-left text-sm"
                                >
                                    Revert Thumbnail
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    tone="warning"
                                    onclick={() => {
                                        showRevertMenu = false;
                                        handleRevert({ name: true, screenshots: true, thumbnail: true });
                                    }}
                                    class="block w-full border-t border-border px-4 py-2 text-left text-sm font-semibold"
                                >
                                    Revert Everything
                                </Button>
                            </div>
                        </div>
                    {/if}
                </div>
            {/if}
            {#if !previewingVisitorView}
                <Button type="button" variant="solid" tone="primary" size="xs" onclick={handleEdit}>Edit</Button>
            {/if}
        </div>
    {/if}

    <div class="game_description prose max-w-none text-fg-muted dark:prose-invert {isEditing ? 'rounded-md border border-border-strong' : ''}">
        {#if isEditing}
            {#if showEditorLoading}
                <div class="flex h-64 items-center justify-center text-sm text-fg-muted">Loading editor...</div>
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
            <Button type="button" variant="solid" tone="success" onclick={handleSave} disabled={isSaving || isReverting} loading={isSaving}>
                {isSaving ? 'Saving...' : 'Save'}
            </Button>

            <Button type="button" variant="solid" tone="neutral" onclick={handleCancel} disabled={isSaving || isReverting}>Cancel</Button>

            <Button
                type="button"
                variant="solid"
                tone="warning"
                onclick={() => handleRevert({ name: true, screenshots: true, thumbnail: true })}
                disabled={isSaving || isReverting}
                loading={isReverting}
                title="Revert everything to original itch.io content"
            >
                {isReverting ? 'Reverting...' : 'Revert All'}
            </Button>

            {#if saveStatus === 'saved'}
                <span class="text-sm text-green-600">Saved</span>
            {/if}

            {#if saveStatus === 'error'}
                <span class="text-sm text-red-600">Error saving</span>
            {/if}
        </div>
    {/if}
</div>
