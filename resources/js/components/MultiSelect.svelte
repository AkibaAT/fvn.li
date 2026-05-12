<script lang="ts">
    import { tick } from 'svelte';
    import { Button, Checkbox, TextInput } from '@/components/ui';
    import type { Snippet } from 'svelte';

    interface SelectItem {
        ref_name?: string;
        name?: string;
        flag_code?: string;
    }

    let {
        title,
        items,
        selectedItems,
        onToggle,
        renderItem,
        placeholder,
    }: {
        title: string;
        items?: Record<string, string | SelectItem>;
        selectedItems: string[];
        onToggle: (value: string) => void;
        renderItem?: Snippet<[string, string | SelectItem]>;
        placeholder?: string;
    } = $props();

    let isOpen = $state(false);
    let search = $state('');
    let opensUp = $state(false);
    let containerEl = $state<HTMLDivElement>();
    let dropdownEl = $state<HTMLDivElement>();

    const normalizeSearchText = (value: string) =>
        value
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    const getDisplayLabel = (value: string, item: string | SelectItem | undefined): string => {
        if (!item) return value;
        if (typeof item === 'string') return item;
        return item.name || item.ref_name || value;
    };
    const getSortLabel = (value: string, item: string | SelectItem) => {
        const label = normalizeSearchText(getDisplayLabel(value, item)).replace(/^[^\p{L}\p{N}]+/gu, '');

        return label || getDisplayLabel(value, item);
    };
    const compareLabels = ([leftValue, leftItem]: [string, string | SelectItem], [rightValue, rightItem]: [string, string | SelectItem]) =>
        getSortLabel(leftValue, leftItem).localeCompare(getSortLabel(rightValue, rightItem), undefined, {
            sensitivity: 'base',
            numeric: true,
        });
    const itemEntries = $derived(Object.entries(items || {}).sort(compareLabels));
    const compactSearchText = (value: string) => normalizeSearchText(value).replace(/[^\p{L}\p{N}]+/gu, '');
    const matchesSearch = (label: string, query: string) => {
        const normalizedLabel = normalizeSearchText(label);
        const normalizedQuery = normalizeSearchText(query);

        return normalizedLabel.includes(normalizedQuery) || compactSearchText(label).includes(compactSearchText(query));
    };
    const filteredItems = $derived(
        search
            ? itemEntries.filter(([value, item]) => {
                  return matchesSearch(getDisplayLabel(value, item), search);
              })
            : itemEntries,
    );

    // Close dropdown when clicking outside
    $effect(() => {
        if (!isOpen) return;

        const handleClickOutside = (event: MouseEvent) => {
            if (containerEl && !containerEl.contains(event.target as Node)) {
                isOpen = false;
                search = '';
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    $effect(() => {
        if (!isOpen) return;

        const updatePlacement = () => positionDropdown();

        window.addEventListener('resize', updatePlacement);
        window.addEventListener('scroll', updatePlacement, true);
        return () => {
            window.removeEventListener('resize', updatePlacement);
            window.removeEventListener('scroll', updatePlacement, true);
        };
    });

    async function toggleDropdown() {
        if (isOpen) {
            isOpen = false;
            search = '';
            return;
        }

        isOpen = true;
        opensUp = false;
        await tick();
        positionDropdown();
    }

    function positionDropdown() {
        if (!containerEl || !dropdownEl) return;

        const gap = 8;
        const containerRect = containerEl.getBoundingClientRect();
        const dropdownHeight = dropdownEl.offsetHeight;
        const availableBelow = window.innerHeight - containerRect.bottom - gap;
        const availableAbove = containerRect.top - gap;

        opensUp = availableBelow < dropdownHeight && availableAbove > availableBelow;
    }
</script>

<div class="relative" bind:this={containerEl}>
    <div
        role="button"
        tabindex="0"
        onclick={toggleDropdown}
        onkeydown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleDropdown();
            }
        }}
        class="flex w-full cursor-pointer items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        aria-expanded={isOpen}
        aria-haspopup="listbox"
        aria-controls="{title}-options"
    >
        <span class="min-w-0 flex-1">
            {#if selectedItems.length > 0}
                <span class="flex flex-wrap gap-1" role="list" aria-label="Selected {title.toLowerCase()}">
                    {#each selectedItems as value (value)}
                        {@const item = items?.[value]}
                        {@const label = getDisplayLabel(value, item)}
                        <span
                            class="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                            role="listitem"
                        >
                            {#if renderItem && item}
                                {@render renderItem(value, item)}
                            {:else}
                                {label}
                            {/if}
                            <Button
                                type="button"
                                variant="ghost"
                                tone="neutral"
                                size="icon-sm"
                                onclick={(e) => {
                                    e.stopPropagation();
                                    onToggle(value);
                                }}
                                class="ml-1 h-3 w-3 rounded-full hover:opacity-80 focus:ring-1 focus:ring-blue-500"
                                ariaLabel="Remove {label}"
                            >
                                <span aria-hidden="true">&times;</span>
                            </Button>
                        </span>
                    {/each}
                </span>
            {:else}
                <span class="text-gray-500 dark:text-gray-400">
                    {placeholder || `Select ${title.toLowerCase()}...`}
                </span>
            {/if}
        </span>
        <svg
            class="ml-2 h-4 w-4 flex-shrink-0 transition-transform {isOpen ? 'rotate-180' : ''}"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </div>

    {#if isOpen}
        <div
            id="{title}-options"
            bind:this={dropdownEl}
            class="absolute z-10 w-full rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-700 {opensUp
                ? 'bottom-full mb-1'
                : 'top-full mt-1'}"
            role="listbox"
            aria-label="{title} options"
            aria-multiselectable="true"
        >
            <!-- Search -->
            <div class="p-2">
                <TextInput
                    bind:value={search}
                    placeholder="Search {title.toLowerCase()}..."
                    class="px-2 py-1"
                    aria-label="Search {title.toLowerCase()}"
                />
            </div>

            <!-- Options -->
            <div class="max-h-48 overflow-y-auto" role="group">
                {#if filteredItems.length === 0}
                    <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400" role="status">
                        No {title.toLowerCase()} found
                    </div>
                {:else}
                    {#each filteredItems as [value, item] (value)}
                        <div
                            class="flex cursor-pointer items-center px-3 py-2 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:hover:bg-gray-600 dark:focus:bg-gray-600"
                            role="option"
                            tabindex="0"
                            aria-selected={selectedItems.includes(value)}
                            onclick={() => onToggle(value)}
                            onkeydown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    onToggle(value);
                                }
                            }}
                        >
                            <Checkbox
                                checked={selectedItems.includes(value)}
                                onclick={(e) => e.stopPropagation()}
                                onchange={() => onToggle(value)}
                                class="mr-2 focus:ring-2"
                                aria-label="Select {getDisplayLabel(value, item)}"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {#if renderItem && item}
                                    {@render renderItem(value, item)}
                                {:else}
                                    {getDisplayLabel(value, item)}
                                {/if}
                            </span>
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    {/if}
</div>
