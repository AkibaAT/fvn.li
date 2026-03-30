<script lang="ts" generics="T extends { id: string | number }">
    import type { Attachment } from 'svelte/attachments';
    import type { Snippet } from 'svelte';
    import { DragDropProvider, DragOverlay, KeyboardSensor, PointerSensor } from '@dnd-kit-svelte/svelte';
    import { useSortable } from '@dnd-kit-svelte/svelte/sortable';
    import { move } from '@dnd-kit/helpers';
    import { defaultSortableTransition } from '@dnd-kit/dom/sortable';

    interface Props {
        items: T[];
        onReorder: (items: T[]) => void | Promise<void>;
        children: Snippet<[T, number, boolean, Attachment<HTMLElement>]>;
        overlay?: Snippet<[T]>;
        class?: string;
        disabled?: boolean;
    }

    let { items, onReorder, children: renderChild, overlay, class: className = '', disabled = false }: Props = $props();

    let activeId = $state<string | number | null>(null);
    let activeItem = $derived(activeId !== null ? (items.find((item) => item.id === activeId) ?? null) : null);

    function handleDragStart(event: any) {
        activeId = event.operation.source.id;
    }

    function handleDragEnd(event: any) {
        activeId = null;

        if (event.canceled) return;

        const reorderedItems = move(items, event);

        if (reorderedItems === items) return;

        onReorder(reorderedItems);
    }
</script>

<DragDropProvider sensors={[KeyboardSensor, PointerSensor]} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
    <div class={className}>
        {#each items as item, index (item.id)}
            {@const sortable = useSortable({
                id: item.id,
                index: () => index,
                feedback: 'move',
                transition: defaultSortableTransition,
                disabled,
            })}

            <div {@attach sortable.ref} class="transition-transform duration-200" class:opacity-30={sortable.isDragging.current}>
                {@render renderChild(item, index, !disabled, sortable.handleRef)}
            </div>
        {/each}
    </div>

    <DragOverlay>
        {#snippet children(_source)}
            {#if activeItem && overlay}
                {@render overlay(activeItem)}
            {:else if activeItem}
                <div class="rounded-lg bg-white text-gray-700 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:text-gray-300">
                    {@render renderChild(activeItem, -1, false, () => {})}
                </div>
            {/if}
        {/snippet}
    </DragOverlay>
</DragDropProvider>
