<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
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
        class="flex w-full items-center justify-between rounded-md border border-border bg-surface-alt px-3 py-2 text-left text-sm text-fg focus:border-border-strong focus:outline-none"
        aria-expanded={open}
        aria-haspopup="listbox"
    >
        <span class="truncate">{selected ? `${prefix}${selected.name}` : placeholder}</span>
        <ChevronDownIcon class="h-4 w-4 text-fg-faint" />
    </button>
    {#if open}
        <div class="absolute z-30 mt-1 w-full rounded-lg border border-border bg-surface p-2">
            <input
                type="search"
                bind:value={search}
                placeholder={searchPlaceholder}
                class="mb-2 w-full rounded-md border border-border bg-surface-alt px-2 py-1.5 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
            />
            <div class="max-h-52 overflow-y-auto" role="listbox">
                {#if allowNone}
                    <button
                        type="button"
                        class="block w-full rounded-md px-2 py-1.5 text-left text-sm text-fg-muted hover:bg-surface-alt hover:text-fg"
                        onclick={() => choose(null)}
                    >
                        {noneLabel}
                    </button>
                {/if}
                {#each filteredItems as item (item.id)}
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm text-fg hover:bg-surface-alt {item.id ===
                        value
                            ? 'bg-surface-alt font-medium'
                            : ''}"
                        onclick={() => choose(item.id)}
                    >
                        <span class="truncate">{prefix}{item.name}</span>
                        {#if item.nsfw}<span class="text-xs text-red-500">NSFW</span>{/if}
                    </button>
                {:else}
                    <p class="px-2 py-3 text-center text-sm text-fg-muted">{emptyLabel}</p>
                {/each}
            </div>
        </div>
    {/if}
</div>
