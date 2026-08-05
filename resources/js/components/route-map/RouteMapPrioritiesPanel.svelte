<script lang="ts">
    import { Button } from '@/components/ui';
    import { formatRoutePreference } from '@/utils/route-map';
    import type { RoutePreference, RouteVariable } from '@/types/route-graph';

    let {
        routePreferences,
        routePlanningVariables,
        preferenceVariable,
        preferenceMode,
        preferenceValue,
        onMovePreference,
        onRemovePreference,
        onPreferenceVariableChange,
        onPreferenceModeChange,
        onPreferenceValueChange,
        onAddPreference,
        onClearPreferences,
    }: {
        routePreferences: RoutePreference[];
        routePlanningVariables: RouteVariable[];
        preferenceVariable: string;
        preferenceMode: RoutePreference['mode'];
        preferenceValue: string;
        onMovePreference: (fromIndex: number, toIndex: number) => void;
        onRemovePreference: (index: number) => void;
        onPreferenceVariableChange: (value: string) => void;
        onPreferenceModeChange: (value: RoutePreference['mode']) => void;
        onPreferenceValueChange: (value: string) => void;
        onAddPreference: () => void;
        onClearPreferences: () => void;
    } = $props();
</script>

<div class="mb-4 border-b border-gray-200 pb-4 dark:border-gray-700">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Route Priorities</h3>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Earlier preferences win over later ones. Path length is only used as a tiebreaker.</p>

    <div class="mt-3 space-y-2">
        {#each routePreferences as pref, index (`${pref.variable}:${pref.mode}:${pref.value ?? ''}:${index}`)}
            <div class="rounded border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-gray-700 dark:text-gray-300">
                        {formatRoutePreference(pref)}
                    </span>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            tone="neutral"
                            size="icon-sm"
                            onclick={() => onMovePreference(index, index - 1)}
                            title="Increase priority"
                        >
                            ↑
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            tone="neutral"
                            size="icon-sm"
                            onclick={() => onMovePreference(index, index + 1)}
                            title="Decrease priority"
                        >
                            ↓
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            tone="danger"
                            size="icon-sm"
                            onclick={() => onRemovePreference(index)}
                            title="Remove priority"
                        >
                            ×
                        </Button>
                    </div>
                </div>
            </div>
        {/each}
    </div>

    <div class="mt-3 space-y-2">
        <select
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
            value={preferenceVariable}
            onchange={(event) => onPreferenceVariableChange((event.currentTarget as HTMLSelectElement).value)}
        >
            <option value="">Select variable…</option>
            {#each routePlanningVariables as variable (variable.name)}
                <option value={variable.name}>{variable.name}</option>
            {/each}
        </select>

        <select
            class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
            value={preferenceMode}
            onchange={(event) => onPreferenceModeChange((event.currentTarget as HTMLSelectElement).value as RoutePreference['mode'])}
        >
            <option value="maximize">Maximize value</option>
            <option value="minimize">Minimize value</option>
            <option value="equals">Match exact value</option>
        </select>

        {#if preferenceMode === 'equals'}
            <input
                type="text"
                class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                placeholder="Desired value"
                value={preferenceValue}
                oninput={(event) => onPreferenceValueChange(event.currentTarget.value)}
            />
        {/if}

        <div class="flex gap-2">
            <Button
                type="button"
                variant="solid"
                tone="success"
                size="xs"
                class="flex-1"
                onclick={onAddPreference}
                disabled={!preferenceVariable.trim() || (preferenceMode === 'equals' && !preferenceValue.trim())}
            >
                Add priority
            </Button>

            {#if routePreferences.length > 0}
                <Button type="button" variant="outline" tone="neutral" size="xs" onclick={onClearPreferences}>Clear</Button>
            {/if}
        </div>
    </div>
</div>
