<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import MagnifyingGlassIcon from '@/components/icons/MagnifyingGlass.svelte';
    import ClipboardIcon from '@/components/icons/Clipboard.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { untrack } from 'svelte';
    import type { VnList } from '@/types/lists';
    import PublicListResults from '@/components/lists/PublicListResults.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Alert, Button, Card, Select, TabLinks, TextInput } from '@/components/ui';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';

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
        type: initialType = 'all',
        search: initialSearch = '',
        sort: initialSort = 'default',
        filterGame: initialFilterGame = null,
        counts = { all: 0, plan_to_read: 0, reading: 0, completed: 0, on_hold: 0, dropped: 0, custom: 0 },
    }: Props = $props();

    let localLists = $state(untrack(() => lists.data));
    let localCounts = $state(untrack(() => counts));
    let searchInput = $state(untrack(() => initialSearch));
    let currentSearch = $state(untrack(() => initialSearch));
    let currentSort = $state(untrack(() => initialSort));
    let type = $state(untrack(() => initialType));
    let activeGame = $state<FilterGame | null>(untrack(() => initialFilterGame));
    let page = $state(untrack(() => lists.current_page));
    let perPage = $state(untrack(() => lists.per_page));

    $effect(() => {
        localLists = lists.data;
        localCounts = counts;
        searchInput = initialSearch;
        currentSearch = initialSearch;
        currentSort = initialSort;
        type = initialType;
        activeGame = initialFilterGame;
        page = lists.current_page;
        perPage = lists.per_page;
    });

    const typeLabel = (t: string) => t.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

    const filterSync = useUrlSyncedFilters({
        route: route('lists.public'),
        only: ['lists', 'type', 'search', 'sort', 'filterGame', 'counts', 'metaTags'],
        getParams: () => ({
            type,
            per_page: perPage,
            page,
            search: currentSearch,
            sort: currentSort === 'default' ? undefined : currentSort,
            game: activeGame?.id,
        }),
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        currentSearch = searchInput;
        page = 1;
    }

    function clearSearch() {
        searchInput = '';
        currentSearch = '';
        page = 1;
    }

    function clearGameFilter() {
        activeGame = null;
        page = 1;
    }

    function tabHref(tabType: string): string {
        return route('lists.public', {
            type: tabType,
            per_page: perPage,
            page: 1,
            search: currentSearch || undefined,
            sort: currentSort !== 'default' ? currentSort : undefined,
            game: activeGame?.id || undefined,
        });
    }

    const tabs = $derived([
        { key: 'all', label: 'All Lists', count: localCounts.all, href: tabHref('all') },
        { key: 'plan_to_read', label: typeLabel('plan_to_read'), count: localCounts.plan_to_read, href: tabHref('plan_to_read') },
        { key: 'reading', label: typeLabel('reading'), count: localCounts.reading, href: tabHref('reading') },
        { key: 'completed', label: typeLabel('completed'), count: localCounts.completed, href: tabHref('completed') },
        { key: 'on_hold', label: typeLabel('on_hold'), count: localCounts.on_hold, href: tabHref('on_hold') },
        { key: 'dropped', label: typeLabel('dropped'), count: localCounts.dropped, href: tabHref('dropped') },
        { key: 'custom', label: 'Custom', count: localCounts.custom, href: tabHref('custom') },
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
            <Button type="submit" variant="solid" tone="primary" disabled={filterSync.isLoading}>Search</Button>
        </form>
        <div class="flex items-center gap-2">
            <label for="sort" class="text-sm text-fg-muted">Sort by:</label>
            <Select
                id="sort"
                value={currentSort}
                onchange={(e) => {
                    currentSort = (e.target as HTMLSelectElement).value;
                    page = 1;
                }}
                disabled={filterSync.isLoading}
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

    {#if activeGame}
        <Alert tone="note" layout="inline" role="status">
            Showing lists containing:
            <Link href={route('games.show', activeGame.slug)} class="font-medium hover:underline">{activeGame.name}</Link>
            {#snippet actions()}
                <Button type="button" variant="ghost" tone="info" size="icon-sm" onclick={clearGameFilter} title="Clear filter">
                    <XMarkIcon class="h-4 w-4" />
                </Button>
            {/snippet}
        </Alert>
    {/if}

    <Card variant="flat" padding="lg">
        <TabLinks
            {tabs}
            active={type}
            onSelect={(tab) => {
                type = tab;
                page = 1;
            }}
        />
    </Card>

    <PublicListResults
        lists={{ ...lists, data: localLists }}
        showUser
        emptyMessage="There are no public lists available for this category."
        isLoading={filterSync.isLoading}
        onPageChange={(nextPage) => (page = nextPage)}
        onPerPageChange={(nextPerPage) => {
            perPage = nextPerPage;
            page = 1;
        }}
        buildPageUrl={filterSync.buildPageUrl}
    />
</div>
