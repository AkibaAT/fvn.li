<script lang="ts">
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import { Badge, Button, Popover, TextInput } from '@/components/ui';

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
    const selected = $derived(items.find((item) => item.id === value));
    const filteredItems = $derived(items.filter((item) => item.name.toLowerCase().includes(search.trim().toLowerCase())));

    function choose(itemId: string | null): void {
        onselect(itemId);
        open = false;
        search = '';
    }
</script>

<Popover bind:open onClose={() => (search = '')}>
    <button
        {id}
        type="button"
        onclick={() => (open = !open)}
        class="flex w-full items-center justify-between rounded-md border border-border-input bg-surface-alt px-3 py-2 text-left text-sm text-fg focus:border-fg-muted focus:outline-none"
        aria-expanded={open}
        aria-haspopup="listbox"
    >
        <span class="truncate">{selected ? `${prefix}${selected.name}` : placeholder}</span>
        <ChevronDownIcon class="h-4 w-4 text-fg-faint" />
    </button>
    {#if open}
        <div class="popover-elevated absolute z-30 mt-1 w-full rounded-lg border border-border-strong p-2">
            <TextInput type="search" bind:value={search} placeholder={searchPlaceholder} fieldClass="mb-2" />
            <div class="max-h-52 overflow-y-auto" role="listbox">
                {#if allowNone}
                    <Button type="button" variant="ghost" size="sm" class="w-full justify-start px-2 text-left" onclick={() => choose(null)}>
                        {noneLabel}
                    </Button>
                {/if}
                {#each filteredItems as item (item.id)}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="w-full justify-between px-2 text-left {item.id === value ? 'bg-surface-alt font-medium' : ''}"
                        onclick={() => choose(item.id)}
                    >
                        <span class="truncate">{prefix}{item.name}</span>
                        {#if item.nsfw}<Badge tone="danger" size="sm">NSFW</Badge>{/if}
                    </Button>
                {:else}
                    <p class="px-2 py-3 text-center text-sm text-fg-muted">{emptyLabel}</p>
                {/each}
            </div>
        </div>
    {/if}
</Popover>
