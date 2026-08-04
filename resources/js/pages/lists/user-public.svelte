<script lang="ts">
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import type { User, VnList } from '@/components/VnListCard.svelte';
    import PublicListResults from '@/components/lists/PublicListResults.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Link, router } from '@inertiajs/svelte';

    interface Props {
        lists: {
            data: VnList[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        user: User;
        metaTags?: {
            title?: string;
            description?: string;
        };
    }

    let { lists, user, metaTags }: Props = $props();
    let isLoading = $state(false);

    function handlePageChange(page: number) {
        isLoading = true;
        router.get(
            route('lists.user-public', { user: user.id, page }),
            {},
            { preserveState: true, preserveScroll: true, onFinish: () => (isLoading = false) },
        );
    }

    function handlePerPageChange(perPage: number) {
        isLoading = true;
        router.get(route('lists.user-public', user.id), { per_page: perPage, page: 1 }, { preserveState: true, onFinish: () => (isLoading = false) });
    }

    function buildPageUrl(page: number): string {
        const params = new SvelteURLSearchParams();
        params.set('per_page', lists.per_page.toString());
        params.set('page', page.toString());
        return `/lists/user/${user.id}?${params.toString()}`;
    }
</script>

<svelte:head>
    <title>{metaTags?.title || `${user.name}'s Visual Novel Lists`}</title>
</svelte:head>

<div class="space-y-8">
    <PageHeader title={`${user.name}'s Visual Novel Lists`} class="mb-0">
        {#snippet actions()}
            <Link
                href={route('lists.public')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                </svg>
                All Public Lists
            </Link>
            <Link
                href={route('lists.index')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                    />
                </svg>
                My Lists
            </Link>
        {/snippet}
    </PageHeader>

    <PublicListResults
        {lists}
        emptyMessage="This user has no public lists."
        {isLoading}
        onPageChange={handlePageChange}
        onPerPageChange={handlePerPageChange}
        {buildPageUrl}
    />
</div>
