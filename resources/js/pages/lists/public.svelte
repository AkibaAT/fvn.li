<script lang="ts">
    import { untrack } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import type { VnList } from '@/components/VnListCard.svelte';
    import VnListCard from '@/components/VnListCard.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';

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

<svelte:head>
    <title>{metaTags?.title || 'Public Visual Novel Lists'}</title>
</svelte:head>

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-blue-600">Public Visual Novel Lists</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Discover and explore visual novel lists shared by the community</p>
        </div>
        <div class="mt-4 flex space-x-3 sm:mt-0">
            <Link
                href={route('lists.index')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
                >My Lists</Link
            >
        </div>
    </div>

    <!-- Search and Sort Controls -->
    <div
        class="flex flex-col gap-4 rounded-xl bg-white/70 p-4 shadow-lg backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between dark:bg-gray-800/70"
    >
        <form onsubmit={handleSearch} class="flex max-w-md flex-1 gap-2">
            <div class="relative flex-1">
                <input
                    type="text"
                    bind:value={searchInput}
                    placeholder="Search by user or VN name..."
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pr-4 pl-10 text-sm text-gray-900 placeholder-gray-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                />
                <svg class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                {#if currentSearch}
                    <Button
                        type="button"
                        variant="ghost"
                        tone="neutral"
                        size="icon-sm"
                        onclick={clearSearch}
                        class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        ariaLabel="Clear search"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg
                        >
                    </Button>
                {/if}
            </div>
            <Button
                type="submit"
                variant="solid"
                tone="primary"
                disabled={isLoading}
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50"
                >Search</Button
            >
        </form>
        <div class="flex items-center gap-2">
            <label for="sort" class="text-sm text-gray-600 dark:text-gray-400">Sort by:</label>
            <select
                id="sort"
                value={currentSort}
                onchange={(e) => handleSortChange((e.target as HTMLSelectElement).value)}
                disabled={isLoading}
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            >
                <option value="default">Default</option>
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="most_entries">Most Games</option>
                <option value="recently_updated">Recently Updated</option>
            </select>
        </div>
    </div>

    {#if currentSearch}
        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <span>Showing results for:</span>
            <span class="rounded-full bg-blue-100 px-3 py-1 font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">"{currentSearch}"</span>
            <Button type="button" variant="link" tone="primary" onclick={clearSearch}>Clear</Button>
        </div>
    {/if}

    {#if filterGame}
        <div class="flex items-center gap-2 rounded-lg bg-purple-50 p-3 text-sm dark:bg-purple-900/20">
            <span class="text-gray-700 dark:text-gray-300">Showing lists containing:</span>
            <Link href={route('games.show', filterGame.slug)} class="font-medium text-purple-700 hover:underline dark:text-purple-300"
                >{filterGame.name}</Link
            >
            <Button
                type="button"
                variant="ghost"
                tone="primary"
                size="icon-sm"
                onclick={clearGameFilter}
                class="ml-auto rounded-full p-1 text-purple-600 hover:bg-purple-100 dark:text-purple-400"
                title="Clear filter"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    ><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg
                >
            </Button>
        </div>
    {/if}

    <Card variant="glass" padding="lg">
        <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
            {#each tabs as tab (tab.key)}
                <Link
                    href={route('lists.public', tab.key === 'all' ? {} : { type: tab.key })}
                    onclick={(e: MouseEvent) => {
                        e.preventDefault();
                        handleTabChange(tab.key);
                    }}
                    class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors {type === tab.key
                        ? 'border-b-2 border-blue-600 bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200'}"
                >
                    {tab.label} ({tab.count})
                </Link>
            {/each}
        </div>
    </Card>

    {#if localLists.length > 0}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each localLists as list (list.id)}
                <VnListCard {list} showUser={true} />
            {/each}
        </div>
    {:else}
        <div class="py-12 text-center">
            <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">No public lists found</h3>
            <p class="text-gray-600 dark:text-gray-400">There are no public lists available for this category.</p>
        </div>
    {/if}

    <AdvancedPagination
        meta={{
            current_page: lists.current_page,
            last_page: lists.last_page,
            total: lists.total,
            from: localLists.length ? (lists.current_page - 1) * lists.per_page + 1 : 0,
            to: localLists.length ? (lists.current_page - 1) * lists.per_page + localLists.length : 0,
            per_page: lists.per_page,
        }}
        onPageChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        {isLoading}
        label="results"
        perPageOptions={[8, 16, 24, 32]}
        {buildPageUrl}
    />
</div>
