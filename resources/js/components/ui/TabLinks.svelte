<script lang="ts">
    import { shouldIntercept } from '@inertiajs/core';

    export interface TabLinkItem<T extends string = string> {
        key: T;
        label: string;
        href: string;
        count?: number;
    }

    interface Props<T extends string> {
        tabs: TabLinkItem<T>[];
        active: T;
        onSelect: (tab: T) => void;
    }

    let { tabs, active, onSelect }: Props<string> = $props();
</script>

<div class="flex flex-wrap gap-2 border-b border-border">
    {#each tabs as tab (tab.key)}
        <a
            href={tab.href}
            onclick={(e: MouseEvent) => {
                if (!shouldIntercept(e)) return;
                e.preventDefault();
                onSelect(tab.key);
            }}
            class="rounded-t-md px-4 py-2 text-sm font-medium transition-colors {active === tab.key
                ? 'border-b-2 border-accent text-fg'
                : 'text-fg-muted hover:text-fg'}"
        >
            {tab.count === undefined ? tab.label : `${tab.label} (${tab.count})`}
        </a>
    {/each}
</div>
