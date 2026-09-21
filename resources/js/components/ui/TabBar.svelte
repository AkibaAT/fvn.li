<script lang="ts">
    import { Button } from '@/components/ui';

    export interface TabItem<T extends string = string> {
        id: T;
        label: string;
    }

    interface Props<T extends string> {
        tabs: TabItem<T>[];
        active: T;
        onSelect: (tab: T) => void;
        ariaLabel?: string;
    }

    let { tabs, active, onSelect, ariaLabel = 'Tabs' }: Props<string> = $props();
</script>

<div class="mb-6 border-b border-border">
    <div class="-mb-px flex flex-wrap gap-x-6" aria-label={ariaLabel} role="tablist">
        {#each tabs as tab (tab.id)}
            <Button
                type="button"
                variant="link"
                tone="info"
                onclick={() => onSelect(tab.id)}
                class="border-b-2 px-1 py-3 text-sm font-medium transition-colors {active === tab.id
                    ? 'border-accent text-fg'
                    : 'border-transparent text-fg-muted hover:border-border-strong hover:text-fg'}"
                aria-selected={active === tab.id}
                role="tab"
            >
                {tab.label}
            </Button>
        {/each}
    </div>
</div>
