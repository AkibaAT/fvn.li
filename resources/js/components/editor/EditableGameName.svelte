<script lang="ts">
    import { onMount, untrack } from 'svelte';
    import { Button } from '@/components/ui';
    import { updateGameName } from '@/api/game-content';

    interface Game {
        id: number;
        effective_name: string;
        custom_name?: string | null;
        has_custom_page?: boolean;
        [key: string]: any;
    }

    interface Props {
        game: Game;
        class?: string;
        onNameUpdate?: (newName: string) => void;
        previewingVisitorView?: boolean;
        previewName?: string;
    }

    let { game, class: className = '', onNameUpdate, previewingVisitorView = false, previewName = '' }: Props = $props();

    const gameId = $derived(game.id);
    const name = $derived(game.custom_name ?? game.effective_name);
    const canEdit = true;

    let isEditing = $state(false);
    let editName = $state(untrack(() => name));
    let displayName = $state(untrack(() => name));
    let isSaving = $state(false);
    let saveStatus = $state<'idle' | 'saving' | 'saved' | 'error'>('idle');

    $effect(() => {
        displayName = name;
        editName = name;
    });

    // Listen for name revert events
    onMount(() => {
        const handleNameReverted = (event: Event) => {
            const { effectiveName } = (event as CustomEvent).detail;
            if (effectiveName) {
                displayName = effectiveName;
                editName = effectiveName;
                if (onNameUpdate) {
                    onNameUpdate(effectiveName);
                }
            }
        };

        window.addEventListener('name-reverted', handleNameReverted);
        return () => {
            window.removeEventListener('name-reverted', handleNameReverted);
        };
    });

    function handleEdit() {
        if (!canEdit) return;
        isEditing = true;
        editName = displayName;
    }

    function handleCancel() {
        isEditing = false;
        editName = displayName;
        saveStatus = 'idle';
    }

    async function handleSave() {
        if (!canEdit) return;

        const trimmedName = editName.trim();
        if (!trimmedName) {
            alert('Name cannot be empty');
            return;
        }

        isSaving = true;
        saveStatus = 'saving';

        try {
            const data = await updateGameName(gameId, trimmedName);

            isEditing = false;
            saveStatus = 'saved';

            const updatedName = data.name || trimmedName;
            displayName = updatedName;

            if (onNameUpdate) {
                onNameUpdate(updatedName);
            }

            setTimeout(() => {
                saveStatus = 'idle';
            }, 3000);
        } catch (error) {
            console.error('Save error:', error);
            saveStatus = 'error';
            alert('Failed to save name. Please try again.');
        } finally {
            isSaving = false;
        }
    }

    function handleKeyPress(e: KeyboardEvent) {
        if (e.key === 'Enter') {
            handleSave();
        } else if (e.key === 'Escape') {
            handleCancel();
        }
    }

    function focusOnMount(node: HTMLElement) {
        node.focus();
    }

    const renderedName = $derived(previewingVisitorView ? previewName : displayName);
</script>

<div class="relative flex w-full min-w-0 flex-wrap items-center gap-2 {className}">
    {#if isEditing}
        <input
            type="text"
            bind:value={editName}
            onkeydown={handleKeyPress}
            disabled={isSaving}
            class="min-w-0 flex-1 rounded border-2 border-blue-300 bg-white px-2 py-1 text-3xl font-bold tracking-tight break-words text-gray-900 focus:border-blue-500 focus:outline-none dark:bg-gray-700 dark:text-white"
            use:focusOnMount
            maxlength={255}
        />
        <Button
            type="button"
            variant="solid"
            tone="success"
            onclick={handleSave}
            disabled={isSaving}
            loading={isSaving}
            size="sm"
            class="whitespace-nowrap"
        >
            {isSaving ? 'Saving...' : 'Save'}
        </Button>
        <Button type="button" variant="solid" tone="neutral" onclick={handleCancel} disabled={isSaving} size="sm" class="whitespace-nowrap">
            Cancel
        </Button>
        {#if saveStatus === 'saved'}
            <span class="text-sm whitespace-nowrap text-green-600">Saved</span>
        {/if}
        {#if saveStatus === 'error'}
            <span class="text-sm whitespace-nowrap text-red-600">Error</span>
        {/if}
    {:else}
        <h1 class="min-w-0 text-3xl font-bold tracking-tight break-words text-gray-900 dark:text-white">
            {renderedName}
        </h1>
        {#if canEdit && !previewingVisitorView}
            <Button
                type="button"
                variant="solid"
                tone="primary"
                size="xs"
                onclick={handleEdit}
                class="whitespace-nowrap opacity-0 shadow-md transition-opacity group-hover:opacity-100"
                title="Edit name"
            >
                Edit
            </Button>
        {/if}
    {/if}
</div>
