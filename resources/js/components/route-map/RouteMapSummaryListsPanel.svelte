<script lang="ts">
    import { Button } from '@/components/ui';
    import type { RouteVariable } from '@/types/route-graph';

    let {
        endings,
        variables,
        onSelectEnding,
    }: {
        endings: string[];
        variables: RouteVariable[];
        onSelectEnding: (ending: string) => void;
    } = $props();
</script>

{#if endings.length > 0}
    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
        <h3 class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">endings ({endings.length})</h3>

        <div class="flex flex-wrap gap-1">
            {#each endings as ending (ending)}
                <Button
                    type="button"
                    variant="soft"
                    tone="danger"
                    class="rounded bg-red-50 px-2 py-1 text-xs text-red-600 transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/40"
                    onclick={() => onSelectEnding(ending)}
                >
                    {ending}
                </Button>
            {/each}
        </div>
    </div>
{/if}

{#if variables.length > 0}
    <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
        <h3 class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">variables ({variables.length})</h3>

        <div class="space-y-1">
            {#each variables as v (v.name)}
                <div class="text-xs text-gray-600 dark:text-gray-400">
                    <span class="font-mono font-medium">{v.name}</span>

                    {#if v.default_value}
                        <span class="text-gray-400"> = {v.default_value}</span>
                    {/if}

                    <span class="text-gray-400"> ({v.change_count} changes)</span>
                </div>
            {/each}
        </div>
    </div>
{/if}
