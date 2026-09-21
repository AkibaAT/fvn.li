<script lang="ts">
    import ChevronRightIcon from '@/components/icons/ChevronRight.svelte';

    export interface FilterFieldOption {
        id: string;
        label: string;
        group: string;
        activeCount: number;
    }

    interface Props {
        fields: FilterFieldOption[];
        onSelect: (field: string) => void;
    }

    let { fields, onSelect }: Props = $props();

    const groups = $derived([...new Set(fields.map((field) => field.group))]);
</script>

<div role="menu" aria-label="Available filters" class="max-h-[calc(100vh-12rem)] overflow-y-auto p-1.5">
    {#each groups as group (group)}
        <div class="px-2 pt-2 pb-1 text-2xs font-semibold tracking-wider text-fg-muted uppercase first:pt-1">
            {group}
        </div>
        {#each fields.filter((field) => field.group === group) as field (field.id)}
            <button
                type="button"
                role="menuitem"
                onclick={() => onSelect(field.id)}
                class="popover-row-hover flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span class="truncate">{field.label}</span>
                    {#if field.activeCount > 0}
                        <span class="rounded-full border border-accent px-1.5 text-2xs font-medium text-accent-fg">{field.activeCount}</span>
                    {/if}
                </span>
                <ChevronRightIcon class="h-3.5 w-3.5 flex-shrink-0 text-fg-muted" />
            </button>
        {/each}
    {/each}
</div>
