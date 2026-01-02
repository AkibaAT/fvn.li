import AdvancedPagination from '@/components/advanced-pagination';
import Stars from '@/components/ui/stars';
import {Link, router} from '@inertiajs/react';
import {useEffect, useMemo, useRef, useState} from 'react';
import SeoHead, {type MetaTags} from '@/components/seo/SeoHead';
import ReviewTextControls, {useReviewTextStyles} from '@/components/review-text-controls';

type Rater = {
    id: number;
    name: string;
    avatar?: string | null;
    bio?: string | null;
    joined_at?: string | null;
    ratings_count?: number;
    average_score?: number | null;
};

type RaterRating = {
    id: number;
    rating: number;
    published_at: string | null;
    is_reviewed: boolean;
    review?: string | null;
    event_id?: number | null;
    is_visible: boolean;
    game: { id: number; name: string; slug: string; url?: string | null; is_visible?: boolean };
};

type RatingDistribution = {
    [key: number]: number;
};

type Stats = {
    first_rating?: string;
    latest_rating?: string;
    all_games: {
        total_ratings: number;
        reviewed_count: number;
        review_percentage: number;
        average_rating: number;
        unique_games: number;
        rating_distribution: RatingDistribution;
    };
    visible_games: {
        total_ratings: number;
        reviewed_count: number;
        review_percentage: number;
        average_rating: number;
        unique_games: number;
        rating_distribution: RatingDistribution;
    };
};

type PhraseContext = {
    slug: string;
    rating: number;
    sentences: string[];
};

type PhraseData = {
    count: number;
    length: number;
    avg_rating: number;
    contexts: {
        [gameName: string]: PhraseContext;
    };
    related: {
        phrase: string;
        count: number;
        avg_rating: number;
    }[];
};

type Phrases = {
    [phrase: string]: PhraseData;
};

type RaterShowProps = {
    pageTitle?: string;
    rater: Rater;
    ratings?: {
        data: RaterRating[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats?: Stats;
    phrases?: Phrases;
    previousRatingCounts?: Record<number, number>;
    filters?: {
        showOnlyReviews: boolean;
        showOnlyVisibleGames: boolean;
        sortField: 'published_at' | 'rating';
        sortDirection: 'asc' | 'desc';
        page: number;
        perPage: number;
    };
    metaTags?: MetaTags;
};

export default function RaterShow({
                                      rater,
                                      ratings,
                                      stats,
                                      phrases,
                                      previousRatingCounts = {},
                                      filters,
                                      metaTags,
                                  }: RaterShowProps) {
    const defaultStats: Stats = {
        first_rating: undefined,
        latest_rating: undefined,
        all_games: {
            total_ratings: 0,
            reviewed_count: 0,
            review_percentage: 0,
            average_rating: 0,
            unique_games: 0,
            rating_distribution: {1: 0, 2: 0, 3: 0, 4: 0, 5: 0},
        },
        visible_games: {
            total_ratings: 0,
            reviewed_count: 0,
            review_percentage: 0,
            average_rating: 0,
            unique_games: 0,
            rating_distribution: {1: 0, 2: 0, 3: 0, 4: 0, 5: 0},
        },
    };
    const safeStats = stats ?? defaultStats;
    const safePhrases: Phrases = useMemo(() => phrases ?? {}, [phrases]);
    const [selectedPhrase, setSelectedPhrase] = useState<string | null>(null);
    const [showContext, setShowContext] = useState(false);
    const [showOnlyReviews] = useState(filters?.showOnlyReviews ?? true);
    const [showOnlyVisibleGames] = useState(filters?.showOnlyVisibleGames ?? false);
    const [sortField] = useState<'published_at' | 'rating'>(
        (filters?.sortField as 'published_at' | 'rating') ?? 'published_at',
    );
    const [sortDirection] = useState<'asc' | 'desc'>(
        (filters?.sortDirection as 'asc' | 'desc') ?? 'desc',
    );
    const [page, setPage] = useState<number>(filters?.page ?? 1);
    const [perPage, setPerPage] = useState<number>(filters?.perPage ?? 10);
    const [isLoading, setIsLoading] = useState(false);
    const [historyModal, setHistoryModal] = useState<{
        gameName: string;
        ratings: RaterRating[];
        open: boolean;
    }>({gameName: '', ratings: [], open: false});
    const historyDialogRef = useRef<HTMLDialogElement>(null);
    const historyCloseBtnRef = useRef<HTMLButtonElement>(null);
    const historyOpenerRef = useRef<HTMLElement | null>(null);

    // Use the review text styles hook
    const reviewStyles = useReviewTextStyles();

    // Phrases dialog refs for focus management and control
    const phrasesDialogRef = useRef<HTMLDialogElement>(null);
    const phrasesCloseBtnRef = useRef<HTMLButtonElement>(null);
    const phrasesOpenerRef = useRef<HTMLElement | null>(null);

    // Recompute when filters change: update URL and data, but don't mutate URL on first render
    const didMountRef = useRef(false);
    useEffect(() => {
        // Skip the initial mount to avoid adding query params when first opening the page
        if (!didMountRef.current) {
            didMountRef.current = true;
            return;
        }

        // Build desired params
        const desired = new URLSearchParams({
            page: String(page),
            perPage: String(perPage),
            showOnlyReviews: String(showOnlyReviews),
            showOnlyVisibleGames: String(showOnlyVisibleGames),
            sortField,
            sortDirection,
        });

        // Compare with current URL; if already matches, do nothing
        const current = new URLSearchParams(window.location.search);
        if (desired.toString() === current.toString()) return;

        setIsLoading(true);
        router.get(
            route('raters.show', rater.id),
            Object.fromEntries(desired.entries()),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true, // avoid history spam on each toggle/sort change
                only: ['ratings', 'previousRatingCounts', 'filters'],
                onFinish: () => setIsLoading(false),
            },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, perPage, showOnlyReviews, showOnlyVisibleGames, sortField, sortDirection]);

    const ratingMeta = useMemo(
        () =>
            ratings
                ? {
                    current_page: ratings.current_page,
                    last_page: ratings.last_page,
                    per_page: ratings.per_page,
                    total: ratings.total,
                }
                : {current_page: 1, last_page: 0, per_page: perPage, total: 0},
        [ratings, perPage],
    );

    // Build SSR-friendly URLs for pagination
    const buildPageUrl = (pageNum: number): string => {
        const params = new URLSearchParams();
        params.set('page', pageNum.toString());
        params.set('perPage', perPage.toString());
        params.set('showOnlyReviews', String(showOnlyReviews));
        params.set('showOnlyVisibleGames', String(showOnlyVisibleGames));
        params.set('sortField', sortField);
        params.set('sortDirection', sortDirection);
        return `/raters/${rater.id}?${params.toString()}`;
    };

    // Sorting toggles are handled elsewhere; no-op placeholder removed

    const openHistory = async (gameId: number, gameName: string) => {
        setIsLoading(true);
        try {
            const res = await fetch(route('raters.games.history', {rater: rater.id, game: gameId}));
            const json = await res.json();
            setHistoryModal({gameName, ratings: json.ratings, open: true});
        } finally {
            setIsLoading(false);
        }
    };

    const closeHistory = () => setHistoryModal((s) => ({...s, open: false}));

    // Open/close native dialog for rating history
    useEffect(() => {
        const dialog = historyDialogRef.current;
        if (!dialog) return;
        if (historyModal.open) {
            historyOpenerRef.current = (document.activeElement as HTMLElement) || null;
            if (!dialog.open) dialog.showModal();
            requestAnimationFrame(() => historyCloseBtnRef.current?.focus());
        } else if (dialog.open) {
            dialog.close();
        }
    }, [historyModal.open]);

    // Restore focus and sync state if closed via Esc/backdrop
    useEffect(() => {
        const dialog = historyDialogRef.current;
        if (!dialog) return;
        const handleClose = () => {
            historyOpenerRef.current?.focus?.();
            historyOpenerRef.current = null;
            if (historyModal.open) {
                setHistoryModal((s) => ({...s, open: false}));
            }
        };
        dialog.addEventListener('close', handleClose);
        return () => dialog.removeEventListener('close', handleClose);
    }, [historyModal.open]);

    const colorForAvg = (avg: number) => {
        if (avg >= 4) return 'bg-green-50 dark:bg-green-900 text-green-900 dark:text-green-100';
        if (avg >= 3) return 'bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100';
        return 'bg-red-50 dark:bg-red-900 text-red-900 dark:text-red-100';
    };

    // Manage phrases dialog open/close, focus trap, and restoration
    useEffect(() => {
        const dialog = phrasesDialogRef.current;
        if (!dialog) return;

        const shouldOpen = showContext && !!selectedPhrase && !!safePhrases[selectedPhrase!];
        if (shouldOpen) {
            phrasesOpenerRef.current = (document.activeElement as HTMLElement) || null;
            dialog.showModal();
            requestAnimationFrame(() => {
                phrasesCloseBtnRef.current?.focus();
            });
        } else if (dialog.open) {
            dialog.close();
        }
    }, [showContext, selectedPhrase, safePhrases]);

    useEffect(() => {
        const dialog = phrasesDialogRef.current;
        if (!dialog) return;
        const handleClose = () => {
            setShowContext(false);
            phrasesOpenerRef.current?.focus?.();
            phrasesOpenerRef.current = null;
        };
        dialog.addEventListener('close', handleClose);
        return () => dialog.removeEventListener('close', handleClose);
    }, []);

    useEffect(() => {
        const dialog = phrasesDialogRef.current;
        if (!dialog) return;
        const handleClick = (e: MouseEvent) => {
            if (e.target === dialog) setShowContext(false);
        };
        if (showContext) {
            dialog.addEventListener('click', handleClick);
            return () => dialog.removeEventListener('click', handleClick);
        }
    }, [showContext]);

  
    return (
        <>
            <SeoHead metaTags={metaTags} />
            <div className="space-y-6">
                {/* Header */}
                <div className="sticky top-0 z-10 bg-gray-100 py-4 dark:bg-gray-900">
                    <div className="mx-auto max-w-6xl px-4">
                        <Link
                            href={route('ratings.index')}
                            className="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            <svg className="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Back
                        </Link>
                    </div>
                </div>

                {/* Stats */}
                <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{rater.name}'s Rating
                            Statistics</h2>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Total Games Rated
                            </div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {safeStats.all_games.unique_games.toLocaleString()}
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                {safeStats.visible_games.unique_games.toLocaleString()} listed
                            </div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Average Rating</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {Number(safeStats.all_games.average_rating ?? 0).toFixed(1)}
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                {Number(safeStats.visible_games.average_rating ?? 0).toFixed(1)} for listed games
                            </div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Review Rate</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {Math.round(safeStats.all_games.review_percentage)}%
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                {Math.round(safeStats.visible_games.review_percentage)}% for listed games
                            </div>
                        </div>
                        <div>
                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">Rating Period</div>
                            <div className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {safeStats.first_rating ? new Date(safeStats.first_rating).toLocaleDateString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric'
                                }) : '—'}
                            </div>
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                to {safeStats.latest_rating ? new Date(safeStats.latest_rating).toLocaleDateString(undefined, {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                            }) : '—'}
                            </div>
                        </div>
                    </div>

                    {/* Two distributions */}
                    <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        {([
                            ['All Games', safeStats.all_games] as const,
                            ['Listed Games', safeStats.visible_games] as const,
                        ]).map(([title, block]) => (
                            <div key={title}>
                                <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">{title} Rating
                                    Distribution</h3>
                                <div className="space-y-2">
                                    {Object.entries(block.rating_distribution).map(([ratingKey, count]) => {
                                        const ratingNum = Number(ratingKey);
                                        const percentage = block.total_ratings > 0 ? (Number(count) / block.total_ratings) * 100 : 0;
                                        return (
                                            <div key={ratingKey}>
                                                <div className="flex items-center">
                                                    <span
                                                        className="w-16 text-sm font-medium text-gray-500 dark:text-gray-400">{ratingNum} Stars</span>
                                                    <div className="mx-2 flex-1">
                                                        <div
                                                            className="h-4 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                            <div className="h-full bg-yellow-400 dark:bg-yellow-500"
                                                                 style={{width: `${percentage}%`}}/>
                                                        </div>
                                                    </div>
                                                    <span
                                                        className="w-20 text-right text-sm text-gray-500 dark:text-gray-400">
                                                            {Number(count).toLocaleString()} ({percentage.toFixed(1)}%)
                                                        </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Phrases */}
                <div className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Common Phrases in
                        Reviews</h2>
                    <div className="mt-4">
                        {Object.keys(safePhrases).length === 0 ? (
                            <div className="text-gray-500 dark:text-gray-400">No common phrases found</div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {Object.entries(safePhrases).map(([phrase, data]) => {
                                    const color = colorForAvg(data.avg_rating);
                                    return (
                                        <div key={phrase}
                                             className={`flex items-center justify-between rounded p-2 ${color}`}>
                                            <span className="flex-grow">{phrase}</span>
                                            <div className="ml-2 flex items-center gap-2 text-sm opacity-75">
                                                <span>{data.count}×</span>
                                                <span>({data.avg_rating.toFixed(1)}★)</span>
                                                <button
                                                    onClick={() => {
                                                        setSelectedPhrase(phrase);
                                                        setShowContext(true);
                                                    }}
                                                    className="ml-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                                    title="Show contexts"
                                                >
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor"
                                                         viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round"
                                                              strokeWidth="2"
                                                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                    {Object.keys(safePhrases).length > 0 ? (
                        <div className="mt-4 flex gap-4 text-sm text-gray-500 dark:text-gray-400">
                            <div>
                                <span
                                    className="mr-1 inline-block h-3 w-3 rounded bg-green-100 dark:bg-green-900"></span>
                                Positive context (4-5★)
                            </div>
                            <div>
                                <span className="mr-1 inline-block h-3 w-3 rounded bg-gray-100 dark:bg-gray-700"></span>
                                Neutral context (3★)
                            </div>
                            <div>
                                <span className="mr-1 inline-block h-3 w-3 rounded bg-red-100 dark:bg-red-900"></span>
                                Negative context (1-2★)
                            </div>
                        </div>
                    ) : null}
                </div>

                {/* Review Text Controls */}
                <ReviewTextControls />

                <div className="section-surface overflow-hidden rounded-2xl">
                    <div className="divide-y divide-[var(--color-ui-border)]">
                        {!ratings || ratings.data.length === 0 ? (
                            <div className="p-6 text-[var(--color-ui-text-muted)]">No ratings</div>
                        ) : (
                            ratings.data.map((row) => (
                                <div key={row.id} className="p-6">
                                    <div className="mb-2 flex items-center justify-between">
                                        <div className="flex items-center gap-4">
                                            <Link
                                                href={route('games.show', {game: row.game.slug})}
                                                className="text-lg font-medium text-[var(--color-link)] hover:underline"
                                            >
                                                {row.game.name}
                                            </Link>
                                            {previousRatingCounts[row.game.id] ? (
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                        <button onClick={() => openHistory(row.game.id, row.game.name)}
                                                                className="hover:underline">
                                                            ({previousRatingCounts[row.game.id]} previous
                                                            {previousRatingCounts[row.game.id] > 1 ? ' ratings' : ' rating'})
                                                        </button>
                                                    </span>
                                            ) : null}
                                            {row.game.url ? (
                                                <a
                                                    href={row.game.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                                    title="Open on itch.io"
                                                >
                                                    <i className="icon-external-link"/>
                                                </a>
                                            ) : null}
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <Stars rating={row.rating}/>
                                            <span className="text-sm text-[var(--color-ui-text-muted)]">
                                                    {row.published_at
                                                        ? new Date(row.published_at).toLocaleDateString(undefined, {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        })
                                                        : ''}
                                                </span>
                                            {row.event_id ? (
                                                <a
                                                    href={`https://itch.io/event/${row.event_id}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                                                    title="View on itch.io"
                                                >
                                                    <i className="icon-external-link"/>
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                    {row.review ? (
                                        <div
                                            className="prose dark:prose-invert mt-2 text-[var(--color-ui-text-muted)] mx-auto"
                                            style={reviewStyles}>
                                            {/* review is trusted HTML from server */}
                                            <div dangerouslySetInnerHTML={{__html: row.review || ''}}/>
                                        </div>
                                    ) : null}
                                </div>
                            ))
                        )}
                    </div>

                    <div className="p-4">
                        <AdvancedPagination
                            meta={ratingMeta}
                            onPageChange={(p) => setPage(p)}
                            onPerPageChange={(pp) => {
                                setPerPage(pp);
                                setPage(1);
                            }}
                            isLoading={isLoading}
                            label="ratings"
                            buildPageUrl={buildPageUrl}
                        />
                    </div>
                </div>


                {/* Common Phrases Dialog */}
                <dialog
                    ref={phrasesDialogRef}
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="phrases-dialog-title"
                    aria-describedby="phrases-dialog-desc"
                    className="m-auto w-full max-w-3xl rounded-lg bg-[var(--color-ui-surface)] p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm"
                >
                    <h1 id="phrases-dialog-title" className="sr-only">
                        Common Phrase Contexts
                    </h1>
                    <p id="phrases-dialog-desc" className="sr-only">
                        Example sentences where the selected phrase appears.
                    </p>
                    {selectedPhrase && safePhrases[selectedPhrase] ? (
                        <>
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-semibold">
                                    "{selectedPhrase}"
                                    <span className="text-sm font-normal">
                                            {' '}
                                        ({safePhrases[selectedPhrase].count}× / {safePhrases[selectedPhrase].avg_rating.toFixed(1)}★)
                                        </span>
                                </h3>
                                <button
                                    ref={phrasesCloseBtnRef}
                                    onClick={() => setShowContext(false)}
                                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                    aria-label="Close dialog"
                                >
                                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                                              d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div className="max-h-96 space-y-4 overflow-y-auto">
                                {Object.entries(safePhrases[selectedPhrase].contexts).map(([gameName, context]) => (
                                    <div key={gameName}>
                                        <h4 className="mb-2 font-medium text-[var(--color-ui-text)]">
                                            <Link
                                                href={route('games.show', {game: context.slug})}
                                                className="text-[var(--color-link)] hover:underline"
                                            >
                                                {gameName}
                                            </Link>{' '}
                                            <span
                                                className="font-normal text-[var(--color-ui-text-muted)]">({context.rating}★)</span>
                                        </h4>
                                        <div className="space-y-2">
                                            {context.sentences.map((sentence, index) => {
                                                const regex = new RegExp(`(${selectedPhrase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig');
                                                const parts = sentence.split(regex);
                                                return (
                                                    <div key={index}
                                                         className="rounded bg-[var(--color-ui-surface-alt)] p-2 text-sm">
                                                        {parts.map((part, i) =>
                                                            regex.test(part) ? (
                                                                <span key={i}
                                                                      className="font-medium text-[var(--color-link)]">{part}</span>
                                                            ) : (
                                                                <span key={i}>{part}</span>
                                                            ),
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : null}
                </dialog>

                {/* Rating History Dialog */}
                <dialog
                    ref={historyDialogRef}
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="history-dialog-title"
                    aria-describedby="history-dialog-desc"
                    className="m-auto w-full max-w-2xl rounded-lg bg-[var(--color-ui-surface)] p-6 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) closeHistory();
                    }}
                >
                    <h1 id="history-dialog-title" className="sr-only">
                        Rating History
                    </h1>
                    <p id="history-dialog-desc" className="sr-only">
                        All previous ratings and reviews for the selected game.
                    </p>
                    {historyModal.gameName && (
                        <div className="mb-4">
                            <h3 className="text-lg font-medium text-[var(--color-ui-text)]">{historyModal.gameName}</h3>
                            <p className="text-sm text-[var(--color-ui-text-muted)]">Rating history for this game:</p>
                        </div>
                    )}
                    <div className="space-y-6">
                        {historyModal.ratings.length > 0 ? (
                            historyModal.ratings.map((hr, idx) => (
                                <div
                                    key={hr.id}
                                    className={`${idx < historyModal.ratings.length - 1 ? 'border-b border-[var(--color-ui-border)] pb-6' : ''}`}
                                >
                                    <div className="mb-2 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Stars rating={hr.rating}/>
                                            <span className="text-sm text-[var(--color-ui-text-muted)]">
                                                    {hr.published_at
                                                        ? new Date(hr.published_at).toLocaleDateString(undefined, {
                                                            month: 'short',
                                                            day: 'numeric',
                                                            year: 'numeric',
                                                        })
                                                        : ''}
                                                </span>
                                            {hr.is_visible ? (
                                                <span
                                                    className="rounded-full bg-[var(--color-surface-peach)] px-2 py-1 text-xs text-[var(--color-link)]">Current</span>
                                            ) : null}
                                        </div>
                                        {hr.event_id ? (
                                            <a
                                                href={`https://itch.io/event/${hr.event_id}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-sm text-[var(--color-link)] hover:underline"
                                            >
                                                View on itch.io
                                            </a>
                                        ) : null}
                                    </div>
                                    {hr.review ? (
                                        <div
                                            className="prose dark:prose-invert text-[var(--color-ui-text-muted)] mx-auto"
                                            style={reviewStyles}>
                                            <div dangerouslySetInnerHTML={{__html: hr.review || ''}}/>
                                        </div>
                                    ) : null}
                                </div>
                            ))
                        ) : (
                            <div className="py-4 text-center text-[var(--color-ui-text-muted)]">No rating history
                                found.</div>
                        )}
                    </div>
                    <div className="mt-6 flex justify-end">
                        <button
                            ref={historyCloseBtnRef}
                            onClick={closeHistory}
                            className="rounded-md border border-[var(--color-ui-border)] bg-[var(--color-ui-surface)] px-4 py-2 text-sm text-[var(--color-ui-text)] hover:border-[var(--color-brand-primary)] hover:text-[var(--color-link)]"
                            aria-label="Close dialog"
                        >
                            Close
                        </button>
                    </div>
                </dialog>
            </div>
        </>
    );
}
