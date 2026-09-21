<script lang="ts">
    import { untrack } from 'svelte';
    import { refreshPage } from '@/utils/refreshPage';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import PlusCircleIcon from '@/components/icons/PlusCircle.svelte';
    import UsersIcon from '@/components/icons/Users.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import type { VnList } from '@/types/lists';
    import VnListCard from '@/components/VnListCard.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { router } from '@inertiajs/svelte';
    import { toast } from '@/utils/toast';
    import { destroyVnList, toggleVnListVisibility } from '@/api/lists';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import { Button, Card, EmptyState, TabLinks } from '@/components/ui';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';
    import { buildPageMeta } from '@/utils/pagination';

    interface Props {
        lists: { data: VnList[]; current_page: number; last_page: number; per_page: number; total: number };
        visibility: string;
        metaTags?: { title?: string; description?: string };
        counts?: { all: number; public: number; private: number };
    }

    let { lists, visibility, metaTags, counts = { all: 0, public: 0, private: 0 } }: Props = $props();

    let visibilityFilter = $state(untrack(() => visibility));
    let page = $state(untrack(() => lists.current_page));
    let perPage = $state(untrack(() => lists.per_page));

    const filterSync = useUrlSyncedFilters({
        route: route('lists.index'),
        only: ['lists', 'visibility', 'counts', 'metaTags'],
        getParams: () => ({
            visibility: visibilityFilter === 'all' ? undefined : visibilityFilter,
            per_page: perPage,
            page,
        }),
    });

    async function refreshLists(): Promise<boolean> {
        if (!(await refreshPage(['lists', 'counts', 'metaTags']))) return false;
        if (lists.current_page > lists.last_page) {
            router.get(filterSync.buildPageUrl(lists.last_page), {}, { preserveState: true, preserveScroll: true, replace: true });
        }
        return true;
    }

    const toggleVisibilityAction = useAsyncAction();
    const deleteListAction = useAsyncAction();

    async function handleToggleVisibility(list: VnList) {
        const data = await toggleVisibilityAction.run(
            async () => {
                const result = await toggleVnListVisibility(list.id);
                if (!(await refreshLists())) return null;
                return result;
            },
            { fallbackError: 'Failed to update list visibility' },
        );
        if (!data) return;
        toast.success(data.message || 'List visibility updated successfully.');
    }

    async function handleDelete(list: VnList) {
        const deleted = await deleteListAction.run(
            async () => {
                await destroyVnList(list.id);
                if (!(await refreshLists())) return null;
                return true;
            },
            { fallbackError: 'Failed to delete list' },
        );
        if (!deleted) return;
        toast.success('List deleted successfully.');
    }

    const tabs = $derived([
        {
            key: 'all',
            label: 'All Lists',
            href: route('lists.index', { visibility: undefined, per_page: perPage, page: 1 }),
            count: counts.all,
        },
        {
            key: 'public',
            label: 'Public Lists',
            href: route('lists.index', { visibility: 'public', per_page: perPage, page: 1 }),
            count: counts.public,
        },
        {
            key: 'private',
            label: 'Private Lists',
            href: route('lists.index', { visibility: 'private', per_page: perPage, page: 1 }),
            count: counts.private,
        },
    ]);
</script>

<SeoHead {metaTags} title="Your Visual Novel Lists" />

<div class="space-y-8">
    <PageHeader title="Your Visual Novel Lists">
        {#snippet actions()}
            <Button href={route('lists.public')} variant="outline" tone="neutral">
                <UsersIcon class="h-5 w-5" />
                Public Lists
            </Button>
            <Button href={route('lists.create')}>
                <PlusCircleIcon class="h-5 w-5" />
                New List
            </Button>
        {/snippet}
    </PageHeader>

    <Card variant="flat" padding="lg">
        <TabLinks
            {tabs}
            active={visibilityFilter}
            onSelect={(tab) => {
                visibilityFilter = tab;
                page = 1;
            }}
        />
    </Card>

    {#if lists.data.length > 0}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each lists.data as list (list.id)}
                <VnListCard {list} isOwner={true} showActions={true} onToggleVisibility={handleToggleVisibility} onDelete={handleDelete} />
            {/each}
        </div>
    {:else}
        <EmptyState
            title="No lists found"
            description={visibility === 'all' ? "You haven't created any lists yet." : `No ${visibility} lists found.`}
        >
            <Button href={route('lists.create')}>Create Your First List</Button>
        </EmptyState>
    {/if}

    <Pagination
        layout="full"
        meta={buildPageMeta(lists, lists.data)}
        onChange={(nextPage) => (page = nextPage)}
        onPerPageChange={(nextPerPage) => {
            perPage = nextPerPage;
            page = 1;
        }}
        loading={filterSync.isLoading}
        label="results"
        perPageOptions={[8, 16, 24, 32]}
        buildPageUrl={filterSync.buildPageUrl}
    />
</div>
