<script lang="ts">
    import ChevronLeftIcon from '@/components/icons/ChevronLeft.svelte';
    import CheckIcon from '@/components/icons/Check.svelte';
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { rovingFocus } from '@/utils/roving-focus';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import type { PlatformIconMeta } from '@/hooks/usePlatformIcons';
    import { fieldChips, fieldClearPatch, optionFlagCode, optionLabel, type FilterField, type FilterOptionItem } from './filter-fields';

    interface Props {
        field: FilterField;
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        updateFilters: (filters: Partial<CurrentFilters>) => void;
        onBack: () => void;
        getPlatformIcon: (platform: string) => PlatformIconMeta;
    }

    let { field, filters, currentFilters, updateFilters, onBack, getPlatformIcon }: Props = $props();

    const OPTION_SELECTOR = '[role="option"]';

    let tagOperator = $state<'include' | 'exclude'>('include');
    let search = $state('');
    let searchEl = $state<HTMLInputElement>();
    let listEl = $state<HTMLElement>();

    const multiField = $derived(field.kind === 'multi' ? field : null);
    const flagDefs = $derived(field.kind === 'flags' ? field.flags : null);
    const canExclude = $derived(Boolean(multiField?.excludeProperty));
    const selectedValues = $derived(multiField ? (currentFilters[multiField.property] ?? []) : []);
    const excludedValues = $derived(multiField?.excludeProperty ? (currentFilters[multiField.excludeProperty] ?? []) : []);
    const hasActive = $derived(fieldChips(field, currentFilters, filters).length > 0);

    const optionEntries = $derived.by(() => {
        if (field.kind === 'flags') return [];
        const entries = Object.entries(field.options(filters)) as Array<[string, FilterOptionItem]>;
        return entries.sort(([leftValue, leftItem], [rightValue, rightItem]) => {
            if (canExclude) {
                const leftActive = membership(leftValue) !== null;
                const rightActive = membership(rightValue) !== null;
                if (leftActive !== rightActive) return leftActive ? -1 : 1;
            }
            return optionLabel(leftValue, leftItem).localeCompare(optionLabel(rightValue, rightItem), undefined, {
                sensitivity: 'base',
                numeric: true,
            });
        });
    });

    const normalizeSearchText = (value: string) =>
        value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

    const compactSearchText = (value: string) => normalizeSearchText(value).replace(/[^\p{L}\p{N}]+/gu, '');

    function matchesSearch(label: string, query: string): boolean {
        return normalizeSearchText(label).includes(normalizeSearchText(query)) || compactSearchText(label).includes(compactSearchText(query));
    }

    const showSearch = $derived(optionEntries.length > 8);

    const filteredEntries = $derived(
        showSearch && search ? optionEntries.filter(([value, item]) => matchesSearch(optionLabel(value, item), search)) : optionEntries,
    );

    $effect(() => {
        (searchEl ?? listEl?.querySelector<HTMLElement>(OPTION_SELECTOR))?.focus();
    });

    function focusFirstOption(event: KeyboardEvent) {
        if (event.key !== 'ArrowDown') return;
        const first = listEl?.querySelector<HTMLElement>(OPTION_SELECTOR);
        if (!first) return;
        event.preventDefault();
        first.focus();
    }

    function membership(value: string): 'include' | 'exclude' | null {
        if (selectedValues.includes(value)) return 'include';
        if (excludedValues.includes(value)) return 'exclude';
        return null;
    }

    function toggleValue(value: string) {
        if (!multiField) return;
        const { property, excludeProperty } = multiField;
        const target = tagOperator === 'exclude' && excludeProperty ? 'exclude' : 'include';
        const current = membership(value);
        const next = current === target ? null : target;
        const changes: Partial<CurrentFilters> = {};

        if (current === 'include' || next === 'include') {
            changes[property] = next === 'include' ? [...selectedValues, value] : selectedValues.filter((item) => item !== value);
        }
        if (excludeProperty && (current === 'exclude' || next === 'exclude')) {
            changes[excludeProperty] = next === 'exclude' ? [...excludedValues, value] : excludedValues.filter((item) => item !== value);
        }
        updateFilters(changes);
    }
</script>

<div class="flex min-h-0 flex-col">
    <div class="flex items-center justify-between gap-2 border-b border-border px-2 py-1.5">
        <button
            type="button"
            onclick={onBack}
            class="inline-flex h-7 items-center gap-1 rounded-md px-1.5 text-ui text-fg-muted transition-colors hover:text-fg focus:outline-none"
            aria-label="Back to filter menu"
        >
            <ChevronLeftIcon class="h-3.5 w-3.5" />
            Filters
        </button>
        <span class="min-w-0 flex-1 truncate text-center text-ui font-medium text-fg" aria-live="polite">
            {field.label}
        </span>
        {#if hasActive}
            <button
                type="button"
                onclick={() => updateFilters(fieldClearPatch(field))}
                aria-label="Clear {field.label.toLowerCase()} filter"
                class="rounded-md px-1.5 text-xs text-fg-muted transition-colors hover:text-fg focus:outline-none"
            >
                Clear
            </button>
        {:else}
            <span class="w-8" aria-hidden="true"></span>
        {/if}
    </div>

    {#if canExclude}
        <div class="flex gap-1 px-2 pt-2" role="group" aria-label="{field.label} filter operator">
            <button
                type="button"
                onclick={() => (tagOperator = 'include')}
                aria-pressed={tagOperator === 'include'}
                class="h-6 flex-1 rounded-md border px-2 text-xs font-medium transition-colors {tagOperator === 'include'
                    ? 'border-accent-fg bg-accent/25 text-fg dark:bg-accent/12'
                    : 'border-border text-fg-muted hover:text-fg'}"
            >
                Include
            </button>
            <button
                type="button"
                onclick={() => (tagOperator = 'exclude')}
                aria-pressed={tagOperator === 'exclude'}
                class="h-6 flex-1 rounded-md border px-2 text-xs font-medium transition-colors {tagOperator === 'exclude'
                    ? 'border-accent-fg bg-accent/25 text-fg dark:bg-accent/12'
                    : 'border-border text-fg-muted hover:text-fg'}"
            >
                Exclude
            </button>
        </div>
    {/if}

    {#if showSearch}
        <div class="p-2 pb-1">
            <input
                bind:this={searchEl}
                bind:value={search}
                onkeydown={focusFirstOption}
                type="text"
                placeholder="Search {field.label.toLowerCase()}..."
                aria-label="Search {field.label.toLowerCase()}"
                class="block w-full rounded-md border border-border bg-surface-alt px-2 py-1.5 text-ui text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
            />
        </div>
    {/if}

    <div
        bind:this={listEl}
        class="max-h-72 overflow-y-auto p-1.5"
        role="listbox"
        aria-label="{field.label} options"
        aria-multiselectable={field.kind !== 'single'}
        {@attach rovingFocus(OPTION_SELECTOR)}
    >
        {#if flagDefs}
            {#each flagDefs as def (def.key)}
                <button
                    type="button"
                    role="option"
                    aria-selected={Boolean(currentFilters[def.key])}
                    onclick={() => updateFilters({ [def.key]: !currentFilters[def.key] })}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center" aria-hidden="true">
                        {#if currentFilters[def.key]}
                            <CheckIcon class="h-3.5 w-3.5 text-accent-fg" />
                        {/if}
                    </span>
                    {def.label}
                </button>
            {/each}
        {:else if field.kind === 'single'}
            {#each optionEntries as [value, item] (value)}
                {@const isSelected = currentFilters[field.property] === value}
                <button
                    type="button"
                    role="option"
                    aria-selected={isSelected}
                    onclick={() => updateFilters({ [field.property]: value })}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span
                        class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border {isSelected
                            ? 'border-accent-fg'
                            : 'border-border-strong'}"
                        aria-hidden="true"
                    >
                        {#if isSelected}
                            <span class="h-2 w-2 rounded-full accent-fill"></span>
                        {/if}
                    </span>
                    {optionLabel(value, item)}
                </button>
            {/each}
        {:else if filteredEntries.length === 0}
            <div class="px-2 py-3 text-center text-ui text-fg-muted" role="status">
                No {field.label.toLowerCase()} found
            </div>
        {:else}
            {#each filteredEntries as [value, item] (value)}
                {@const valueMembership = membership(value)}
                {@const activeInOperator = canExclude ? valueMembership === tagOperator : valueMembership === 'include'}
                <button
                    type="button"
                    role="option"
                    aria-selected={activeInOperator}
                    onclick={() => toggleValue(value)}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span
                        class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-sm border {activeInOperator
                            ? 'border-accent-fg bg-accent text-on-accent'
                            : 'border-border-strong'}"
                        aria-hidden="true"
                    >
                        {#if activeInOperator}
                            <CheckIcon class="h-3 w-3" />
                        {/if}
                    </span>
                    {#if multiField?.icon === 'flag' && optionFlagCode(item)}
                        <span class="fi fi-{optionFlagCode(item)} rounded-sm"></span>
                    {:else if multiField?.icon === 'platform' && getPlatformIcon(value)}
                        {@const iconMeta = getPlatformIcon(value)}
                        {@const PlatformIconComponent = iconMeta.icon}
                        <PlatformIconComponent class="h-3.5 w-3.5 text-fg-muted" />
                    {:else if multiField?.icon === 'store'}
                        {#if value === 'itch_io'}
                            <Itchio class="h-3.5 w-3.5 text-fg-muted" monochrome />
                        {:else if value === 'steam'}
                            <Steam class="h-3.5 w-3.5 text-fg-muted" monochrome />
                        {:else}
                            <GlobeIcon class="h-3.5 w-3.5 text-fg-muted" />
                        {/if}
                    {/if}
                    <span class="min-w-0 flex-1 truncate">
                        {optionLabel(value, item)}
                    </span>
                    {#if canExclude && valueMembership && valueMembership !== tagOperator}
                        <span class="flex-shrink-0 rounded-full bg-surface-alt px-1.5 py-0.5 text-2xs text-fg-muted">
                            {valueMembership === 'include' ? 'Included' : 'Excluded'}
                        </span>
                    {/if}
                </button>
            {/each}
        {/if}
    </div>
</div>
