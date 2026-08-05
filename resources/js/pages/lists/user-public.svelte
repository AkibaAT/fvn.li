<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ClipboardIcon from '@/components/icons/Clipboard.svelte';
    import UsersIcon from '@/components/icons/Users.svelte';
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

<SeoHead {metaTags} title={`${user.name}'s Visual Novel Lists`} />

<div class="space-y-8">
    <PageHeader title={`${user.name}'s Visual Novel Lists`} class="mb-0">
        {#snippet actions()}
            <Link
                href={route('lists.public')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <UsersIcon class="mr-2 h-5 w-5" />
                All Public Lists
            </Link>
            <Link
                href={route('lists.index')}
                class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700"
            >
                <ClipboardIcon class="mr-2 h-5 w-5" />
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
