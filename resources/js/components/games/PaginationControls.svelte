<script lang="ts">
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import type { PaginationMeta } from '@/components/Pagination.svelte';
    import type { CurrentFilters } from '@/types';

    interface Props {
        meta: PaginationMeta;
        currentFilters: CurrentFilters;
        updateFilters: (filters: Partial<CurrentFilters>) => void;
    }

    let { meta, currentFilters, updateFilters }: Props = $props();

    function handlePageChange(page: number) {
        updateFilters({ page });
    }

    function handlePerPageChange(perPage: number) {
        updateFilters({ perPage });
    }

    function buildPageUrl(page: number): string {
        const params = new SvelteURLSearchParams();

        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.selectedPlatforms?.length) {
            currentFilters.selectedPlatforms.forEach((p) => params.append('platform[]', p));
        }
        if (currentFilters.selectedLanguages?.length) {
            currentFilters.selectedLanguages.forEach((l) => params.append('language[]', l));
        }
        if (currentFilters.selectedTags?.length) {
            currentFilters.selectedTags.forEach((t) => params.append('tag[]', t));
        }
        if (currentFilters.selectedStatuses?.length) {
            currentFilters.selectedStatuses.forEach((s) => params.append('status[]', s));
        }
        if (currentFilters.selectedEngines?.length) {
            currentFilters.selectedEngines.forEach((e) => params.append('engine[]', e));
        }
        if (currentFilters.selectedGameJams?.length) {
            currentFilters.selectedGameJams.forEach((j) => params.append('gameJam[]', j));
        }
        if (currentFilters.nsfw) params.set('nsfw', '1');
        if (currentFilters.showPaid) params.set('showPaid', '1');
        if (currentFilters.showDemo) params.set('showDemo', '1');
        if (currentFilters.sort) params.set('sort', currentFilters.sort);
        if (currentFilters.direction) params.set('direction', currentFilters.direction);
        if (currentFilters.perPage) params.set('perPage', currentFilters.perPage.toString());
        params.set('page', page.toString());

        return `/games?${params.toString()}`;
    }
</script>

<AdvancedPagination
    {meta}
    label="results"
    onPageChange={handlePageChange}
    onPerPageChange={handlePerPageChange}
    perPageOptions={[8, 16, 24, 32]}
    {buildPageUrl}
/>
