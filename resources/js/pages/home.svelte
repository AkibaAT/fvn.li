<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
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
    }

    let { stats, teasers, metaTags, ignoredGameIds = [] }: Props = $props();
    let catalogueDescription = $derived(
        stats
            ? `Browse ${stats.totalGames.toLocaleString()} furry visual novels, get notified about updates, explore routes, and organize your reading lists.`
            : 'Browse furry visual novels, get notified about updates, explore routes, and organize your reading lists.',
    );
</script>

<SeoHead {metaTags} />

<div class="home-page space-y-10">
    <PageHeader title="Furry visual novel catalogue" description={catalogueDescription} descriptionWidth="full" class="mb-0" />

    <!-- Main Content -->
    <div class="space-y-10">
        <!-- Recently Added -->
        {#if teasers?.recentlyAdded?.length}
            <section>
                <div class="mb-6 flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Recently Added</h2>
                    <Link
                        href={route('games.index', { sort: 'first_visible_at', direction: 'desc' })}
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition-colors hover:text-gray-950 dark:text-zinc-400 dark:hover:text-white"
                    >
                        View all
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                            ><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg
                        >
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {#each teasers.recentlyAdded as game (game.id)}
                        <GameCard {game} {ignoredGameIds} />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- Recently Updated -->
        {#if teasers?.recentlyUpdated?.length}
            <section>
                <div class="mb-6 flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Recently Updated</h2>
                    <Link
                        href={route('games.index', { sort: 'latest_version_published_at', direction: 'desc' })}
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition-colors hover:text-gray-950 dark:text-zinc-400 dark:hover:text-white"
                    >
                        View all
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                            ><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg
                        >
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {#each teasers.recentlyUpdated as game (game.id)}
                        <GameCard {game} {ignoredGameIds} />
                    {/each}
                </div>
            </section>
        {/if}

        <!-- Most Popular -->
        {#if teasers?.mostPopular?.length}
            <section>
                <div class="mb-6 flex items-center justify-between gap-4">
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">Most Popular</h2>
                    <Link
                        href={route('games.index', { sort: 'trending', direction: 'desc' })}
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 transition-colors hover:text-gray-950 dark:text-zinc-400 dark:hover:text-white"
                    >
                        View all
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                            ><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg
                        >
                    </Link>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {#each teasers.mostPopular as game (game.id)}
                        <GameCard {game} {ignoredGameIds} />
                    {/each}
                </div>
            </section>
        {/if}
    </div>
</div>
