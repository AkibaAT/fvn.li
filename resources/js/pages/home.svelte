<script lang="ts">
    import GamesGrid from '@/components/games/GamesGrid.svelte';
    import GameUpdateRow from '@/components/games/GameUpdateRow.svelte';
    import ListGroup from '@/components/lists/ListGroup.svelte';
    import { ViewToggle } from '@/components/ui';
    import { VIEW_MODE_COOKIES, parseViewMode, writeViewModeCookie, type ViewMode } from '@/utils/view-mode';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import type { OptimizedScreenshotVariants } from '@/constants/screenshot-variants';
    import type { Game } from '@/types/game';
    import { Link } from '@inertiajs/svelte';

    /**
     * Home teasers carry the display-only author/screenshot extras beside the
     * shared core; `user_progress` keeps the GameCardGame row shape so teasers
     * stay assignable to the GameCard/GameUpdateRow props.
     */
    interface HomeGame extends Game {
        rating?: string;
        authors_html?: string;
        authors_text?: string;
        has_author_links?: boolean;
        screenshots?: Array<{
            url?: string;
            thumbnail_url?: string;
            optimized?: OptimizedScreenshotVariants;
        }>;
        game_engine: string;
        latest_version_number?: string | null;
        user_progress?: Array<{ id: number; game_id: number; user_id: number; receive_updates: boolean }>;
    }

    interface Props {
        stats?: {
            totalGames: number;
            totalRatings: number;
            totalUsers: number;
        };
        teasers?: {
            recentlyAdded: HomeGame[];
            recentlyUpdated: HomeGame[];
            mostPopular: HomeGame[];
        };
        metaTags?: MetaTags;
        ignoredGameIds?: number[];
        /** Persisted catalogue layout, resolved from the `home_view` cookie. */
        homeView?: string;
    }

    let { stats, teasers, metaTags, ignoredGameIds = [], homeView = 'grid' }: Props = $props();

    // The cookie decides the server-rendered layout; the override keeps the toggle instant.
    let viewOverride = $state<ViewMode | null>(null);
    const viewMode = $derived<ViewMode>(viewOverride ?? parseViewMode(homeView));

    function setViewMode(next: ViewMode) {
        viewOverride = next;
        writeViewModeCookie(VIEW_MODE_COOKIES.home, next);
    }

    const catalogueLead = $derived(
        stats ? `A catalogue of ${stats.totalGames.toLocaleString()} furry visual novels.` : 'A catalogue of furry visual novels.',
    );

    const sections = $derived([
        {
            title: 'Recently added',
            linkLabel: 'All new titles',
            games: teasers?.recentlyAdded ?? [],
            href: route('games.index', { sort: 'first_visible_at', direction: 'desc' }),
        },
        {
            title: 'Recently updated',
            linkLabel: 'All updates',
            games: teasers?.recentlyUpdated ?? [],
            href: route('games.index', { sort: 'latest_version_published_at', direction: 'desc' }),
        },
        {
            title: 'Most popular',
            linkLabel: 'Trending',
            games: teasers?.mostPopular ?? [],
            href: route('games.index', { sort: 'trending', direction: 'desc' }),
        },
    ]);

    const hasTeasers = $derived(sections.some((section) => section.games.length > 0));
</script>

<SeoHead {metaTags} />

<div class="home-page">
    <PageHeader
        title={catalogueLead}
        description="Indexed from itch.io and Steam. Follow a title to get notified when it updates."
        descriptionWidth="readable"
        class="mb-12 max-sm:mb-8"
    >
        {#snippet actions()}
            {#if hasTeasers}
                <ViewToggle value={viewMode} onchange={setViewMode} label="Home layout" />
            {/if}
        {/snippet}
    </PageHeader>

    <div class="space-y-10 max-sm:space-y-8">
        {#each sections as section (section.title)}
            {#if section.games.length}
                <section>
                    <div class="mb-3.5 flex items-baseline justify-between gap-4">
                        <h2 class="text-title leading-none font-semibold text-fg">{section.title}</h2>
                        <Link href={section.href} class="text-ui text-fg-muted transition-colors hover:text-fg">{section.linkLabel} →</Link>
                    </div>

                    {#if viewMode === 'list'}
                        <ListGroup class="px-3.5 max-sm:px-2.5">
                            {#each section.games as game (game.id)}
                                <GameUpdateRow {game} />
                            {/each}
                        </ListGroup>
                    {:else}
                        <GamesGrid games={section.games} {ignoredGameIds} />
                    {/if}
                </section>
            {/if}
        {/each}
    </div>
</div>
