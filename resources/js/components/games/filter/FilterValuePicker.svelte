<script lang="ts">
    import ChevronLeftIcon from '@/components/icons/ChevronLeft.svelte';
    import CheckIcon from '@/components/icons/Check.svelte';
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import type { CurrentFilters, FilterOptions } from '@/types';
    import type { PlatformIconMeta } from '@/hooks/usePlatformIcons';

    interface LanguageOption {
        ref_name?: string;
        name?: string;
        flag_code?: string;
    }

    type FlagDef = {
        value: string;
        label: string;
        key: keyof CurrentFilters;
    };

    interface Props {
        field: string;
        filters: FilterOptions;
        currentFilters: CurrentFilters;
        toggleFilter: (type: string, value: string) => void;
        updateFilters: (filters: Partial<CurrentFilters>) => void;
        onBack: () => void;
        getPlatformIcon: (platform: string) => PlatformIconMeta;
    }

    let { field, filters, currentFilters, toggleFilter, updateFilters, onBack, getPlatformIcon }: Props = $props();

    const readingTimeDefaults: Record<string, string> = {
        short: 'Short (< 10k words)',
        medium: 'Medium (10k-50k words)',
        long: 'Long (> 50k words)',
    };

    const contentFlags: FlagDef[] = [
        { value: 'sfw', label: 'Safe for Work', key: 'sfw' },
        { value: 'nsfw', label: 'NSFW Content', key: 'nsfw' },
    ];

    const priceFlags: FlagDef[] = [
        { value: 'showFree', label: 'Free Games', key: 'showFree' },
        { value: 'showPaid', label: 'Paid Games', key: 'showPaid' },
        { value: 'showDemo', label: 'Has Demo', key: 'showDemo' },
        { value: 'showSale', label: 'On Sale', key: 'showSale' },
    ];

    const visibilityFlags: FlagDef[] = [{ value: 'showIgnored', label: 'Show ignored games', key: 'showIgnored' }];

    type MultiProperty =
        | 'selectedStatuses'
        | 'selectedEngines'
        | 'selectedPlatforms'
        | 'selectedStorePlatforms'
        | 'selectedLanguages'
        | 'selectedGameJams'
        | 'selectedTags';

    const multiConfigs = $derived<
        Record<
            string,
            { title: string; options: Record<string, string | LanguageOption>; property: MultiProperty; icon?: 'flag' | 'platform' | 'store' }
        >
    >({
        status: { title: 'Status', options: filters.statuses, property: 'selectedStatuses' },
        engine: { title: 'Engine', options: filters.gameEngines, property: 'selectedEngines' },
        platform: { title: 'Platform', options: filters.platforms, property: 'selectedPlatforms', icon: 'platform' },
        storePlatform: { title: 'Store', options: filters.storePlatforms, property: 'selectedStorePlatforms', icon: 'store' },
        language: { title: 'Language', options: filters.languages, property: 'selectedLanguages', icon: 'flag' },
        gameJam: { title: 'Game jam', options: filters.gameJams, property: 'selectedGameJams' },
        tags: { title: 'Tags', options: filters.tags, property: 'selectedTags' },
    });

    let tagOperator = $state<'include' | 'exclude'>('include');
    let search = $state('');
    let searchEl = $state<HTMLInputElement>();

    $effect(() => {
        searchEl?.focus();
    });

    const isTags = $derived(field === 'tags');
    const multiConfig = $derived(multiConfigs[field]);
    const flagDefs = $derived(field === 'content' ? contentFlags : field === 'price' ? priceFlags : field === 'visibility' ? visibilityFlags : null);
    const isSingle = $derived(field === 'readingTime');
    const title = $derived(
        multiConfig?.title ??
            (field === 'readingTime'
                ? 'Reading time'
                : ((flagDefs?.[0] && (field === 'content' ? 'Content rating' : field === 'price' ? 'Price & availability' : 'Visibility')) ?? '')),
    );

    const selectedValues = $derived((currentFilters[multiConfig?.property ?? 'selectedStatuses'] as string[] | undefined) ?? []);
    const excludedTags = $derived(currentFilters.excludedTags ?? []);

    const options = $derived.by(() => {
        if (isSingle) return filters.readingTimeOptions || readingTimeDefaults;
        if (multiConfig) return multiConfig.options;
        return {};
    });

    const optionEntries = $derived.by(() => {
        const entries = Object.entries(options) as Array<[string, string | LanguageOption]>;
        const compare = ([leftValue, leftItem]: [string, string | LanguageOption], [rightValue, rightItem]: [string, string | LanguageOption]) =>
            displayLabel(leftValue, leftItem).localeCompare(displayLabel(rightValue, rightItem), undefined, { sensitivity: 'base', numeric: true });

        return entries.sort(([leftValue, leftItem], [rightValue, rightItem]) => {
            if (isTags) {
                const leftActive = tagMembership(leftValue) !== null;
                const rightActive = tagMembership(rightValue) !== null;
                if (leftActive !== rightActive) return leftActive ? -1 : 1;
            }
            return compare([leftValue, leftItem], [rightValue, rightItem]);
        });
    });

    const normalizeSearchText = (value: string) =>
        value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

    function displayLabel(value: string, item: string | LanguageOption | undefined): string {
        if (!item) return value;
        if (typeof item === 'string') return item;
        return item.name || item.ref_name || value;
    }

    function flagCode(item: string | LanguageOption | undefined): string | undefined {
        return typeof item === 'object' ? item?.flag_code : undefined;
    }

    const compactSearchText = (value: string) => normalizeSearchText(value).replace(/[^\p{L}\p{N}]+/gu, '');

    function matchesSearch(label: string, query: string): boolean {
        const normalizedLabel = normalizeSearchText(label);
        const normalizedQuery = normalizeSearchText(query);

        return normalizedLabel.includes(normalizedQuery) || compactSearchText(label).includes(compactSearchText(query));
    }

    const showSearch = $derived(optionEntries.length > 8);

    const filteredEntries = $derived(
        showSearch && search ? optionEntries.filter(([value, item]) => matchesSearch(displayLabel(value, item), search)) : optionEntries,
    );

    function tagMembership(value: string): 'include' | 'exclude' | null {
        if ((currentFilters.selectedTags ?? []).includes(value)) return 'include';
        if ((currentFilters.excludedTags ?? []).includes(value)) return 'exclude';
        return null;
    }

    function setTagMembership(value: string, target: 'include' | 'exclude' | null) {
        const include = new SvelteSet(currentFilters.selectedTags ?? []);
        const exclude = new SvelteSet(currentFilters.excludedTags ?? []);
        include.delete(value);
        exclude.delete(value);
        if (target === 'include') include.add(value);
        if (target === 'exclude') exclude.add(value);
        updateFilters({ selectedTags: [...include], excludedTags: [...exclude] });
    }

    function handleRowClick(value: string) {
        if (isTags) {
            const membership = tagMembership(value);
            const active = membership === (tagOperator === 'include' ? 'include' : 'exclude');
            setTagMembership(value, active ? null : tagOperator);
            return;
        }
        if (multiConfig) {
            const toggleTypes: Record<string, string> = {
                status: 'status',
                engine: 'engine',
                platform: 'platform',
                storePlatform: 'storePlatform',
                language: 'language',
                gameJam: 'gameJam',
                tags: 'tag',
            };
            const type = isTags ? 'tag' : toggleTypes[field];
            if (type) toggleFilter(type, value);
        }
    }

    function clearField() {
        if (isTags) updateFilters({ selectedTags: [], excludedTags: [] });
        else if (multiConfig) updateFilters({ [multiConfig.property]: [] } as Partial<CurrentFilters>);
        else if (isSingle) updateFilters({ readingTime: '' });
    }

    const hasActive = $derived(
        isTags
            ? selectedValues.length + excludedTags.length > 0
            : isSingle
              ? Boolean(currentFilters.readingTime)
              : flagDefs
                ? flagDefs.some((def) => Boolean(currentFilters[def.key]))
                : selectedValues.length > 0,
    );
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
            {title}
        </span>
        {#if hasActive}
            <button
                type="button"
                onclick={clearField}
                class="rounded-md px-1.5 text-xs text-fg-muted transition-colors hover:text-fg focus:outline-none"
            >
                Clear
            </button>
        {:else}
            <span class="w-8" aria-hidden="true"></span>
        {/if}
    </div>

    {#if isTags}
        <div class="flex gap-1 px-2 pt-2" role="group" aria-label="Tag filter operator">
            <button
                type="button"
                onclick={() => (tagOperator = 'include')}
                aria-pressed={tagOperator === 'include'}
                class="h-6 flex-1 rounded-md border px-2 text-xs font-medium transition-colors {tagOperator === 'include'
                    ? 'border-accent bg-accent/10 text-fg'
                    : 'border-border text-fg-muted hover:text-fg'}"
            >
                Include
            </button>
            <button
                type="button"
                onclick={() => (tagOperator = 'exclude')}
                aria-pressed={tagOperator === 'exclude'}
                class="h-6 flex-1 rounded-md border px-2 text-xs font-medium transition-colors {tagOperator === 'exclude'
                    ? 'border-accent bg-accent/10 text-fg'
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
                type="text"
                placeholder="Search {title.toLowerCase()}..."
                aria-label="Search {title.toLowerCase()}"
                class="block w-full rounded-md border border-border bg-surface-alt px-2 py-1.5 text-ui text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
            />
        </div>
    {/if}

    <div
        class="max-h-72 overflow-y-auto p-1.5"
        role={multiConfig || flagDefs ? 'listbox' : 'radiogroup'}
        aria-label="{title} options"
        aria-multiselectable={multiConfig || flagDefs ? 'true' : undefined}
    >
        {#if flagDefs}
            {#each flagDefs as def (def.value)}
                <button
                    type="button"
                    role="option"
                    aria-selected={Boolean(currentFilters[def.key])}
                    onclick={() => updateFilters({ [def.key]: !currentFilters[def.key] } as Partial<CurrentFilters>)}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span class="flex h-4 w-4 flex-shrink-0 items-center justify-center" aria-hidden="true">
                        {#if currentFilters[def.key]}
                            <CheckIcon class="h-3.5 w-3.5 text-accent" />
                        {/if}
                    </span>
                    {def.label}
                </button>
            {/each}
        {:else if isSingle}
            {#each optionEntries as [value, item] (value)}
                <button
                    type="button"
                    role="radio"
                    aria-checked={currentFilters.readingTime === value}
                    onclick={() => updateFilters({ readingTime: value })}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span
                        class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border {currentFilters.readingTime === value
                            ? 'border-accent'
                            : 'border-border-strong'}"
                        aria-hidden="true"
                    >
                        {#if currentFilters.readingTime === value}
                            <span class="h-2 w-2 rounded-full bg-accent"></span>
                        {/if}
                    </span>
                    {displayLabel(value, item)}
                </button>
            {/each}
        {:else if filteredEntries.length === 0}
            <div class="px-2 py-3 text-center text-ui text-fg-muted" role="status">
                No {title.toLowerCase()} found
            </div>
        {:else}
            {#each filteredEntries as [value, item] (value)}
                {@const isSelected = selectedValues.includes(value)}
                {@const membership = isTags ? tagMembership(value) : isSelected ? 'include' : null}
                {@const activeInOperator = isTags ? membership === tagOperator : isSelected}
                <button
                    type="button"
                    role="option"
                    aria-selected={activeInOperator}
                    onclick={() => handleRowClick(value)}
                    class="popover-row-hover flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-ui text-fg transition-colors focus:outline-none"
                >
                    <span
                        class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-sm border {activeInOperator
                            ? 'border-accent bg-accent text-surface'
                            : 'border-border-strong'}"
                        aria-hidden="true"
                    >
                        {#if activeInOperator}
                            <CheckIcon class="h-3 w-3" />
                        {/if}
                    </span>
                    {#if multiConfig?.icon === 'flag' && flagCode(item)}
                        <span class="fi fi-{flagCode(item)} rounded-sm"></span>
                    {:else if multiConfig?.icon === 'platform' && getPlatformIcon(value)}
                        {@const iconMeta = getPlatformIcon(value)}
                        {@const PlatformIconComponent = iconMeta.icon}
                        <PlatformIconComponent class="h-3.5 w-3.5 text-fg-muted" />
                    {:else if multiConfig?.icon === 'store'}
                        {#if value === 'itch_io'}
                            <Itchio class="h-3.5 w-3.5 text-fg-muted" monochrome />
                        {:else if value === 'steam'}
                            <Steam class="h-3.5 w-3.5 text-fg-muted" monochrome />
                        {:else}
                            <GlobeIcon class="h-3.5 w-3.5 text-fg-muted" />
                        {/if}
                    {/if}
                    <span class="min-w-0 flex-1 truncate">
                        {displayLabel(value, item)}
                    </span>
                    {#if isTags && membership && membership !== tagOperator}
                        <span class="flex-shrink-0 rounded-full bg-surface-alt px-1.5 py-0.5 text-2xs text-fg-muted">
                            {membership === 'include' ? 'Included' : 'Excluded'}
                        </span>
                    {/if}
                </button>
            {/each}
        {/if}
    </div>
</div>
