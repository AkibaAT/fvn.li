<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import GamesGrid from '@/components/games/GamesGrid.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { Button } from '@/components/ui';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import type { TagSummary } from '@/types/tags';

    interface Props {
        tag: TagSummary & { id: number };
        games: {
            data: GameCardGame[];
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
        relatedTags: TagSummary[];
        catalogueUrl: string;
        ignoredGameIds?: number[];
        metaTags: MetaTags;
    }

    let { tag, games, relatedTags, catalogueUrl, ignoredGameIds = [], metaTags }: Props = $props();

    const pageUrl = (page: number) => {
        const base = route('tags.show', tag.slug);
        return page > 1 ? `${base}?page=${page}` : base;
    };

    const meta = $derived({
        current_page: games.current_page,
        last_page: games.last_page,
        per_page: games.per_page,
        total: games.total,
        from: games.from ?? 0,
        to: games.to ?? 0,
    });
</script>

<SeoHead {metaTags} />

<div class="space-y-6">
    <PageHeader
        title="{tag.name} visual novels"
        count="{tag.games_count.toLocaleString()} titles"
        description="Furry visual novels tagged {tag.name}, most popular first."
        backHref={route('tags.index')}
        backLabel="All tags"
        class="mb-6"
    >
        {#snippet actions()}
            <Button href={catalogueUrl} variant="outline" tone="neutral" size="sm">Refine in catalogue</Button>
        {/snippet}
    </PageHeader>

    <GamesGrid games={games.data} {ignoredGameIds} />

    <Pagination layout="pages" {meta} label="games" onChange={(page) => router.visit(pageUrl(page))} buildPageUrl={pageUrl} />

    {#if relatedTags.length > 0}
        <section aria-labelledby="related-tags-heading" class="border-t border-border pt-6">
            <h2 id="related-tags-heading" class="mb-3 text-title font-semibold text-fg">Related tags</h2>
            <ul class="flex flex-wrap gap-x-4 gap-y-2 text-ui">
                {#each relatedTags as related (related.slug)}
                    <li>
                        <Link href={route('tags.show', related.slug)} class="text-fg hover:underline">{related.name}</Link>
                        <span class="text-fg-faint">{related.games_count}</span>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
