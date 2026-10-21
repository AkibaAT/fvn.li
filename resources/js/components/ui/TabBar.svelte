<script lang="ts">
    import { shouldIntercept } from '@inertiajs/core';
    import { Button } from '@/components/ui';
    import { cn } from '@/utils/cn';
    import { rovingFocus } from '@/utils/roving-focus';

    export interface TabItem<T extends string = string> {
        id: T;
        label: string;
        count?: number;
        href?: string;
    }

    interface Props<T extends string> {
        tabs: TabItem<T>[];
        active: T;
        onSelect: (tab: T) => void;
        ariaLabel?: string;
        class?: string;
    }

    let { tabs, active, onSelect, ariaLabel = 'Tabs', class: className = '' }: Props<string> = $props();

    function handleClick(event: MouseEvent, tab: TabItem) {
        if (tab.href) {
            if (!shouldIntercept(event)) return;
            event.preventDefault();
        }
        onSelect(tab.id);
    }
</script>

<div class={cn('mb-6 border-b border-border', className)}>
    <div class="-mb-px flex flex-wrap gap-x-6" aria-label={ariaLabel} role="tablist" {@attach rovingFocus('[role="tab"]')}>
        {#each tabs as tab (tab.id)}
            <Button
                type={tab.href ? undefined : 'button'}
                href={tab.href}
                inertia={false}
                variant="link"
                tone="info"
                onclick={(event: MouseEvent) => handleClick(event, tab)}
                class="relative border-b-2 px-1 py-3 text-sm font-medium transition-colors {active === tab.id
                    ? 'accent-underline border-transparent text-fg'
                    : 'border-transparent text-fg-muted hover:border-border-strong hover:text-fg'}"
                aria-selected={active === tab.id}
                role="tab"
            >
                {tab.count === undefined ? tab.label : `${tab.label} (${tab.count})`}
            </Button>
        {/each}
    </div>
</div>
