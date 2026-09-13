<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import PlusCircleIcon from '@/components/icons/PlusCircle.svelte';
    import UsersIcon from '@/components/icons/Users.svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import Pagination from '@/components/Pagination.svelte';
    import type { VnList } from '@/components/VnListCard.svelte';
    import VnListCard from '@/components/VnListCard.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { shouldIntercept } from '@inertiajs/core';
    import { toast } from '@/utils/toast';
    import { destroyVnList, toggleVnListVisibility } from '@/api/lists';
    import { Card } from '@/components/ui';

    interface Props {
        lists: { data: VnList[]; current_page: number; last_page: number; per_page: number; total: number };
        visibility: string;
        metaTags?: { title?: string; description?: string };
        counts?: { all: number; public: number; private: number };
    }

    let { lists, visibility, metaTags, counts = { all: 0, public: 0, private: 0 } }: Props = $props();

    let isLoading = $state(false);

    function handleTabChange(newVisibility: string) {
        isLoading = true;
        router.get(
            route('lists.index'),
            {
                visibility: newVisibility === 'all' ? undefined : newVisibility,
                per_page: lists.per_page,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) },
        );
    }

    function handlePageChange(page: number) {
        isLoading = true;
        router.get(
            route('lists.index'),
            {
                visibility: visibility === 'all' ? undefined : visibility,
                per_page: lists.per_page,
                page,
            },
            { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) },
        );
    }

    function handlePerPageChange(perPage: number) {
        isLoading = true;
        router.get(
            route('lists.index'),
            {
                visibility: visibility === 'all' ? undefined : visibility,
                per_page: perPage,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) },
        );
    }

    function buildPageUrl(page: number): string {
        const params = new SvelteURLSearchParams();
        if (visibility && visibility !== 'all') params.set('visibility', visibility);
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists?${params.toString()}`;
    }

    async function refreshLists(): Promise<boolean> {
        if (!(await refreshPage(['lists', 'counts', 'metaTags']))) return false;
        if (lists.current_page > lists.last_page) {
            router.get(buildPageUrl(lists.last_page), {}, { preserveState: true, preserveScroll: true, replace: true });
        }
        return true;
    }

    async function handleToggleVisibility(list: VnList) {
        try {
            const data = await toggleVnListVisibility(list.id);
            if (!(await refreshLists())) return;
            toast.success(data.message || 'List visibility updated successfully.');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to update list visibility');
        }
    }

    async function handleDelete(list: VnList) {
        try {
            await destroyVnList(list.id);
            if (!(await refreshLists())) return;
            toast.success('List deleted successfully.');
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to delete list');
        }
    }

    const tabs = [
        { key: 'all', label: 'All Lists' },
        { key: 'public', label: 'Public Lists' },
        { key: 'private', label: 'Private Lists' },
    ];
</script>

<SeoHead {metaTags} title="Your Visual Novel Lists" />

<div class="space-y-8">
    <PageHeader title="Your Visual Novel Lists">
        {#snippet actions()}
            <Link
                href={route('lists.public')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <UsersIcon class="mr-2 h-5 w-5" />
                Public Lists
            </Link>
            <Link
                href={route('lists.create')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <PlusCircleIcon class="mr-2 h-5 w-5" />
                New List
            </Link>
        {/snippet}
    </PageHeader>

    <Card variant="glass" padding="lg">
        <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700">
            {#each tabs as tab (tab.key)}
                {@const count = counts[tab.key as keyof typeof counts] ?? 0}
                <a
                    href={route('lists.index', { visibility: tab.key === 'all' ? undefined : tab.key, per_page: lists.per_page, page: 1 })}
                    onclick={(e: MouseEvent) => {
                        if (!shouldIntercept(e)) return;
                        e.preventDefault();
                        handleTabChange(tab.key);
                    }}
                    class="rounded-t-lg px-4 py-2 text-sm font-medium transition-colors {visibility === tab.key
                        ? 'border-b-2 border-blue-600 bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200'}"
                >
                    {tab.label} ({count})
                </a>
            {/each}
        </div>
    </Card>

    {#if lists.data.length > 0}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each lists.data as list (list.id)}
                <VnListCard {list} isOwner={true} showActions={true} onToggleVisibility={handleToggleVisibility} onDelete={handleDelete} />
            {/each}
        </div>
    {:else}
        <div class="py-12 text-center">
            <h3 class="mb-2 text-lg font-medium text-gray-900 dark:text-white">No lists found</h3>
            <p class="mb-6 text-gray-600 dark:text-gray-400">
                {visibility === 'all' ? "You haven't created any lists yet." : `No ${visibility} lists found.`}
            </p>
            <Link
                href={route('lists.create')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 font-medium text-white transition-colors hover:bg-blue-700"
                >Create Your First List</Link
            >
        </div>
    {/if}

    <Pagination
        layout="full"
        meta={{
            current_page: lists.current_page,
            last_page: lists.last_page,
            total: lists.total,
            from: lists.data.length ? (lists.current_page - 1) * lists.per_page + 1 : 0,
            to: lists.data.length ? (lists.current_page - 1) * lists.per_page + lists.data.length : 0,
            per_page: lists.per_page,
        }}
        onChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        loading={isLoading}
        label="results"
        perPageOptions={[8, 16, 24, 32]}
        {buildPageUrl}
    />
</div>
