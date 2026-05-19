<script lang="ts">
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

<div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Path to <span class="font-mono text-xs">{navigationTarget}</span>
        </h3>
        <Button
            type="button"
            variant="ghost"
            tone="neutral"
            size="icon-sm"
            onclick={onClearPath}
            class="rounded p-0.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            title="Clear path"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" />
            </svg>
        </Button>
    </div>

    {#if isCalculatingPath}
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Calculating path...</p>
    {:else if hasNavigationPath}
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {navigationSteps.length} steps{#if choiceCount > 0}
                &middot; {choiceCount} choice{choiceCount !== 1 ? 's' : ''}{/if}
            {#if conditionedStepCount > 0}
                &middot; {conditionedStepCount} condition{conditionedStepCount !== 1 ? 's' : ''}{/if}
        </p>
        {#if routeWordCount > 0}
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {routeWordCount.toLocaleString()} words &middot; {formatReadingTime(routeWordCount)}
            </p>
        {/if}

        {#if routePreferences.length > 0}
            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                prioritizing {routePreferences.map((pref) => formatRoutePreference(pref)).join(', ')}
            </p>
        {/if}

        <div class="mt-3 max-h-72 space-y-0.5 overflow-y-auto">
            <Button
                type="button"
                variant="ghost"
                tone="neutral"
                class="flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-xs text-gray-600 transition-colors hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800"
                onclick={() => onSelectNode(startNodeId)}
            >
                <span
                    class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-green-100 text-[10px] font-bold text-green-700 dark:bg-green-900/40 dark:text-green-400"
                    >S</span
                >
                <span class="font-mono">{startNodeId}</span>
            </Button>

            {#each navigationSteps as step (step.nodeId)}
                <div class="flex items-stretch gap-1.5">
                    <div class="flex w-4 shrink-0 justify-center">
                        <div class="w-px bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        tone={step.isChoice ? 'primary' : 'neutral'}
                        class="flex-1 rounded px-2 py-1 text-left text-xs transition-colors {step.isChoice
                            ? 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50'
                            : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'}"
                        onclick={() => onSelectNode(step.nodeId)}
                    >
                        {#if step.isChoice && step.choiceText}
                            <span class="font-medium">Select &ldquo;{step.choiceText}&rdquo;</span>
                        {:else if step.edgeType === 'jump'}
                            <span class="text-gray-400">↪</span> <span class="font-mono">{step.nodeId}</span>
                        {:else if step.edgeType === 'call'}
                            <span class="text-gray-400">↩</span> <span class="font-mono">{step.nodeId}</span>
                        {:else}
                            <span class="text-gray-400">→</span> <span class="font-mono">{step.nodeId}</span>
                        {/if}

                        {#if step.targetIsEnding}
                            <span
                                class="ml-1 rounded bg-red-100 px-1 py-0.5 text-[10px] font-medium text-red-600 dark:bg-red-900/30 dark:text-red-400"
                                >ending</span
                            >
                        {/if}

                        {#if step.condition}
                            <div class="mt-1 rounded bg-amber-50 px-1.5 py-1 text-[10px] text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                                requires: <span class="font-mono">{step.condition}</span>
                            </div>
                        {/if}
                    </Button>
                </div>
            {/each}
        </div>
    {:else if startNodeId}
        <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
            No path found from {startNodeId} to {navigationTarget}
        </p>
    {:else}
        <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">No start node found in this graph</p>
    {/if}
</div>
