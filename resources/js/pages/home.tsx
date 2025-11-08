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
            <div className="space-y-16">
                {/* Hero Section */}
                <div className="relative overflow-hidden">
                    <div
                        className="absolute inset-0 rounded-3xl bg-blue-600/10"></div>
                    <div
                        className="relative rounded-3xl border border-gray-200/50 bg-white/50 p-12 text-center shadow-xl backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/50">
                        <h1 className="mb-6 text-5xl font-bold text-blue-600 dark:text-blue-400 lg:text-7xl">
                            Welcome to FVN.li
                        </h1>
                        <p className="mx-auto mb-8 max-w-4xl text-xl leading-relaxed text-gray-600 lg:text-2xl dark:text-gray-300">
                            Discover and explore the world of furry visual
                            novels. Find your next favorite story with our
                            comprehensive database and community features.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            <Link
                                href={route('games.index')}
                                className="inline-flex items-center space-x-2 rounded-xl bg-blue-700 px-8 py-4 text-lg font-semibold text-white shadow-lg transition-all duration-200 hover:bg-blue-800 hover:shadow-xl"
                            >
                                <span>🎮</span>
                                <span>Browse Games</span>
                            </Link>
                            {/* Legacy Livewire link removed */}
                        </div>
                    </div>
                </div>

                {/* Stats Section */}
                {stats && (
                    <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
                        <div
                            className="rounded-2xl border border-gray-200/50 bg-white/70 p-8 text-center shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="mb-2 text-4xl font-bold text-blue-600 dark:text-blue-400">
                                {stats.totalGames.toLocaleString()}
                            </div>
                            <div className="font-medium text-gray-600 dark:text-gray-300">
                                Visual Novels
                            </div>
                        </div>
                        <div
                            className="rounded-2xl border border-gray-200/50 bg-white/70 p-8 text-center shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="mb-2 text-4xl font-bold text-emerald-600 dark:text-emerald-400">
                                {stats.totalRatings.toLocaleString()}
                            </div>
                            <div className="font-medium text-gray-600 dark:text-gray-300">
                                Community Ratings
                            </div>
                        </div>
                        <div
                            className="rounded-2xl border border-gray-200/50 bg-white/70 p-8 text-center shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                            <div className="mb-2 text-4xl font-bold text-amber-600 dark:text-amber-400">
                                {stats.totalUsers.toLocaleString()}
                            </div>
                            <div className="font-medium text-gray-600 dark:text-gray-300">
                                Registered Users
                            </div>
                        </div>
                    </div>
                )}

                {/* Game Teasers */}
                {teasers && (
                    <div className="space-y-16">
                        {/* Recently Added Games */}
                        <div>
                            <div className="mb-8 flex items-center justify-between">
                                <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
                                    Recently Added
                                </h2>
                                <Link
                                    href={route('games.index', {
                                        sort: 'first_visible_at',
                                        direction: 'desc',
                                    })}
                                    className="inline-flex items-center space-x-2 rounded-lg bg-blue-100 px-4 py-2 font-medium text-blue-700 transition-colors duration-200 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                >
                                    <span>View All</span>
                                    <span>→</span>
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {teasers.recentlyAdded.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds}/>
                                ))}
                            </div>
                        </div>

                        {/* Recently Updated Games */}
                        <div>
                            <div className="mb-8 flex items-center justify-between">
                                <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
                                    Recently Updated
                                </h2>
                                <Link
                                    href={route('games.index', {
                                        sort: 'latest_version_published_at',
                                        direction: 'desc',
                                    })}
                                    className="inline-flex items-center space-x-2 rounded-lg bg-blue-100 px-4 py-2 font-medium text-blue-700 transition-colors duration-200 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50"
                                >
                                    <span>View All</span>
                                    <span>→</span>
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {teasers.recentlyUpdated.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds}/>
                                ))}
                            </div>
                        </div>

                        {/* Most Popular Games */}
                        <div>
                            <div className="mb-8 flex items-center justify-between">
                                <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
                                    Most Popular
                                </h2>
                                <Link
                                    href={route('games.index', {
                                        sort: 'trending',
                                        direction: 'desc',
                                    })}
                                    className="inline-flex items-center space-x-2 rounded-lg bg-green-100 px-4 py-2 font-medium text-green-700 transition-colors duration-200 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50"
                                >
                                    <span>View All</span>
                                    <span>→</span>
                                </Link>
                            </div>
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {teasers.mostPopular.map((game) => (
                                    <GameCard key={game.id} game={game} ignoredGameIds={ignoredGameIds}/>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* Features Section */}
                <div className="grid grid-cols-1 gap-12 lg:grid-cols-2">
                    <div className="space-y-8">
                        <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
                            Discover Amazing Stories
                        </h2>
                        <div className="space-y-6">
                            <div className="flex items-start space-x-4">
                                <div
                                    className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                    <span className="text-2xl">🔍</span>
                                </div>
                                <div>
                                    <h3 className="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                                        Advanced Search & Filters
                                    </h3>
                                    <p className="text-gray-600 dark:text-gray-300">
                                        Find exactly what you're looking for
                                        with our comprehensive filtering system.
                                        Search by genre, platform, language, and
                                        more.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-4">
                                <div
                                    className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                    <span className="text-2xl">⭐</span>
                                </div>
                                <div>
                                    <h3 className="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                                        Community Ratings
                                    </h3>
                                    <p className="text-gray-600 dark:text-gray-300">
                                        See what the community thinks with
                                        ratings and reviews from fellow
                                        visual novel enthusiasts.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-8">
                        <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
                            Join Our Community
                        </h2>
                        <div className="space-y-6">
                            <div className="flex items-start space-x-4">
                                <div
                                    className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                                    <span className="text-2xl">📚</span>
                                </div>
                                <div>
                                    <h3 className="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                                        Personal Lists
                                    </h3>
                                    <p className="text-gray-600 dark:text-gray-300">
                                        Create and manage your own reading
                                        lists. Keep track of what you've played,
                                        want to play, and favorites.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="flex items-start space-x-4">
                            <div
                                className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                                <span className="text-2xl">📱</span>
                            </div>
                            <div>
                                <h3 className="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                                    Multi-Platform Support
                                </h3>
                                <p className="text-gray-600 dark:text-gray-300">
                                    Find games available on Windows, Mac, Linux,
                                    Android, and web browsers. Never miss out on
                                    a great story.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Call to Action */}
                <div className="text-center">
                    <div
                        className="rounded-3xl bg-blue-600/10 p-12">
                        <h2 className="mb-4 text-3xl font-bold text-gray-900 lg:text-4xl dark:text-white">
                            Ready to Start Your Journey?
                        </h2>
                        <p className="mx-auto mb-8 max-w-2xl text-xl text-gray-600 dark:text-gray-300">
                            Join thousands of visual novel fans and discover
                            your next favorite story today.
                        </p>
                        <Link
                            href={route('games.index')}
                            className="inline-flex items-center space-x-2 rounded-xl bg-blue-700 px-10 py-5 text-xl font-semibold text-white shadow-lg transition-all duration-200 hover:bg-blue-800 hover:shadow-xl"
                        >
                            <span>🚀</span>
                            <span>Explore Games Now</span>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
