<script lang="ts">
    import { Button } from '@/components/ui';
    import type { RouteNode } from '@/types/route-graph';
    import { formatReadingTime } from '@/utils/route-map';

    type SelectedRouteNode = RouteNode & {
        hub_choice_count?: number;
        last_label?: string;
    };

    let {
        selectedNode,
        seenNodeIds,
        startNodeId,
        navigationTarget,
        onSelectNode,
        onNavigateTo,
    }: {
        selectedNode: SelectedRouteNode | null;
        seenNodeIds: Set<string>;
        startNodeId: string | null;
        navigationTarget: string | null;
        onSelectNode: (nodeId: string | null) => void;
        onNavigateTo: (target: string) => void;
    } = $props();
</script>

{#if selectedNode}
    {@const selectedNavigationTarget = selectedNode.last_label ?? selectedNode.id}
    <div>
        <h3 class="border-b border-gray-200 pb-3 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
            {#if selectedNode.node_type === 'choice'}
                <span class="text-amber-600 dark:text-amber-400">Choice:</span>
            {/if}
            {selectedNode.label}
        </h3>

        {#if selectedNode.parent_label}
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                in <Button
                    type="button"
                    variant="link"
                    tone="primary"
                    class="font-mono text-blue-500 hover:underline"
                    onclick={() => onSelectNode(selectedNode.parent_label ?? null)}>{selectedNode.parent_label}</Button
                >
            </p>
        {/if}

        <div class="mt-2 flex flex-wrap gap-1">
            {#if selectedNode.node_type === 'choice'}
                <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                    choice
                </span>
            {/if}
            {#if selectedNode.node_type === 'hub'}
                <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">
                    {selectedNode.hub_choice_count} routes
                </span>
            {/if}
            {#if selectedNode.is_start}
                <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    START
                </span>
            {/if}

            {#if selectedNode.is_ending}
                <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"> ending </span>
            {/if}

            {#if selectedNode.returns_to_caller}
                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                    returns to caller
                </span>
            {/if}

            {#if seenNodeIds.has(selectedNode.id)}
                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                    seen
                </span>
            {/if}
        </div>

        {#if selectedNode.word_count > 0}
            <div class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                <span>{selectedNode.word_count.toLocaleString()} words</span>
                <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                <span>{formatReadingTime(selectedNode.word_count)}</span>
            </div>
        {/if}

        {#if selectedNode.file_path}
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {selectedNode.file_path}:{selectedNode.line_number}
            </p>
        {/if}

        {#if selectedNode.choices && selectedNode.choices.length > 0}
            <div class="mt-3">
                <h4 class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">choices</h4>
                {#each selectedNode.choices as choice (choice.text)}
                    {@const relatedChanges =
                        selectedNode.variable_changes?.filter((vc: { context: string | null }) => vc.context === `menu_choice:${choice.text}`) ?? []}
                    <div class="text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-gray-800 dark:text-gray-200">{choice.text}</span>
                        {#if choice.condition}
                            <span class="ml-1 text-amber-600 dark:text-amber-400">(if {choice.condition})</span>
                        {/if}
                        {#if choice.target_label}
                            <span class="ml-1 text-blue-500">&rarr; {choice.target_label}</span>
                        {/if}
                        {#if relatedChanges.length > 0}
                            {#each relatedChanges as vc (vc.variable + vc.operation)}
                                <span class="ml-1 font-mono text-emerald-600 dark:text-emerald-400">{vc.variable} {vc.operation} {vc.value}</span>
                            {/each}
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}

        {#if selectedNode.variable_changes && selectedNode.variable_changes.length > 0}
            <div class="mt-3">
                <h4 class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">variable changes</h4>

                {#each selectedNode.variable_changes as vc, i (`${i}:${vc.variable}:${vc.operation}`)}
                    <div class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                        <span class="font-mono">{vc.variable}</span>
                        <span class="text-gray-400">{vc.operation}</span>
                        <span class="font-mono">{vc.value}</span>
                        {#if vc.condition}
                            <span class="text-gray-400">if</span>
                            <span class="font-mono text-blue-600 dark:text-blue-400">{vc.condition}</span>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}

        {#if selectedNode.outgoing_count > 0}
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {selectedNode.outgoing_count} outgoing path{selectedNode.outgoing_count > 1 ? 's' : ''}
            </p>
        {/if}

        {#if startNodeId && selectedNode.id !== startNodeId}
            <Button
                type="button"
                variant="solid"
                tone="primary"
                class="mt-3 w-full rounded-lg bg-blue-500 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-600 disabled:opacity-50"
                onclick={() => onNavigateTo(selectedNavigationTarget)}
                disabled={navigationTarget === selectedNavigationTarget}
            >
                {#if navigationTarget === selectedNavigationTarget}
                    Viewing path
                {:else}
                    Navigate here
                {/if}
            </Button>
        {/if}
    </div>
{:else}
    <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">click a node to see details</div>
{/if}
