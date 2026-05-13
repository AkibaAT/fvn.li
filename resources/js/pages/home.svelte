<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import type { OptimizedScreenshotVariants } from '@/constants/screenshot-variants';
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

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
    let showHeroCards = $state(false);

    onMount(() => {
        const mediaQuery = window.matchMedia('(min-width: 1024px)');
        const updateHeroCards = () => {
            showHeroCards = mediaQuery.matches;
        };

        updateHeroCards();
        mediaQuery.addEventListener('change', updateHeroCards);

        return () => {
            mediaQuery.removeEventListener('change', updateHeroCards);
        };
    });
</script>

<SeoHead {metaTags} />

<div class="home-page relative dark:bg-[#060a16]">
    <!-- Hero Section -->
    <section class="hero-section relative overflow-hidden">
        <div class="absolute inset-0 bg-white/80 dark:bg-zinc-950/60" style="-webkit-backdrop-filter: blur(14.9px); backdrop-filter: blur(14.9px);">
            <div
                class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(59,130,246,0.15),transparent)] dark:bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(59,130,246,0.3),rgba(255,255,255,0))]"
            ></div>
            <div class="absolute top-1/2 right-0 bottom-0 left-0 bg-gradient-to-t from-white/80 to-transparent dark:from-[#060a16]"></div>
        </div>

        <!-- Animated Orbs -->
        <div
            class="absolute top-1/4 left-1/4 h-64 w-64 animate-pulse rounded-full bg-blue-400/20 blur-[100px] dark:bg-blue-500/35"
            style="animation-duration: 6s;"
        ></div>
        <div
            class="absolute right-1/4 bottom-1/4 h-96 w-96 animate-pulse rounded-full bg-blue-600/25 blur-[120px] dark:bg-blue-700/25"
            style="animation-delay: 2s; animation-duration: 8s;"
        ></div>

        <div class="relative px-4 pt-4 pb-10 sm:px-6 sm:pt-6 lg:px-8 lg:pt-0 lg:pb-12">
            <div class="mx-auto max-w-7xl">
                <div class="grid gap-12 lg:min-h-[780px] lg:grid-cols-2 lg:items-center lg:gap-8">
                    <!-- Left Content -->
                    <div class="flex flex-col justify-center">
                        <h1 class="text-5xl font-bold tracking-tight text-gray-900 sm:text-6xl lg:text-7xl dark:text-white">
                            Your next
                            <br />
                            <span class="text-rose-500 dark:text-rose-400"> obsession </span>
                            is waiting
                        </h1>

                        <p class="mt-6 max-w-lg text-lg text-gray-600 dark:text-zinc-400">
                            Browse a deep catalogue of furry visual novels, compare releases at a glance, and find your next read by platform,
                            language, tags, and rating.
                        </p>

                        <div class="mt-8 flex flex-col gap-4 sm:flex-row">
                            <Link
                                href={route('games.index')}
                                class="inline-flex h-12 items-center justify-center rounded-lg bg-gray-900 px-8 text-sm font-semibold text-white transition-all hover:bg-gray-800 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200"
                            >
                                <i class="icon-magnifier mr-2"></i>
                                Explore Library
                            </Link>

                            <Link
                                href={route('login')}
                                class="inline-flex h-12 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-8 text-sm font-semibold text-gray-900 backdrop-blur-sm transition-all hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                            >
                                Log In
                            </Link>
                        </div>

                        <!-- Quick Stats -->
                        {#if stats}
                            <div class="mt-10 flex gap-8">
                                <div>
                                    <div class="text-3xl font-bold text-gray-900 sm:text-4xl dark:text-white">
                                        {stats.totalGames.toLocaleString()}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-zinc-500">Games</div>
                                </div>
                                <div>
                                    <div class="text-3xl font-bold text-gray-900 sm:text-4xl dark:text-white">
                                        {stats.totalRatings.toLocaleString()}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-zinc-500">Ratings</div>
                                </div>
                                <div>
                                    <div class="text-3xl font-bold text-gray-900 sm:text-4xl dark:text-white">
                                        {stats.totalUsers.toLocaleString()}
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-zinc-500">Readers</div>
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- Right - Featured Game Cards Preview -->
                    <div class="relative hidden lg:flex lg:items-center lg:justify-end" aria-hidden="true" inert>
                        {#if teasers?.recentlyAdded?.[0]}
                            {#if showHeroCards}
                                <div class="relative h-[620px] w-full max-w-[30rem]">
                                    <!-- Card 1 - Back Left -->
                                    {#if teasers.recentlyAdded[1]}
                                        <div class="absolute top-12 left-2 w-64 scale-90 rotate-[-8deg] transform opacity-60">
                                            <GameCard game={teasers.recentlyAdded[1]} fixedHeight={true} {ignoredGameIds} />
                                        </div>
                                    {/if}
                                    <!-- Card 2 - Front Center -->
                                    <div class="absolute top-0 left-[4.5rem] z-20 w-64 rotate-[3deg] transform shadow-2xl">
                                        <GameCard game={teasers.recentlyAdded[0]} fixedHeight={true} {ignoredGameIds} />
                                    </div>
                                    <!-- Card 3 - Back Right -->
                                    {#if teasers.recentlyAdded[2]}
                                        <div class="absolute top-16 right-0 w-64 scale-90 rotate-[10deg] transform opacity-60">
                                            <GameCard game={teasers.recentlyAdded[2]} fixedHeight={true} {ignoredGameIds} />
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="relative bg-gray-50 dark:bg-transparent">
        <!-- Recently Added -->
        {#if teasers?.recentlyAdded?.length}
            <section class="px-4 pt-12 pb-4 sm:px-6 sm:pt-16 sm:pb-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-6 flex items-end justify-between">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Recently Added</h2>
                            <div class="mt-1.5 h-1 w-8 rounded-full bg-purple-500 dark:bg-purple-400"></div>
                        </div>
                        <Link
                            href={route('games.index', { sort: 'first_visible_at', direction: 'desc' })}
                            class="inline-flex items-center gap-1 rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-700 shadow-sm transition-all hover:bg-purple-100 dark:border-purple-500/30 dark:bg-purple-500/10 dark:text-purple-400 dark:hover:bg-purple-500/20"
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
                </div>
            </section>
        {/if}

        <!-- Recently Updated -->
        {#if teasers?.recentlyUpdated?.length}
            <section class="px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-6 flex items-end justify-between">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Recently Updated</h2>
                            <div class="mt-1.5 h-1 w-8 rounded-full bg-emerald-500 dark:bg-emerald-400"></div>
                        </div>
                        <Link
                            href={route('games.index', { sort: 'latest_version_published_at', direction: 'desc' })}
                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm transition-all hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20"
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
                </div>
            </section>
        {/if}

        <!-- Most Popular -->
        {#if teasers?.mostPopular?.length}
            <section class="px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-6 flex items-end justify-between">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Most Popular</h2>
                            <div class="mt-1.5 h-1 w-8 rounded-full bg-amber-500 dark:bg-amber-400"></div>
                        </div>
                        <Link
                            href={route('games.index', { sort: 'trending', direction: 'desc' })}
                            class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 shadow-sm transition-all hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400 dark:hover:bg-amber-500/20"
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
                </div>
            </section>
        {/if}

        <!-- Features Grid -->
        <section class="px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Built for readers</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-zinc-400">
                        Everything you need to track, discover, and enjoy visual novels.
                    </p>
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Search -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-purple-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-purple-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400"
                        >
                            <i class="icon-magnifier text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Smart Search</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            Filter by genre, platform, language, and content rating to find exactly what you want.
                        </p>
                    </div>

                    <!-- Ratings -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-amber-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-amber-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"
                        >
                            <i class="icon-star text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Community Ratings</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            See what fellow readers think with detailed ratings and reviews from enthusiasts.
                        </p>
                    </div>

                    <!-- Lists -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-emerald-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-emerald-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                        >
                            <i class="icon-books text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reading Lists</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            Create unlimited lists to track completed, playing, and planned visual novels.
                        </p>
                    </div>

                    <!-- Platforms -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-blue-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-blue-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400"
                        >
                            <i class="icon-laptop text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Multi-Platform</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">Find games for Windows, Mac, Linux, Android, and web browsers.</p>
                    </div>

                    <!-- Progress -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-rose-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-rose-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400"
                        >
                            <i class="icon-bookmark text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Progress Tracking</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            Track your reading progress and never lose your place in long visual novels.
                        </p>
                    </div>

                    <!-- Updates -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-8 transition-all hover:border-cyan-300 dark:border-zinc-800 dark:bg-zinc-900/50 dark:hover:border-cyan-500/30"
                    >
                        <div
                            class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400"
                        >
                            <i class="icon-bell text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Update Alerts</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-zinc-400">
                            Get notified when games in your lists receive new chapters or updates.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="relative overflow-hidden">
            <div
                class="absolute inset-0 bg-white/80 dark:bg-zinc-950/60"
                style="-webkit-backdrop-filter: blur(14.9px); backdrop-filter: blur(14.9px);"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(59,130,246,0.15),transparent)] dark:bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(59,130,246,0.3),rgba(255,255,255,0))]"
                ></div>
                <div class="absolute top-0 right-0 bottom-1/2 left-0 bg-gradient-to-b from-[#060a16] to-transparent"></div>
            </div>

            <!-- Animated Orbs -->
            <div
                class="absolute top-1/4 right-1/4 h-64 w-64 animate-pulse rounded-full bg-blue-400/20 blur-[100px] dark:bg-blue-500/20"
                style="animation-duration: 6s;"
            ></div>
            <div
                class="absolute bottom-1/4 left-1/4 h-96 w-96 animate-pulse rounded-full bg-blue-600/10 blur-[120px] dark:bg-blue-700/10"
                style="animation-delay: 2s; animation-duration: 8s;"
            ></div>

            <div class="relative px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Start your reading journey</h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-gray-600 dark:text-zinc-400">
                        Join thousands of visual novel enthusiasts and discover your next favorite story.
                    </p>

                    <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href={route('games.index')}
                            class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-6 text-sm font-semibold text-gray-700 shadow-sm transition-all hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            <i class="icon-magnifier mr-2"></i>
                            Browse Library
                        </Link>

                        <Link
                            href={route('login')}
                            class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-6 text-sm font-semibold text-gray-700 shadow-sm transition-all hover:bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                        >
                            Log In
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
