<script lang="ts">
    import GridIcon from '@/components/icons/Grid.svelte';
    import ListIcon from '@/components/icons/List.svelte';
    import type { ViewMode } from '@/utils/view-mode';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';

    interface Props {
        value: ViewMode;
        onchange: (value: ViewMode) => void;
        /** Accessible name for the group, e.g. "Home layout". */
        label: string;
        class?: string;
    }

    let { value, onchange, label, class: className = '' }: Props = $props();

    const options = [
        { value: 'grid' as const, label: 'Cards', icon: GridIcon },
        { value: 'list' as const, label: 'List', icon: ListIcon },
    ];
</script>

<div
    role="group"
    aria-label={label}
    class={twMerge(clsx('inline-flex items-center gap-0.5 rounded-lg border border-border bg-surface p-0.5', className))}
>
    {#each options as option (option.value)}
        {@const Icon = option.icon}
        <button
            type="button"
            aria-pressed={value === option.value}
            onclick={() => onchange(option.value)}
            class={clsx(
                'inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 text-[13px] transition-colors',
                value === option.value ? 'bg-surface-alt font-medium text-fg' : 'text-fg-muted hover:text-fg',
            )}
        >
            <Icon class="h-4 w-4" />
            {option.label}
        </button>
    {/each}
</div>
