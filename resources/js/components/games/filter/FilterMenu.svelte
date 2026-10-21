<script lang="ts">
    import ChevronRightIcon from '@/components/icons/ChevronRight.svelte';
    import { rovingFocus } from '@/utils/roving-focus';
    import type { FilterFieldId } from './filter-fields';

    export interface FilterFieldOption {
        id: FilterFieldId;
        label: string;
        group: string;
        activeCount: number;
    }

    interface Props {
        fields: FilterFieldOption[];
        onSelect: (field: FilterFieldId) => void;
        focusField?: FilterFieldId | null;
    }

    let { fields, onSelect, focusField = null }: Props = $props();

    const groups = $derived([...new Set(fields.map((field) => field.group))]);

    let menuEl = $state<HTMLElement>();

    $effect(() => {
        const items = menuEl?.querySelectorAll<HTMLElement>('[role="menuitem"]');
        if (!items?.length) return;
        const target = Array.from(items).find((item) => item.dataset.field === focusField) ?? items[0];
        target.focus();
    });
</script>

<div
    bind:this={menuEl}
    role="menu"
    aria-label="Available filters"
    class="max-h-[calc(100vh-12rem)] overflow-y-auto p-1.5"
    {@attach rovingFocus('[role="menuitem"]')}
>
    {#each groups as group (group)}
        <div role="presentation" class="px-2 pt-2 pb-1 text-2xs font-semibold tracking-wider text-fg-muted uppercase first:pt-1">
            {group}
        </div>
        {#each fields.filter((field) => field.group === group) as field (field.id)}
            <button
                type="button"
                role="menuitem"
                data-field={field.id}
                aria-label={field.activeCount > 0 ? `${field.label}, ${field.activeCount} active` : field.label}
                onclick={() => onSelect(field.id)}
                class="popover-row-hover flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span class="truncate">{field.label}</span>
                    {#if field.activeCount > 0}
                        <span class="rounded-full border border-accent-fg bg-accent px-1.5 text-2xs font-medium text-on-accent"
                            >{field.activeCount}</span
                        >
                    {/if}
                </span>
                <ChevronRightIcon class="h-3.5 w-3.5 flex-shrink-0 text-fg-muted" />
            </button>
        {/each}
    {/each}
</div>
