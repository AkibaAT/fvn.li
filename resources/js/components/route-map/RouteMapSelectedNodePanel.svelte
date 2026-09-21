<script lang="ts">
    import { Badge, Button } from '@/components/ui';
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
        <h3 class="border-b border-border pb-3 text-sm font-semibold text-fg">
            {#if selectedNode.node_type === 'choice'}
                <span class="text-amber-700 dark:text-amber-400">Choice:</span>
            {/if}
            {selectedNode.label}
        </h3>

        {#if selectedNode.parent_label}
            <p class="mt-1 text-xs text-fg-faint">
                in <Button
                    type="button"
                    variant="link"
                    tone="primary"
                    class="font-mono"
                    onclick={() => onSelectNode(selectedNode.parent_label ?? null)}>{selectedNode.parent_label}</Button
                >
            </p>
        {/if}

        <div class="mt-2 flex flex-wrap gap-1">
            {#if selectedNode.node_type === 'choice'}
                <Badge tone="warning">choice</Badge>
            {/if}
            {#if selectedNode.node_type === 'hub'}
                <Badge tone="purple">{selectedNode.hub_choice_count} routes</Badge>
            {/if}
            {#if selectedNode.is_start}
                <Badge tone="success">START</Badge>
            {/if}

            {#if selectedNode.is_ending}
                <Badge tone="danger">ending</Badge>
            {/if}

            {#if selectedNode.returns_to_caller}
                <Badge tone="info">returns to caller</Badge>
            {/if}

            {#if seenNodeIds.has(selectedNode.id)}
                <Badge tone="success">seen</Badge>
            {/if}
        </div>

        {#if selectedNode.word_count > 0}
            <div class="mt-2 flex items-center gap-2 text-xs text-fg-muted">
                <span>{selectedNode.word_count.toLocaleString()} words</span>
                <span class="text-fg-faint">&middot;</span>
                <span>{formatReadingTime(selectedNode.word_count)}</span>
            </div>
        {/if}

        {#if selectedNode.file_path}
            <p class="mt-2 text-xs text-fg-faint">
                {selectedNode.file_path}:{selectedNode.line_number}
            </p>
        {/if}

        {#if selectedNode.choices && selectedNode.choices.length > 0}
            <div class="mt-3">
                <h4 class="mb-1 text-xs font-medium text-fg-muted">choices</h4>
                {#each selectedNode.choices as choice (choice.text)}
                    {@const relatedChanges =
                        selectedNode.variable_changes?.filter((vc: { context: string | null }) => vc.context === `menu_choice:${choice.text}`) ?? []}
                    <div class="text-xs text-fg-muted">
                        <span class="font-medium text-fg">{choice.text}</span>
                        {#if choice.condition}
                            <span class="ml-1 text-amber-700 dark:text-amber-400">(if {choice.condition})</span>
                        {/if}
                        {#if choice.target_label}
                            <span class="ml-1 text-fg-faint">&rarr; {choice.target_label}</span>
                        {/if}
                        {#if relatedChanges.length > 0}
                            {#each relatedChanges as vc (vc.variable + vc.operation)}
                                <span class="ml-1 font-mono text-green-700 dark:text-green-400">{vc.variable} {vc.operation} {vc.value}</span>
                            {/each}
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}

        {#if selectedNode.variable_changes && selectedNode.variable_changes.length > 0}
            <div class="mt-3">
                <h4 class="mb-1 text-xs font-medium text-fg-muted">variable changes</h4>

                {#each selectedNode.variable_changes as vc, i (`${i}:${vc.variable}:${vc.operation}`)}
                    <div class="flex items-center gap-1 text-xs text-fg-muted">
                        <span class="font-mono">{vc.variable}</span>
                        <span class="text-fg-faint">{vc.operation}</span>
                        <span class="font-mono">{vc.value}</span>
                        {#if vc.condition}
                            <span class="text-fg-faint">if</span>
                            <span class="font-mono text-fg">{vc.condition}</span>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}

        {#if selectedNode.outgoing_count > 0}
            <p class="mt-2 text-xs text-fg-faint">
                {selectedNode.outgoing_count} outgoing path{selectedNode.outgoing_count > 1 ? 's' : ''}
            </p>
        {/if}

        {#if startNodeId && selectedNode.id !== startNodeId}
            <Button
                type="button"
                variant="solid"
                tone="primary"
                size="xs"
                class="mt-3 w-full"
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
    <div class="py-8 text-center text-sm text-fg-faint">click a node to see details</div>
{/if}
