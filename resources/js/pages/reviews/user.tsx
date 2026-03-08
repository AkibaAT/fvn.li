import AdvancedPagination from '@/components/advanced-pagination';
import {Head, Link, router, usePage} from '@inertiajs/react';
import {lazy, Suspense, useCallback, useEffect, useRef, useState} from 'react';

const TinyMCEEditor = lazy(() => import('@/components/editor/TinyMCEEditor'));

interface ReviewGame {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
}

interface Review {
    id: number;
    rating: number;
    review?: string;
    published_at?: string;
    is_reviewed: boolean;
    has_spoilers: boolean;
    game?: ReviewGame | null;
}

interface ReviewUser {
    id: number;
    name: string;
    avatar?: string;
}

interface Stats {
    total_ratings: number;
    reviewed_count: number;
    average_rating: number;
    unique_games: number;
}

interface Filters {
    sortField: string;
    sortDirection: string;
    page: number;
    perPage: number;
}

interface UserReviewsProps {
    reviewUser: ReviewUser;
    reviews: {
        data: Review[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: Stats;
    filters: Filters;
    metaTags?: {
        title?: string;
        description?: string;
    };
}

const REVIEW_COLLAPSE_HEIGHT = 150;

function CollapsibleReview({html}: {html: string}) {
    const contentRef = useRef<HTMLDivElement>(null);
    const [isOverflowing, setIsOverflowing] = useState(false);
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        const el = contentRef.current;
        if (el) {
            setIsOverflowing(el.scrollHeight > REVIEW_COLLAPSE_HEIGHT);
        }
    }, [html]);

    return (
        <div>
            <div
                ref={contentRef}
                className="relative overflow-hidden transition-[max-height] duration-300 ease-in-out"
                style={{maxHeight: !expanded && isOverflowing ? `${REVIEW_COLLAPSE_HEIGHT}px` : undefined}}
            >
                <div
                    className="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-300"
                    dangerouslySetInnerHTML={{__html: html}}
                />
                {!expanded && isOverflowing && (
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-white dark:from-gray-800" />
                )}
            </div>
            {isOverflowing && (
                <button
                    onClick={() => setExpanded(!expanded)}
                    className="mt-1 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                    {expanded ? 'Show less' : 'Read more'}
                </button>
            )}
        </div>
    );
}

function SpoilerReview({html}: {html: string}) {
    const [revealed, setRevealed] = useState(false);
    return revealed ? (
        <div>
            <span className="mr-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                Spoilers
            </span>
            <CollapsibleReview html={html} />
        </div>
    ) : (
        <button
            onClick={() => setRevealed(true)}
            className="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-2 py-1.5 text-xs text-yellow-800 transition-colors hover:bg-yellow-100 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
        >
            <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
            Contains spoilers — click to reveal
        </button>
    );
}

function InlineReviewEditor({review, onSaved, onCancel}: {review: Review; onSaved: (updated: Review) => void; onCancel: () => void}) {
    const [rating, setRating] = useState(review.rating);
    const [hoveredRating, setHoveredRating] = useState(0);
    const [reviewText, setReviewText] = useState(review.review ?? '');
    const [hasSpoilers, setHasSpoilers] = useState(review.has_spoilers);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (rating === 0 || !review.game) return;
        setIsSubmitting(true);
        setError(null);
        try {
            const response = await window.axios.post(
                route('react-api.user-reviews.store', {game: review.game.id}),
                {rating, review: reviewText, has_spoilers: hasSpoilers}
            );
            onSaved({
                ...review,
                rating: response.data.review.rating,
                review: response.data.review.review,
                has_spoilers: response.data.review.has_spoilers,
                is_reviewed: Boolean(response.data.review.review?.replace(/<[^>]*>/g, '').trim()),
            });
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to update review');
        } finally {
            setIsSubmitting(false);
        }
    }, [rating, reviewText, hasSpoilers, review, onSaved]);

    return (
        <form onSubmit={handleSubmit} className="mt-3 space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
            {/* Star Rating */}
            <div>
                <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">Rating *</label>
                <div className="flex items-center gap-1">
                    {Array.from({length: 5}, (_, i) => {
                        const starValue = i + 1;
                        const isActive = starValue <= (hoveredRating || rating);
                        return (
                            <button
                                key={i}
                                type="button"
                                onClick={() => setRating(starValue)}
                                onMouseEnter={() => setHoveredRating(starValue)}
                                onMouseLeave={() => setHoveredRating(0)}
                                className="focus:outline-none"
                            >
                                <svg
                                    className={`h-6 w-6 cursor-pointer transition-colors ${
                                        isActive
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'fill-gray-300 text-gray-300 hover:fill-yellow-200 hover:text-yellow-200 dark:fill-gray-600 dark:text-gray-600'
                                    }`}
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>
                            </button>
                        );
                    })}
                    {rating > 0 && (
                        <span className="ml-2 text-sm text-gray-500 dark:text-gray-400">{rating}/5</span>
                    )}
                </div>
            </div>

            {/* Review Text */}
            <div>
                <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">Review (optional)</label>
                <Suspense
                    fallback={
                        <div className="flex h-48 items-center justify-center rounded-md border border-gray-300 bg-gray-50 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Loading editor...
                        </div>
                    }
                >
                    <TinyMCEEditor
                        content={reviewText}
                        onUpdate={setReviewText}
                        editable={true}
                        placeholder="Share your thoughts about this visual novel..."
                        height={200}
                        disableImages={true}
                    />
                </Suspense>
            </div>

            {/* Spoiler Toggle */}
            {reviewText.trim().length > 0 && (
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={hasSpoilers}
                        onChange={(e) => setHasSpoilers(e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                    />
                    <span className="text-sm text-gray-600 dark:text-gray-400">This review contains spoilers</span>
                </label>
            )}

            {error && <div className="text-sm text-red-600 dark:text-red-400">{error}</div>}

            {/* Actions */}
            <div className="flex items-center gap-2">
                <button
                    type="submit"
                    disabled={rating === 0 || isSubmitting}
                    className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {isSubmitting ? 'Saving...' : 'Update Review'}
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-md bg-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function UserReviews({reviewUser, reviews, stats, filters, metaTags}: UserReviewsProps) {
    const {auth} = (usePage().props as { auth?: { user?: { id: number } | null } }) ?? {};
    const isOwnProfile = (auth?.user as { id: number } | null)?.id === reviewUser.id;
    const [isLoading, setIsLoading] = useState(false);
    const [editingReviewId, setEditingReviewId] = useState<number | null>(null);
    const [localReviews, setLocalReviews] = useState(reviews.data);

    // Sync when server data changes (pagination, sort)
    useEffect(() => {
        setLocalReviews(reviews.data);
        setEditingReviewId(null);
    }, [reviews.data]);

    const navigate = (params: Record<string, string | number>) => {
        setIsLoading(true);
        router.get(
            route('users.reviews', reviewUser.id),
            {...filters, ...params},
            {preserveState: true, preserveScroll: true, onFinish: () => setIsLoading(false)},
        );
    };

    const handlePageChange = (page: number) => navigate({page});
    const handlePerPageChange = (perPage: number) => navigate({perPage, page: 1});

    const toggleSort = (field: string) => {
        const newDirection = filters.sortField === field && filters.sortDirection === 'desc' ? 'asc' : 'desc';
        navigate({sortField: field, sortDirection: newDirection, page: 1});
    };

    const buildPageUrl = (page: number): string => {
        const params = new URLSearchParams();
        params.set('page', page.toString());
        params.set('perPage', reviews.per_page.toString());
        params.set('sortField', filters.sortField);
        params.set('sortDirection', filters.sortDirection);
        return `/users/${reviewUser.id}/reviews?${params.toString()}`;
    };

    const sortIcon = (field: string) => {
        if (filters.sortField !== field) return null;
        return filters.sortDirection === 'asc' ? ' \u2191' : ' \u2193';
    };

    return (
        <>
            <Head title={metaTags?.title || `${reviewUser.name}'s Reviews`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        {reviewUser.avatar && (
                            <img src={reviewUser.avatar} alt="" className="h-10 w-10 rounded-full" />
                        )}
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {reviewUser.name}'s Reviews
                            </h1>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {stats.reviewed_count} review{stats.reviewed_count !== 1 ? 's' : ''} across {stats.unique_games} game{stats.unique_games !== 1 ? 's' : ''}
                                {stats.average_rating > 0 && (
                                    <> &middot; avg {stats.average_rating}/5</>
                                )}
                            </p>
                        </div>
                    </div>
                    <Link
                        href={route('lists.user-public', reviewUser.id)}
                        className="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                    >
                        View Lists
                    </Link>
                </div>

                {/* Sort controls */}
                <div className="flex gap-2">
                    <button
                        onClick={() => toggleSort('published_at')}
                        className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
                            filters.sortField === 'published_at'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                        }`}
                    >
                        Date{sortIcon('published_at')}
                    </button>
                    <button
                        onClick={() => toggleSort('rating')}
                        className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
                            filters.sortField === 'rating'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                        }`}
                    >
                        Rating{sortIcon('rating')}
                    </button>
                </div>

                {/* Reviews list */}
                {localReviews.length === 0 ? (
                    <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                        No reviews yet.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {localReviews.map((review) => (
                            <div
                                key={review.id}
                                className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div className="flex items-start gap-4">
                                    {/* Game thumbnail */}
                                    {review.game && (
                                        <Link href={route('games.show', review.game.slug)} className="shrink-0">
                                            {review.game.thumb_url ? (
                                                <img
                                                    src={review.game.thumb_url}
                                                    alt={review.game.name}
                                                    className="h-16 w-16 rounded object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="flex h-16 w-16 items-center justify-center rounded bg-gray-100 dark:bg-gray-700">
                                                    <span className="text-xs text-gray-400">No img</span>
                                                </div>
                                            )}
                                        </Link>
                                    )}

                                    <div className="min-w-0 flex-1">
                                        {/* Game name and rating */}
                                        <div className="flex items-center justify-between gap-2">
                                            <div className="min-w-0">
                                                {review.game && (
                                                    <Link
                                                        href={route('games.show', review.game.slug)}
                                                        className="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                                    >
                                                        {review.game.name}
                                                    </Link>
                                                )}
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <div className="flex items-center gap-1">
                                                    {Array.from({length: 5}, (_, i) => (
                                                        <svg
                                                            key={i}
                                                            className={`h-4 w-4 ${i < review.rating ? 'fill-yellow-400 text-yellow-400' : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}`}
                                                            viewBox="0 0 20 20"
                                                        >
                                                            <path
                                                                fillRule="evenodd"
                                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                                            />
                                                        </svg>
                                                    ))}
                                                </div>
                                                {isOwnProfile && editingReviewId !== review.id && (
                                                    <button
                                                        onClick={() => setEditingReviewId(review.id)}
                                                        className="text-gray-400 hover:text-blue-500 dark:text-gray-500 dark:hover:text-blue-400"
                                                        title="Edit review"
                                                    >
                                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        {/* Date */}
                                        {review.published_at && (
                                            <div className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {new Date(review.published_at).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })}
                                            </div>
                                        )}

                                        {editingReviewId === review.id ? (
                                            <InlineReviewEditor
                                                review={review}
                                                onSaved={(updated) => {
                                                    setLocalReviews(prev => prev.map(r => r.id === updated.id ? updated : r));
                                                    setEditingReviewId(null);
                                                }}
                                                onCancel={() => setEditingReviewId(null)}
                                            />
                                        ) : (
                                            <>
                                                {/* Review body */}
                                                {review.review && review.is_reviewed && (
                                                    <div className="mt-2">
                                                        {review.has_spoilers ? (
                                                            <SpoilerReview html={review.review} />
                                                        ) : (
                                                            <CollapsibleReview html={review.review} />
                                                        )}
                                                    </div>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <AdvancedPagination
                    meta={{
                        current_page: reviews.current_page,
                        last_page: reviews.last_page,
                        total: reviews.total,
                        from: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + 1 : 0,
                        to: reviews.data.length ? (reviews.current_page - 1) * reviews.per_page + reviews.data.length : 0,
                        per_page: reviews.per_page,
                    }}
                    onPageChange={handlePageChange}
                    onPerPageChange={handlePerPageChange}
                    isLoading={isLoading}
                    label="reviews"
                    perPageOptions={[10, 25, 50]}
                    buildPageUrl={buildPageUrl}
                />
            </div>
        </>
    );
}
