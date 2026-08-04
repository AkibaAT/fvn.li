<script lang="ts">
    import { onMount } from 'svelte';

    export type PickerItem = { id: string; name: string; nsfw?: boolean; color?: number };

    let {
        items,
        value = null,
        onselect,
        id,
        placeholder = 'Select an item',
        searchPlaceholder = 'Filter items...',
        emptyLabel = 'No items found',
        prefix = '#',
        allowNone = false,
        noneLabel = 'Use default',
    }: {
        items: PickerItem[];
        value?: string | null;
        onselect: (id: string | null) => void;
        id?: string;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyLabel?: string;
        prefix?: string;
        allowNone?: boolean;
        noneLabel?: string;
    } = $props();

    let open = $state(false);
    let search = $state('');
    let root: HTMLDivElement;
    const selected = $derived(items.find((item) => item.id === value));
    const filteredItems = $derived(items.filter((item) => item.name.toLowerCase().includes(search.trim().toLowerCase())));

    function choose(itemId: string | null): void {
        onselect(itemId);
        open = false;
        search = '';
    }

    onMount(() => {
        const handleOutside = (event: MouseEvent) => {
            if (!root.contains(event.target as Node)) {
                open = false;
                search = '';
            }
        };
        document.addEventListener('mousedown', handleOutside);
        return () => document.removeEventListener('mousedown', handleOutside);
    });
</script>

<div class="relative" bind:this={root}>
    <button
        {id}
        type="button"
        onclick={() => (open = !open)}
        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        aria-expanded={open}
        aria-haspopup="listbox"
    >
        <span class="truncate">{selected ? `${prefix}${selected.name}` : placeholder}</span>
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"
            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg
        >
    </button>
    {#if open}
        <div class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-600 dark:bg-gray-800">
            <input
                type="search"
                bind:value={search}
                placeholder={searchPlaceholder}
                class="mb-2 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
            <div class="max-h-52 overflow-y-auto" role="listbox">
                {#if allowNone}
                    <button
                        type="button"
                        class="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                        onclick={() => choose(null)}
                    >
                        {noneLabel}
                    </button>
                {/if}
                {#each filteredItems as item (item.id)}
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {item.id ===
                        value
                            ? 'bg-indigo-50 dark:bg-indigo-900/30'
                            : ''}"
                        onclick={() => choose(item.id)}
                    >
                        <span class="truncate">{prefix}{item.name}</span>
                        {#if item.nsfw}<span class="text-xs text-red-500">NSFW</span>{/if}
                    </button>
                {:else}
                    <p class="px-2 py-3 text-center text-sm text-gray-500">{emptyLabel}</p>
                {/each}
            </div>
        </div>
    {/if}
</div>
