<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import ClipboardIcon from '@/components/icons/Clipboard.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { untrack } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import type { VnList } from '@/components/VnListCard.svelte';
    import PublicListResults from '@/components/lists/PublicListResults.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { shouldIntercept } from '@inertiajs/core';
    import { Alert, Button, Card, Select, TextInput } from '@/components/ui';

    interface FilterGame {
        id: number;
        name: string;
        slug: string;
    }

    interface Props {
        lists: { data: VnList[]; current_page: number; last_page: number; per_page: number; total: number };
        metaTags?: { title?: string; description?: string };
        type?: string;
        search?: string;
        sort?: string;
        filterGame?: FilterGame | null;
        counts?: { all: number; plan_to_read: number; reading: number; completed: number; on_hold: number; dropped: number; custom: number };
    }

    let {
        lists,
        metaTags,
        type = 'all',
        search: initialSearch = '',
        sort: initialSort = 'default',
        filterGame = null,
        counts = { all: 0, plan_to_read: 0, reading: 0, completed: 0, on_hold: 0, dropped: 0, custom: 0 },
    }: Props = $props();

    let isLoading = $state(false);
    let localLists = $state(untrack(() => lists.data));
    let localCounts = $state(untrack(() => counts));
    let searchInput = $state(untrack(() => initialSearch));
    let currentSearch = $state(untrack(() => initialSearch));
    let currentSort = $state(untrack(() => initialSort));

    $effect(() => {
        localLists = lists.data;
        localCounts = counts;
        searchInput = initialSearch;
        currentSearch = initialSearch;
        currentSort = initialSort;
    });

    const typeLabel = (t: string) => t.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

    function navigateWithParams(params: Record<string, any>) {
        isLoading = true;
        router.get(route('lists.public'), params, { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) });
    }

    function handleTabChange(newType: string) {
        navigateWithParams({
            type: newType,
            per_page: lists.per_page,
            page: 1,
            search: currentSearch || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function handlePageChange(page: number) {
        navigateWithParams({
            type,
            per_page: lists.per_page,
            page,
            search: currentSearch || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function handlePerPageChange(perPage: number) {
        navigateWithParams({
            type,
            per_page: perPage,
            page: 1,
            search: currentSearch || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function handleSearch(e: Event) {
        e.preventDefault();
        currentSearch = searchInput;
        navigateWithParams({
            type,
            per_page: lists.per_page,
            page: 1,
            search: searchInput || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function handleSortChange(newSort: string) {
        currentSort = newSort;
        navigateWithParams({
            type,
            per_page: lists.per_page,
            page: 1,
            search: currentSearch || undefined,
            sort: newSort !== 'default' ? newSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function clearSearch() {
        searchInput = '';
        currentSearch = '';
        navigateWithParams({
            type,
            per_page: lists.per_page,
            page: 1,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: filterGame?.id || undefined,
        });
    }

    function clearGameFilter() {
        navigateWithParams({
            type,
            per_page: lists.per_page,
            page: 1,
            search: currentSearch || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
        });
    }

    function buildPageUrl(page: number): string {
        const params = new SvelteURLSearchParams();
        if (type && type !== 'all') params.set('type', type);
        if (currentSearch) params.set('search', currentSearch);
        if (currentSort && currentSort !== 'default') params.set('sort', currentSort);
        if (filterGame?.id) params.set('game', filterGame.id.toString());
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists/public?${params.toString()}`;
    }

    const tabs = $derived([
        { key: 'all', label: 'All Lists', count: localCounts.all },
        { key: 'plan_to_read', label: typeLabel('plan_to_read'), count: localCounts.plan_to_read },
        { key: 'reading', label: typeLabel('reading'), count: localCounts.reading },
        { key: 'completed', label: typeLabel('completed'), count: localCounts.completed },
        { key: 'on_hold', label: typeLabel('on_hold'), count: localCounts.on_hold },
        { key: 'dropped', label: typeLabel('dropped'), count: localCounts.dropped },
        { key: 'custom', label: 'Custom', count: localCounts.custom },
    ]);
</script>

<SeoHead {metaTags} title="Public Visual Novel Lists" />

<div class="space-y-8">
    <PageHeader title="Public Visual Novel Lists">
        {#snippet actions()}
            <Button href={route('lists.index')} variant="outline" tone="neutral">
                <ClipboardIcon class="h-5 w-5" />
                My Lists
            </Button>
        {/snippet}
    </PageHeader>

    <Card variant="flat" padding="md" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <form onsubmit={handleSearch} class="flex max-w-md flex-1 gap-2">
            <div class="relative flex-1">
                <TextInput type="text" bind:value={searchInput} placeholder="Search by user or VN name..." class="pr-4 pl-10" />
                <MagnifyingGlassIcon class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-fg-faint" />
                {#if currentSearch}
                    <Button
                        type="button"
                        variant="ghost"
                        tone="neutral"
                        size="icon-sm"
                        onclick={clearSearch}
                        class="absolute top-1/2 right-3 -translate-y-1/2"
                        ariaLabel="Clear search"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </Button>
                {/if}
            </div>
            <Button type="submit" variant="solid" tone="primary" disabled={isLoading}>Search</Button>
        </form>
        <div class="flex items-center gap-2">
            <label for="sort" class="text-sm text-fg-muted">Sort by:</label>
            <Select
                id="sort"
                value={currentSort}
                onchange={(e) => handleSortChange((e.target as HTMLSelectElement).value)}
                disabled={isLoading}
                class="w-auto"
            >
                <option value="default">Default</option>
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="most_entries">Most Games</option>
                <option value="recently_updated">Recently Updated</option>
            </Select>
        </div>
    </Card>

    {#if currentSearch}
        <div class="flex items-center gap-2 text-sm text-fg-muted">
            <span>Showing results for:</span>
            <span class="rounded-full bg-surface-alt px-3 py-1 font-medium text-fg">"{currentSearch}"</span>
            <Button type="button" variant="link" tone="primary" onclick={clearSearch}>Clear</Button>
        </div>
    {/if}

    {#if filterGame}
        <Alert tone="note" layout="inline" role="status">
            Showing lists containing:
            <Link href={route('games.show', filterGame.slug)} class="font-medium hover:underline">{filterGame.name}</Link>
            {#snippet actions()}
                <Button type="button" variant="ghost" tone="info" size="icon-sm" onclick={clearGameFilter} title="Clear filter">
                    <XMarkIcon class="h-4 w-4" />
                </Button>
            {/snippet}
        </Alert>
    {/if}

    <Card variant="flat" padding="lg">
        <div class="flex flex-wrap gap-2 border-b border-border">
            {#each tabs as tab (tab.key)}
                <a
                    href={route('lists.public', {
                        type: tab.key,
                        per_page: lists.per_page,
                        page: 1,
                        search: currentSearch || undefined,
                        sort: currentSort !== 'default' ? currentSort : undefined,
                        game: filterGame?.id || undefined,
                    })}
                    onclick={(e: MouseEvent) => {
                        if (!shouldIntercept(e)) return;
                        e.preventDefault();
                        handleTabChange(tab.key);
                    }}
                    class="rounded-t-md px-4 py-2 text-sm font-medium transition-colors {type === tab.key
                        ? 'border-b-2 border-accent text-fg'
                        : 'text-fg-muted hover:text-fg'}"
                >
                    {tab.label} ({tab.count})
                </a>
            {/each}
        </div>
    </Card>

    <PublicListResults
        lists={{ ...lists, data: localLists }}
        showUser
        emptyMessage="There are no public lists available for this category."
        {isLoading}
        onPageChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        {buildPageUrl}
    />
</div>
