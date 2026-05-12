<script lang="ts">
    import { fetchUserLists, addGameToList } from '@/hooks/api';
    import type { VnList } from '@/hooks/api';
    import { Badge, Button, Dialog } from '@/components/ui';
    import { formatListType, listTypeTone } from '@/components/ui/tones';

    let {
        gameId,
        gameName,
        class: className = '',
    }: {
        gameId: number;
        gameName: string;
        class?: string;
    } = $props();

    let lists = $state<VnList[]>([]);
    let isOpen = $state(false);
    let selectedListId = $state<number | null>(null);
    let isAdding = $state(false);

    async function loadLists() {
        try {
            lists = await fetchUserLists();
        } catch {
            lists = [];
        }
    }

    $effect(() => {
        loadLists();
    });

    const handleAddToList = async () => {
        if (!selectedListId || isAdding) return;
        isAdding = true;
        try {
            const result = await addGameToList({ gameId, listId: selectedListId });
            if (result.success) {
                document.dispatchEvent(
                    new CustomEvent('show-toast', {
                        detail: { message: `Added "${gameName}" to list successfully!`, type: 'success' },
                    }),
                );
                isOpen = false;
                selectedListId = null;
            } else {
                throw new Error(result.message || 'Failed to add game to list');
            }
        } catch (error) {
            console.error('Error adding game to list:', error);
            document.dispatchEvent(
                new CustomEvent('show-toast', {
                    detail: { message: error instanceof Error ? error.message : 'Failed to add game to list', type: 'error' },
                }),
            );
        } finally {
            isAdding = false;
        }
    };

    function closeDialog() {
        isOpen = false;
        selectedListId = null;
    }
</script>

{#if lists.length > 0}
    <div class="relative">
        <Button onclick={() => (isOpen = !isOpen)} class={className}>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add to List
        </Button>

        {#snippet footer()}
            <Button onclick={closeDialog} variant="soft" tone="neutral" aria-label="Close dialog">Cancel</Button>
            <Button onclick={handleAddToList} disabled={!selectedListId || isAdding}>{isAdding ? 'Adding...' : 'Add to List'}</Button>
        {/snippet}

        <Dialog open={isOpen} onClose={closeDialog} title={`Add "${gameName}" to a list`} size="sm" {footer}>
            <div class="max-h-60 space-y-2 overflow-y-auto">
                {#each lists as list (list.id)}
                    <label
                        class="flex cursor-pointer items-center rounded-lg border border-gray-200 p-3 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        <input
                            type="radio"
                            name="list"
                            value={list.id}
                            checked={selectedListId === list.id}
                            onchange={(e) => (selectedListId = Number((e.target as HTMLInputElement).value))}
                            class="mr-3 text-blue-600 focus:ring-blue-500"
                        />
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900 dark:text-white">{list.name}</span>
                                {#if !list.is_default}
                                    <Badge tone={listTypeTone(list.type)} size="sm">{formatListType(list.type)}</Badge>
                                {/if}
                            </div>
                            {#if list.is_default}
                                <span class="text-xs text-gray-500 dark:text-gray-400">Default list</span>
                            {/if}
                        </div>
                    </label>
                {/each}
            </div>
        </Dialog>
    </div>
{/if}
