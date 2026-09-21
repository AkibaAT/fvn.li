<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ClipboardIcon from '@/components/icons/Clipboard.svelte';
    import UsersIcon from '@/components/icons/Users.svelte';
    import { untrack } from 'svelte';
    import type { ListOwner as User, VnList } from '@/types/lists';
    import PublicListResults from '@/components/lists/PublicListResults.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Button } from '@/components/ui';
    import { useUrlSyncedFilters } from '@/hooks/useUrlSyncedFilters.svelte';

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

    let page = $state(untrack(() => lists.current_page));
    let perPage = $state(untrack(() => lists.per_page));

    const filterSync = useUrlSyncedFilters({
        route: untrack(() => route('lists.user-public', user.id)),
        only: ['lists', 'user', 'metaTags'],
        getParams: () => ({ per_page: perPage, page }),
    });
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
        isLoading={filterSync.isLoading}
        onPageChange={(nextPage) => (page = nextPage)}
        onPerPageChange={(nextPerPage) => {
            perPage = nextPerPage;
            page = 1;
        }}
        buildPageUrl={filterSync.buildPageUrl}
    />
</div>
