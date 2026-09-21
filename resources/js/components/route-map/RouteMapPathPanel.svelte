<script lang="ts">
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { Button } from '@/components/ui';
    import { formatReadingTime, formatRoutePreference } from '@/utils/route-map';
    import type { NavigationStep, RoutePreference } from '@/types/route-graph';

    let {
        navigationTarget,
        isCalculatingPath,
        hasNavigationPath,
        navigationSteps,
        choiceCount,
        conditionedStepCount,
        routeWordCount,
        routePreferences,
        startNodeId,
        onClearPath,
        onSelectNode,
    }: {
        navigationTarget: string;
        isCalculatingPath: boolean;
        hasNavigationPath: boolean;
        navigationSteps: NavigationStep[];
        choiceCount: number;
        conditionedStepCount: number;
        routeWordCount: number;
        routePreferences: RoutePreference[];
        startNodeId: string | null;
        onClearPath: () => void;
        onSelectNode: (nodeId: string | null) => void;
    } = $props();
</script>

<div class="mb-4 border-b border-border pb-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-fg">
            Path to <span class="font-mono text-xs">{navigationTarget}</span>
        </h3>
        <Button type="button" variant="ghost" tone="neutral" size="icon-sm" onclick={onClearPath} title="Clear path">
            <XMarkIcon class="h-4 w-4" />
        </Button>
    </div>

    {#if isCalculatingPath}
        <p class="mt-1 text-xs text-fg-faint">Calculating path...</p>
    {:else if hasNavigationPath}
        <p class="mt-1 text-xs text-fg-faint">
            {navigationSteps.length} steps{#if choiceCount > 0}
                &middot; {choiceCount} choice{choiceCount !== 1 ? 's' : ''}{/if}
            {#if conditionedStepCount > 0}
                &middot; {conditionedStepCount} condition{conditionedStepCount !== 1 ? 's' : ''}{/if}
        </p>
        {#if routeWordCount > 0}
            <p class="mt-1 text-xs text-fg-faint">
                {routeWordCount.toLocaleString()} words &middot; {formatReadingTime(routeWordCount)}
            </p>
        {/if}

        {#if routePreferences.length > 0}
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
                prioritizing {routePreferences.map((pref) => formatRoutePreference(pref)).join(', ')}
            </p>
        {/if}

        <div class="mt-3 max-h-72 space-y-0.5 overflow-y-auto">
            <Button
                type="button"
                variant="ghost"
                tone="neutral"
                size="xs"
                class="w-full justify-start text-left"
                onclick={() => onSelectNode(startNodeId)}
            >
                <span
                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-[3px] border border-border-strong text-[10px] font-bold text-green-700 dark:text-green-400"
                    >S</span
                >
                <span class="font-mono">{startNodeId}</span>
            </Button>

            {#each navigationSteps as step (step.nodeId)}
                <div class="flex items-stretch gap-1.5">
                    <div class="flex w-4 shrink-0 justify-center">
                        <div class="w-px bg-border"></div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        tone={step.isChoice ? 'primary' : 'neutral'}
                        size="xs"
                        class="flex-1 justify-start text-left"
                        onclick={() => onSelectNode(step.nodeId)}
                    >
                        {#if step.isChoice && step.choiceText}
                            <span class="font-medium">Select &ldquo;{step.choiceText}&rdquo;</span>
                        {:else if step.edgeType === 'jump'}
                            <span class="text-fg-faint">↪</span> <span class="font-mono">{step.nodeId}</span>
                        {:else if step.edgeType === 'call'}
                            <span class="text-fg-faint">↩</span> <span class="font-mono">{step.nodeId}</span>
                        {:else}
                            <span class="text-fg-faint">→</span> <span class="font-mono">{step.nodeId}</span>
                        {/if}

                        {#if step.targetIsEnding}
                            <span
                                class="ml-1 rounded-[3px] border border-border-strong px-1 py-0.5 text-[10px] font-medium text-red-700 dark:text-red-400"
                                >ending</span
                            >
                        {/if}

                        {#if step.condition}
                            <div class="mt-1 rounded-[3px] border border-border-strong px-1.5 py-1 text-[10px] text-amber-700 dark:text-amber-300">
                                requires: <span class="font-mono">{step.condition}</span>
                            </div>
                        {/if}
                    </Button>
                </div>
            {/each}
        </div>
    {:else if startNodeId}
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
            No path found from {startNodeId} to {navigationTarget}
        </p>
    {:else}
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">No start node found in this graph</p>
    {/if}
</div>
