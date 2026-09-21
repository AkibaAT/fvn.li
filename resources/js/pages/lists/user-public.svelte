<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ClipboardIcon from '@/components/icons/Clipboard.svelte';
    import UsersIcon from '@/components/icons/Users.svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import type { User, VnList } from '@/components/VnListCard.svelte';
    import PublicListResults from '@/components/lists/PublicListResults.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui';

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
    <PageHeader title={`${user.name}'s Visual Novel Lists`}>
        {#snippet actions()}
            <Button href={route('lists.public')} variant="outline" tone="neutral">
                <UsersIcon class="h-5 w-5" />
                All Public Lists
            </Button>
            <Button href={route('lists.index')}>
                <ClipboardIcon class="h-5 w-5" />
                My Lists
            </Button>
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
