<script lang="ts">
    import { fetchUserLists, addGameToList } from '@/hooks/api';
    import type { VnList } from '@/hooks/api';

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
    let dialogEl = $state<HTMLDialogElement | undefined>(undefined);
    let closeBtnEl = $state<HTMLButtonElement | undefined>(undefined);
    let openerEl: HTMLElement | null = null;

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

    const getListTypeColor = (type: string) => {
        switch (type) {
            case 'reading':
                return 'blue';
            case 'completed':
                return 'green';
            case 'plan_to_read':
                return 'yellow';
            case 'on_hold':
                return 'orange';
            case 'dropped':
                return 'red';
            default:
                return 'gray';
        }
    };

    $effect(() => {
        if (!dialogEl) return;
        if (isOpen) {
            openerEl = (document.activeElement as HTMLElement) || null;
            if (!dialogEl.open) dialogEl.showModal();
            requestAnimationFrame(() => closeBtnEl?.focus());
        } else if (dialogEl.open) {
            dialogEl.close();
        }
    });

    $effect(() => {
        if (!dialogEl) return;
        const handleClose = () => {
            isOpen = false;
            selectedListId = null;
            openerEl?.focus?.();
            openerEl = null;
        };
        dialogEl.addEventListener('close', handleClose);
        return () => dialogEl?.removeEventListener('close', handleClose);
    });
</script>

{#if lists.length > 0}
    <div class="relative">
        <button
            onclick={() => (isOpen = !isOpen)}
            class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50 {className}"
        >
            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add to List
        </button>

        <dialog
            bind:this={dialogEl}
            aria-modal="true"
            aria-labelledby="add-to-list-title"
            class="m-auto w-80 rounded-lg border border-gray-200 bg-white p-0 shadow-lg backdrop:bg-black/20 dark:border-gray-700 dark:bg-gray-800"
            onclick={(e: MouseEvent) => {
                if (e.target === e.currentTarget) isOpen = false;
            }}
        >
            <div class="p-4">
                <h3 id="add-to-list-title" class="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                    Add "{gameName}" to a list
                </h3>
                <div class="max-h-60 space-y-2 overflow-y-auto">
                    {#each lists as list (list.id)}
                        {@const color = getListTypeColor(list.type)}
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
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-semibold bg-{color}-100 text-{color}-800 dark:bg-{color}-900/20 dark:text-{color}-400"
                                        >
                                            {list.type.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase())}
                                        </span>
                                    {/if}
                                </div>
                                {#if list.is_default}
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Default list</span>
                                {/if}
                            </div>
                        </label>
                    {/each}
                </div>
                <div class="mt-4 flex justify-end space-x-3 border-t border-gray-200 pt-4 dark:border-gray-600">
                    <button
                        bind:this={closeBtnEl}
                        onclick={() => {
                            isOpen = false;
                            selectedListId = null;
                        }}
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        aria-label="Close dialog">Cancel</button
                    >
                    <button
                        onclick={handleAddToList}
                        disabled={!selectedListId || isAdding}
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >{isAdding ? 'Adding...' : 'Add to List'}</button
                    >
                </div>
            </div>
        </dialog>
    </div>
{/if}
