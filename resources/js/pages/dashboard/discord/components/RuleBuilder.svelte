<script lang="ts">
    import CheckIcon from '@/components/icons/Check.svelte';
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import TrashIcon from '@/components/icons/Trash.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import ChannelPicker from './ChannelPicker.svelte';
    interface RuleCondition {
        field: string;
        operator: string;
        value: string | string[] | boolean;
    }

    interface RoutingRule {
        id: string;
        name: string;
        enabled: boolean;
        priority: number;
        conditions: RuleCondition[];
        action: { type: 'ignore' | 'route'; channel_id?: string };
    }

    interface DiscordChannel {
        id: string;
        name: string;
        nsfw?: boolean;
    }

    interface RuleOption {
        value: string | boolean;
        label: string;
    }

    interface RuleFieldMetadata {
        type: 'enum' | 'multi_enum' | 'boolean';
        operators: string[];
        options: RuleOption[];
    }

    interface Props {
        rules: RoutingRule[];
        channels: DiscordChannel[];
        fieldMetadata: Record<string, RuleFieldMetadata>;
        onchange: (rules: RoutingRule[]) => void;
    }

    let { rules, channels, fieldMetadata, onchange }: Props = $props();

    const uid = $props.id();

    let expandedRule = $state<string | number | null>(null);
    let valuePickerKey = $state<string | null>(null);
    let valueSearch = $state('');
    let valuePickerEl: HTMLDivElement | undefined = $state();

    const fieldOptions = [
        { value: 'notification_type', label: 'Notification Type' },
        { value: 'status', label: 'Status' },
        { value: 'source_language', label: 'Source Language' },
        { value: 'tags', label: 'Tags' },
        { value: 'content_type', label: 'Content Type' },
        { value: 'platform', label: 'Platform' },
        { value: 'is_nsfw', label: 'NSFW' },
        { value: 'is_paid', label: 'Paid' },
        { value: 'developer', label: 'Developer' },
    ];

    const operatorOptions = [
        { value: 'equals', label: 'Equals' },
        { value: 'not_equals', label: 'Not Equals' },
        { value: 'contains', label: 'Contains' },
        { value: 'not_contains', label: 'Not Contains' },
        { value: 'contains_any', label: 'Contains Any' },
        { value: 'in', label: 'In' },
        { value: 'not_in', label: 'Not In' },
    ];

    function generateId(): string {
        return `rule_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
    }

    function addRule() {
        const newRule: RoutingRule = {
            id: generateId(),
            name: `Rule ${(rules?.length || 0) + 1}`,
            enabled: true,
            priority: (rules?.length || 0) + 1,
            conditions: [{ field: 'notification_type', operator: 'equals', value: 'new_game' }],
            action: { type: 'route' },
        };
        const updated = [...(rules || []), newRule];
        onchange(updated);
        expandedRule = newRule.id!;
    }

    function removeRule(ruleId: string | number | undefined) {
        if (!ruleId) return;
        const updated = rules.filter((r) => r.id !== ruleId);
        onchange(updated);
        if (expandedRule === ruleId) expandedRule = null;
    }

    function updateRule(ruleId: string | number | undefined, changes: Partial<RoutingRule>) {
        const updated = rules.map((r) => (r.id === ruleId ? { ...r, ...changes } : r));
        onchange(updated);
    }

    function toggleRule(ruleId: string | number | undefined) {
        const rule = rules.find((r) => r.id === ruleId);
        if (rule) updateRule(ruleId, { enabled: !rule.enabled });
    }

    function addCondition(ruleId: string | number | undefined) {
        const rule = rules.find((r) => r.id === ruleId);
        if (!rule) return;
        updateRule(ruleId, {
            conditions: [...rule.conditions, getDefaultCondition()],
        });
    }

    function removeCondition(ruleId: string | number | undefined, index: number) {
        const rule = rules.find((r) => r.id === ruleId);
        if (!rule) return;
        const conditions = rule.conditions.filter((_, i) => i !== index);
        updateRule(ruleId, { conditions });
    }

    function updateCondition(ruleId: string | number | undefined, index: number, changes: Partial<RuleCondition>) {
        const rule = rules.find((r) => r.id === ruleId);
        if (!rule) return;
        const conditions = rule.conditions.map((c, i) => (i === index ? { ...c, ...changes } : c));
        updateRule(ruleId, { conditions });
    }

    function getDefaultCondition(field = 'notification_type'): RuleCondition {
        const metadata = fieldMetadata[field];
        const operator = metadata?.operators?.[0] || 'equals';
        const firstOption = metadata?.options?.[0];

        let value: RuleCondition['value'] = '';
        if (metadata?.type === 'multi_enum') {
            value = firstOption ? [String(firstOption.value)] : [];
        } else if (metadata?.type === 'boolean') {
            value = firstOption ? Boolean(firstOption.value) : false;
        } else if (firstOption) {
            value = String(firstOption.value);
        }

        return { field, operator, value };
    }

    function handleFieldChange(ruleId: string | number | undefined, index: number, field: string) {
        const next = getDefaultCondition(field);
        updateCondition(ruleId, index, next);
    }

    function getOperatorOptions(field: string) {
        const allowed = fieldMetadata[field]?.operators;
        return allowed?.length ? operatorOptions.filter((option) => allowed.includes(option.value)) : operatorOptions;
    }

    function getValueOptions(field: string): RuleOption[] {
        return fieldMetadata[field]?.options ?? [];
    }

    function getFieldType(field: string): RuleFieldMetadata['type'] | 'text' {
        return fieldMetadata[field]?.type ?? 'text';
    }

    function toggleMultiValue(ruleId: string | number | undefined, index: number, rawValue: string) {
        const rule = rules.find((r) => r.id === ruleId);
        const condition = rule?.conditions[index];
        if (!condition) return;

        const current = Array.isArray(condition.value) ? condition.value.map(String) : [];
        const next = current.includes(rawValue) ? current.filter((value) => value !== rawValue) : [...current, rawValue];
        updateCondition(ruleId, index, { value: next });
    }

    function summarizeConditions(conditions: RuleCondition[]): string {
        if (!conditions.length) return 'No conditions';
        return conditions
            .map((c) => {
                const field = fieldOptions.find((f) => f.value === c.field)?.label || c.field;
                const op = operatorOptions.find((o) => o.value === c.operator)?.label || c.operator;
                const value = Array.isArray(c.value)
                    ? c.value.join(', ')
                    : typeof c.value === 'boolean'
                      ? c.value
                          ? 'Yes'
                          : 'No'
                      : c.value || '...';
                return `${field} ${op} "${value}"`;
            })
            .join(' AND ');
    }

    $effect(() => {
        if (valuePickerKey === null) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (valuePickerEl && !valuePickerEl.contains(event.target as Node)) {
                valuePickerKey = null;
                valueSearch = '';
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    });
</script>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Routing Rules</h3>
        <button
            onclick={addRule}
            class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
        >
            <PlusIcon class="h-4 w-4" />
            Add Rule
        </button>
    </div>

    {#if !rules || rules.length === 0}
        <div class="rounded-lg border border-dashed border-gray-300 py-8 text-center dark:border-gray-600">
            <p class="text-sm text-gray-500 dark:text-gray-400">No routing rules configured. All notifications will go to the default channel.</p>
        </div>
    {:else}
        <div class="space-y-3">
            {#each rules as rule (rule.id)}
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-3 p-4">
                        <button
                            onclick={() => toggleRule(rule.id)}
                            class="relative h-6 w-11 shrink-0 rounded-full transition-colors {rule.enabled
                                ? 'bg-blue-600'
                                : 'bg-gray-300 dark:bg-gray-600'}"
                            role="switch"
                            aria-checked={rule.enabled}
                            aria-label="Enable rule {rule.name}"
                        >
                            <span
                                class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform {rule.enabled
                                    ? 'left-[22px]'
                                    : 'left-0.5'}"
                            ></span>
                        </button>

                        <button
                            type="button"
                            onclick={() => (expandedRule = expandedRule === rule.id ? null : rule.id)}
                            class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 text-left"
                            aria-expanded={expandedRule === rule.id}
                            aria-controls="{uid}-rule-{rule.id}"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="font-medium text-gray-900 dark:text-white">{rule.name}</span>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Priority: {rule.priority}</span>
                            </span>

                            <span class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">
                                {summarizeConditions(rule.conditions)}
                            </span>

                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                {rule.action.type === 'ignore'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                    : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'}"
                            >
                                {rule.action.type === 'ignore'
                                    ? 'Ignore'
                                    : '#' + (channels.find((c) => c.id === rule.action.channel_id)?.name || 'default')}
                            </span>
                        </button>

                        <button
                            onclick={() => {
                                if (confirm('Delete this rule?')) removeRule(rule.id);
                            }}
                            aria-label="Delete rule {rule.name}"
                            class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                        >
                            <TrashIcon class="h-4 w-4" />
                        </button>

                        <ChevronDownIcon class="h-5 w-5 shrink-0 text-gray-400 transition-transform {expandedRule === rule.id ? 'rotate-180' : ''}" />
                    </div>

                    {#if expandedRule === rule.id}
                        <div id="{uid}-rule-{rule.id}" class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-700">
                            <div>
                                <label for="{uid}-name-{rule.id}" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                                    >Rule Name</label
                                >
                                <input
                                    id="{uid}-name-{rule.id}"
                                    type="text"
                                    value={rule.name}
                                    oninput={(e) => updateRule(rule.id, { name: (e.target as HTMLInputElement).value })}
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="{uid}-priority-{rule.id}" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
                                        >Priority (lower = first)</label
                                    >
                                    <input
                                        id="{uid}-priority-{rule.id}"
                                        type="number"
                                        value={rule.priority}
                                        oninput={(e) => updateRule(rule.id, { priority: parseInt((e.target as HTMLInputElement).value) || 0 })}
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <span id="{uid}-conditions-{rule.id}" class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        >Conditions (all must match)</span
                                    >
                                    <button
                                        onclick={() => addCondition(rule.id)}
                                        class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">+ Add Condition</button
                                    >
                                </div>
                                <div class="space-y-2" role="group" aria-labelledby="{uid}-conditions-{rule.id}">
                                    {#each rule.conditions as condition, cIndex (rule.id + '-' + cIndex)}
                                        <div class="flex items-center gap-2">
                                            <select
                                                aria-label="Condition {cIndex + 1} field"
                                                value={condition.field}
                                                onchange={(e) => handleFieldChange(rule.id, cIndex, (e.target as HTMLSelectElement).value)}
                                                class="rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            >
                                                {#each fieldOptions as opt (opt.value)}
                                                    <option value={opt.value}>{opt.label}</option>
                                                {/each}
                                            </select>
                                            <select
                                                aria-label="Condition {cIndex + 1} operator"
                                                value={condition.operator}
                                                onchange={(e) =>
                                                    updateCondition(rule.id, cIndex, { operator: (e.target as HTMLSelectElement).value })}
                                                class="rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            >
                                                {#each getOperatorOptions(condition.field) as opt (opt.value)}
                                                    <option value={opt.value}>{opt.label}</option>
                                                {/each}
                                            </select>
                                            {#if getFieldType(condition.field) === 'multi_enum'}
                                                <div class="relative min-w-0 flex-1" bind:this={valuePickerEl}>
                                                    <button
                                                        type="button"
                                                        onclick={() => {
                                                            valuePickerKey =
                                                                valuePickerKey === `${rule.id}:${cIndex}` ? null : `${rule.id}:${cIndex}`;
                                                            if (valuePickerKey === null) valueSearch = '';
                                                        }}
                                                        class="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-2 py-1.5 text-left text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                    >
                                                        <span class="truncate">
                                                            {Array.isArray(condition.value) && condition.value.length > 0
                                                                ? condition.value.join(', ')
                                                                : 'Select values'}
                                                        </span>
                                                        <ChevronDownIcon class="h-4 w-4 shrink-0" />
                                                    </button>
                                                    {#if valuePickerKey === `${rule.id}:${cIndex}`}
                                                        <div
                                                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800"
                                                        >
                                                            <div class="p-2">
                                                                <input
                                                                    type="text"
                                                                    bind:value={valueSearch}
                                                                    aria-label="Filter values"
                                                                    placeholder="Type to filter values..."
                                                                    class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                                />
                                                            </div>
                                                            <div class="max-h-56 overflow-y-auto py-1">
                                                                {#each getValueOptions(condition.field).filter((option) => option.label
                                                                        .toLowerCase()
                                                                        .includes(valueSearch.trim().toLowerCase())) as option (String(option.value))}
                                                                    <button
                                                                        type="button"
                                                                        onclick={() => toggleMultiValue(rule.id, cIndex, String(option.value))}
                                                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                                                                    >
                                                                        <span class="truncate">{option.label}</span>
                                                                        {#if Array.isArray(condition.value) && condition.value
                                                                                .map(String)
                                                                                .includes(String(option.value))}
                                                                            <CheckIcon class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                                                        {/if}
                                                                    </button>
                                                                {/each}
                                                            </div>
                                                        </div>
                                                    {/if}
                                                </div>
                                            {:else if getFieldType(condition.field) === 'enum' || getFieldType(condition.field) === 'boolean'}
                                                <select
                                                    aria-label="Condition {cIndex + 1} value"
                                                    value={String(condition.value)}
                                                    onchange={(e) =>
                                                        updateCondition(rule.id, cIndex, {
                                                            value:
                                                                getFieldType(condition.field) === 'boolean'
                                                                    ? (e.target as HTMLSelectElement).value === 'true'
                                                                    : (e.target as HTMLSelectElement).value,
                                                        })}
                                                    class="min-w-0 flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                >
                                                    {#each getValueOptions(condition.field) as option (String(option.value))}
                                                        <option value={String(option.value)}>{option.label}</option>
                                                    {/each}
                                                </select>
                                            {:else}
                                                <input
                                                    type="text"
                                                    value={Array.isArray(condition.value)
                                                        ? condition.value.join(', ')
                                                        : String(condition.value ?? '')}
                                                    aria-label="Condition {cIndex + 1} value"
                                                    placeholder="Value"
                                                    oninput={(e) => updateCondition(rule.id, cIndex, { value: (e.target as HTMLInputElement).value })}
                                                    class="min-w-0 flex-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                />
                                            {/if}
                                            {#if rule.conditions.length > 1}
                                                <button
                                                    onclick={() => removeCondition(rule.id, cIndex)}
                                                    aria-label="Remove condition {cIndex + 1}"
                                                    class="shrink-0 rounded p-1 text-gray-400 hover:text-red-600"
                                                >
                                                    <XMarkIcon class="h-4 w-4" />
                                                </button>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>

                            <div>
                                <span id="{uid}-action-{rule.id}" class="mb-2 block text-xs font-medium text-gray-500 dark:text-gray-400">Action</span
                                >
                                <div class="flex flex-wrap items-center gap-4" role="group" aria-labelledby="{uid}-action-{rule.id}">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="action-{rule.id}"
                                            checked={rule.action.type === 'ignore'}
                                            onchange={() => updateRule(rule.id, { action: { type: 'ignore' } })}
                                            class="text-blue-600"
                                        />
                                        <span class="text-gray-700 dark:text-gray-300">Ignore</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="action-{rule.id}"
                                            checked={rule.action.type === 'route'}
                                            onchange={() => updateRule(rule.id, { action: { type: 'route', channel_id: '' } })}
                                            class="text-blue-600"
                                        />
                                        <span class="text-gray-700 dark:text-gray-300">Route to channel</span>
                                    </label>
                                    {#if rule.action.type === 'route'}
                                        <ChannelPicker
                                            items={channels}
                                            value={rule.action.channel_id || null}
                                            placeholder="Select a channel..."
                                            searchPlaceholder="Type to filter channels..."
                                            emptyLabel="No channels found"
                                            onselect={(channelId) => updateRule(rule.id, { action: { type: 'route', channel_id: channelId || '' } })}
                                        />
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}
                </div>
            {/each}
        </div>
    {/if}
</div>
