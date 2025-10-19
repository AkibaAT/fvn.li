import {Head, Link} from '@inertiajs/react';

interface GameSummary {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string | null;
    has_additional_links?: boolean;
    platform?: 'itch_io' | 'steam' | 'other';
}

interface GameClickStats {
    page_views_total: number;
    page_views_unique: number;
    external_project_total: number;
    external_project_unique: number;
    custom_link_clicks_total: number;
    custom_link_clicks_unique: number;
}

interface ClickStatsSummary {
    [gameId: string]: GameClickStats;
}

interface Props {
    itchio: {
        username?: string | null;
    };
    games: GameSummary[];
    clickStats: ClickStatsSummary | null;
    metaTags?: { title?: string };
}


export default function MyGamesIndex({
                                         itchio,
                                         games,
                                         clickStats,
                                         metaTags,
                                     }: Props) {
    const hasItchio = !!itchio?.username;

    return (
        <>
            <Head title={metaTags?.title || 'Manage My Games'}/>
            <div className="space-y-8">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold text-blue-600">
                        Manage My Games
                    </h1>
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Dashboard
                    </Link>
                </div>

                {!hasItchio && (
                    <div
                        className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <div className="flex items-center space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500">
                                <span className="text-sm text-white">🎮</span>
                            </div>
                            <div className="flex-1">
                                <div className="font-medium text-yellow-800 dark:text-yellow-300">
                                    Connect your itch.io account to manage your
                                    games
                                </div>
                                <div className="mt-1 text-xs text-yellow-700 dark:text-yellow-400">
                                    After connecting, we’ll show your owned
                                    games here for quick editing and analytics.
                                </div>
                            </div>
                            <Link
                                href={route('auth.redirect', 'itchio')}
                                className="rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700"
                            >
                                Connect itch.io
                            </Link>
                        </div>
                    </div>
                )}

                {hasItchio && (
                    <div
                        className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <div className="flex items-center space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500">
                                <span className="text-sm text-white">🎮</span>
                            </div>
                            <div className="flex-1">
                                <div className="text-blue-800 dark:text-blue-300">
                                    Connected: {itchio.username}.itch.io
                                </div>
                                <div className="mt-0.5 text-xs text-blue-700 dark:text-blue-400">
                                    {games.length}{' '}
                                    {games.length === 1 ? 'game' : 'games'}{' '}
                                    found
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    {games.map((g) => {
                        const gameStats = clickStats?.[g.id.toString()];
                        const totalViews = gameStats?.page_views_unique || 0;
                        const totalDownloads = gameStats?.custom_link_clicks_unique || 0;
                        const itchioVisits = gameStats?.external_project_unique || 0;
                        const thumbnailUrl = g.thumb_url;

                        return (
                            <div
                                key={g.id}
                                className="overflow-hidden rounded-xl border border-gray-200/50 bg-white/70 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70"
                            >
                                <Link href={route('games.show', g.slug)} className="block">
                                    {thumbnailUrl ? (
                                        <img
                                            src={thumbnailUrl}
                                            alt={g.name}
                                            className={`aspect-[4/3] w-full ${g.platform === 'steam' ? 'object-contain' : 'object-cover'} transition-opacity hover:opacity-90`}
                                        />
                                    ) : (
                                        <div
                                            className="flex h-36 w-full items-center justify-center bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600">
                                            <div className="text-center">
                                                <div className="mb-2 text-3xl opacity-50">
                                                    🎮
                                                </div>
                                                <div className="text-sm font-medium">
                                                    No Image
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </Link>
                                <div className="space-y-2 p-4">
                                    <div className="font-semibold text-gray-900 dark:text-white">
                                        {g.name}
                                    </div>
                                    {g.has_additional_links ? (
                                        <div className="text-xs text-green-600 dark:text-green-400">
                                            Has download links
                                        </div>
                                    ) : (
                                        <div className="text-xs text-gray-500 dark:text-gray-400">
                                            No download links
                                        </div>
                                    )}

                                    {/* Analytics Summary */}
                                    {gameStats && (totalViews > 0 || totalDownloads > 0 || itchioVisits > 0) && (
                                        <div className="space-y-1 rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                                            <div className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                                Last 30 days:
                                            </div>
                                            <div
                                                className="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
                                                {totalViews > 0 && (
                                                    <div className="flex items-center gap-1">
                                                        <svg className="h-3 w-3" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round"
                                                                  strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path strokeLinecap="round" strokeLinejoin="round"
                                                                  strokeWidth={2}
                                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        <span>{totalViews}</span>
                                                    </div>
                                                )}
                                                {totalDownloads > 0 && (
                                                    <div className="flex items-center gap-1">
                                                        <svg className="h-3 w-3" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round"
                                                                  strokeWidth={2}
                                                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <span>{totalDownloads}</span>
                                                    </div>
                                                )}
                                                {itchioVisits > 0 && (
                                                    <div className="flex items-center gap-1">
                                                        <svg className="h-3 w-3" fill="none" stroke="currentColor"
                                                             viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round"
                                                                  strokeWidth={2}
                                                                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                        </svg>
                                                        <span>{itchioVisits}</span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <div className="pt-2">
                                        <Link
                                            href={route('my-games.edit', {
                                                game: g.slug,
                                            })}
                                            className="inline-flex items-center space-x-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700"
                                        >
                                            <svg
                                                className="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M11 5h2m-1 14v-4m0 0l-2-2m2 2l2-2M5 13a7 7 0 1114 0 7 7 0 01-14 0z"
                                                />
                                            </svg>
                                            <span>Edit</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {hasItchio && games.length === 0 && (
                    <div className="text-center text-gray-600 dark:text-gray-400">
                        No owned games were detected for your itch.io account.
                    </div>
                )}
            </div>
        </>
    );
}
