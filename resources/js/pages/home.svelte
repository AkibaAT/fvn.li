<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import GameUpdateRow from '@/components/games/GameUpdateRow.svelte';
    import ListGroup from '@/components/lists/ListGroup.svelte';
    import { ViewToggle } from '@/components/ui';
    import { HOME_GRID_TEASER_LIMIT, VIEW_MODE_COOKIES, parseViewMode, writeViewModeCookie, type ViewMode } from '@/utils/view-mode';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import type { OptimizedScreenshotVariants } from '@/constants/screenshot-variants';
    import { Link } from '@inertiajs/svelte';

    interface Game {
        id: number;
        name: string;
        effective_name: string;
        slug: string;
        status: string;
        rating?: string;
        authors?: string;
        authors_html?: string;
        authors_text?: string;
        has_author_links?: boolean;
        description?: string;
        thumb_url?: string;
        optimized_thumbnails?: {
            default?: {
                path: string;
                width?: number;
                height?: number;
            };
        };
        screenshots?: Array<{
            url?: string;
            thumbnail_url?: string;
            optimized?: OptimizedScreenshotVariants;
        }>;
        game_engine: string;
        english_word_count?: number;
        initially_published_at?: string;
        latest_version_published_at?: string;
        latest_version_number?: string | null;
        tags?: Array<{
            id: number;
            name: string;
        }>;
        supported_languages?: Array<{
            iso_code: string;
            ref_name: string;
            flag_code: string;
        }>;
        platform?: 'itch_io' | 'steam' | 'other';
        is_windows?: boolean;
        is_linux?: boolean;
        is_mac?: boolean;
        is_android?: boolean;
        is_web?: boolean;
        is_nsfw?: boolean;
        is_paid?: boolean;
        has_demo?: boolean;
        is_on_sale?: boolean;
        [key: string]: unknown;
    }

    interface Props {
        stats?: {
            totalGames: number;
            totalRatings: number;
            totalUsers: number;
        };
        teasers?: {
            recentlyAdded: Game[];
            recentlyUpdated: Game[];
            mostPopular: Game[];
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

<div class="home-page space-y-10 max-sm:space-y-7">
    <PageHeader
        title={catalogueLead}
        description="Indexed from itch.io and Steam. Follow a title to get notified when it updates."
        descriptionWidth="readable"
        class="mb-0"
    >
        {#snippet actions()}
            {#if hasTeasers}
                <ViewToggle value={viewMode} onchange={setViewMode} label="Home layout" />
            {/if}
        {/snippet}
    </PageHeader>

    {#each sections as section (section.title)}
        {@const visibleGames = viewMode === 'list' ? section.games : section.games.slice(0, HOME_GRID_TEASER_LIMIT)}
        {#if visibleGames.length}
            <section>
                <div class="mb-3.5 flex items-baseline justify-between gap-4">
                    <h2 class="text-[17px] leading-none font-semibold tracking-[-0.015em] text-fg">{section.title}</h2>
                    <Link href={section.href} class="text-[13px] text-fg-muted transition-colors hover:text-fg">{section.linkLabel} →</Link>
                </div>

                {#if viewMode === 'list'}
                    <ListGroup class="px-3.5 max-sm:px-2.5">
                        {#each visibleGames as game (game.id)}
                            <GameUpdateRow {game} />
                        {/each}
                    </ListGroup>
                {:else}
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {#each visibleGames as game (game.id)}
                            <GameCard {game} {ignoredGameIds} />
                        {/each}
                    </div>
                {/if}
            </section>
        {/if}
    {/each}
</div>
