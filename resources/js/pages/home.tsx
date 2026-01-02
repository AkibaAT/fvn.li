import GameCard from '@/components/game-card';
import type {OptimizedScreenshotVariants} from '@/constants/screenshot-variants';
import {Link} from '@inertiajs/react';
import SeoHead, {type MetaTags} from '@/components/seo/SeoHead';

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

interface HomeProps {
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

export default function Home({stats, teasers, metaTags, ignoredGameIds = []}: HomeProps) {
    return (
        <>
            <SeoHead metaTags={metaTags} />

            {/* Hero Section */}
            <section className="hero-stage relative -mx-4 -mt-8 mb-12 overflow-hidden px-4 pt-12 pb-16 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 lg:pt-16 lg:pb-20">
                <div className="hero-grid" />

                <div className="relative mx-auto max-w-6xl">
                    <div className="grid items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                        <div className="space-y-5 text-left">
                            <h1 className="text-4xl font-semibold leading-tight text-[var(--color-ui-text)] sm:text-5xl lg:text-6xl">
                                The furry visual novel index
                            </h1>

                            <p className="max-w-xl text-base text-[var(--color-ui-text-muted)] sm:text-lg">
                                Find games, track what you have read, and see what is new.
                            </p>

                            {stats && (
                                <div className="flex flex-wrap items-center gap-6 text-sm text-[var(--color-ui-text-muted)]">
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-xl font-semibold text-[var(--color-link)]">{stats.totalGames.toLocaleString()}</span>
                                        <span>games</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-xl font-semibold text-[var(--color-brand-secondary)]">{stats.totalRatings.toLocaleString()}</span>
                                        <span>ratings</span>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <span className="text-xl font-semibold text-[var(--color-brand-accent)]">{stats.totalUsers.toLocaleString()}</span>
                                        <span>readers</span>
                                    </div>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-4">
                                <Link
                                    href={route('games.index')}
                                    className="hero-cta group inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-black/15 transition-all hover:-translate-y-0.5 hover:brightness-95"
                                >
                                    Browse All Games
                                    <svg className="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </Link>

                                <Link
                                    href={route('games.index', { sort: 'trending', direction: 'desc' })}
                                    className="group inline-flex items-center gap-2 rounded-full bg-[var(--color-brand-accent)] px-6 py-3 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-[var(--color-brand-accent-dark)]"
                                >
                                    <svg className="h-4 w-4 transition-transform group-hover:scale-110" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" />
                                    </svg>
                                    Popular Now
                                </Link>
                            </div>

                        </div>

                        <div className="section-surface hero-panel relative rounded-[28px] p-6">
                            <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.2em] text-[var(--color-ui-text-muted)]">
                                <span>At a glance</span>
                            </div>

                            <div className="mt-6 grid gap-4">
                                <div className="flex items-start gap-3 rounded-2xl bg-[var(--color-ui-surface-alt)] p-4 text-sm text-[var(--color-ui-text-muted)]">
                                    <span className="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-brand-primary)] text-white">
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p className="font-semibold text-[var(--color-ui-text)]">Filter quickly</p>
                                        <p>Filter by platform, language, length, and tags.</p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3 rounded-2xl bg-[var(--color-ui-surface-alt)] p-4 text-sm text-[var(--color-ui-text-muted)]">
                                    <span className="mt-1 inline-flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-brand-secondary)] text-white">
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3" />
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 2a10 10 0 100 20 10 10 0 000-20z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p className="font-semibold text-[var(--color-ui-text)]">Stay organized</p>
                                        <p>Lists and updates in one place.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Main Content */}
            <div className="relative">
                {teasers && (
                    <div className="space-y-12 pb-12">
                        {/* Recently Added */}
                        <section className="section-surface rounded-3xl p-6 sm:p-8">
                            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="h-8 w-1 rounded-full bg-[var(--color-brand-primary)]" />
                                    <h2 className="text-xl font-semibold text-[var(--color-ui-text)]">
                                        Recently Added
                                    </h2>
                                </div>
                                <Link
                                    href={route('games.index', {
                                        sort: 'first_visible_at',
                                        direction: 'desc',
                                    })}
                                    className="text-sm font-medium text-[var(--color-brand-secondary)] transition-colors hover:text-[var(--color-brand-accent)]"
                                >
                                    View all →
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {teasers.recentlyAdded.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds} />
                                ))}
                            </div>
                        </section>

                        {/* Recently Updated */}
                        <section className="section-surface rounded-3xl p-6 sm:p-8">
                            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="h-8 w-1 rounded-full bg-[var(--color-brand-secondary)]" />
                                    <h2 className="text-xl font-semibold text-[var(--color-ui-text)]">
                                        Recently Updated
                                    </h2>
                                </div>
                                <Link
                                    href={route('games.index', {
                                        sort: 'latest_version_published_at',
                                        direction: 'desc',
                                    })}
                                    className="text-sm font-medium text-[var(--color-brand-secondary)] transition-colors hover:text-[var(--color-brand-accent)]"
                                >
                                    View all →
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {teasers.recentlyUpdated.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds} />
                                ))}
                            </div>
                        </section>

                        {/* Most Popular */}
                        <section className="section-surface rounded-3xl p-6 sm:p-8">
                            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3">
                                    <div className="h-8 w-1 rounded-full bg-[var(--color-brand-accent)]" />
                                    <h2 className="text-xl font-semibold text-[var(--color-ui-text)]">
                                        Most Popular
                                    </h2>
                                </div>
                                <Link
                                    href={route('games.index', {
                                        sort: 'trending',
                                        direction: 'desc',
                                    })}
                                    className="text-sm font-medium text-[var(--color-brand-accent)] transition-colors hover:text-[var(--color-link)]"
                                >
                                    View all →
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                {teasers.mostPopular.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds} />
                                ))}
                            </div>
                        </section>
                    </div>
                )}

                {/* Features Section */}
                <section className="section-surface mb-12 rounded-3xl p-8">
                    <div className="mb-8 text-center">
                        <h2 className="text-2xl font-semibold text-[var(--color-ui-text)]">
                            Everything you need, in one place
                        </h2>
                        <p className="mt-2 text-sm text-[var(--color-ui-text-muted)]">
                            Simple tools to browse, rate, and keep tabs on releases.
                        </p>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-2xl bg-[var(--color-ui-surface)] p-5 text-left shadow-sm">
                            <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-brand-primary)] text-white shadow-md shadow-black/10">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <h3 className="mb-1 text-sm font-semibold text-[var(--color-ui-text)]">Discover</h3>
                            <p className="text-xs text-[var(--color-ui-text-muted)]">Filter by tags, platform, language, and length.</p>
                        </div>

                        <div className="rounded-2xl bg-[var(--color-ui-surface)] p-5 text-left shadow-sm">
                            <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-brand-secondary)] text-white shadow-md shadow-black/10">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                            </div>
                            <h3 className="mb-1 text-sm font-semibold text-[var(--color-ui-text)]">Rate</h3>
                            <p className="text-xs text-[var(--color-ui-text-muted)]">See ratings gathered in one place.</p>
                        </div>

                        <div className="rounded-2xl bg-[var(--color-ui-surface)] p-5 text-left shadow-sm">
                            <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-brand-accent)] text-white shadow-md shadow-black/10">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                                </svg>
                            </div>
                            <h3 className="mb-1 text-sm font-semibold text-[var(--color-ui-text)]">Track</h3>
                            <p className="text-xs text-[var(--color-ui-text-muted)]">Make lists and log your progress.</p>
                        </div>

                        <div className="rounded-2xl bg-[var(--color-ui-surface)] p-5 text-left shadow-sm">
                            <div className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-brand-primary-light)] text-white shadow-md shadow-black/10">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            <h3 className="mb-1 text-sm font-semibold text-[var(--color-ui-text)]">Updates</h3>
                            <p className="text-xs text-[var(--color-ui-text-muted)]">See what is new for games you follow.</p>
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="mb-8 text-center">
                    <p className="text-[var(--color-ui-text-muted)]">
                        Ready to explore?{' '}
                        <Link
                            href={route('games.index')}
                            className="font-semibold text-[var(--color-link)] underline decoration-[var(--color-brand-secondary)] underline-offset-4 hover:text-[var(--color-link-hover)]"
                        >
                            Browse the full collection
                        </Link>
                    </p>
                </section>
            </div>
        </>
    );
}
