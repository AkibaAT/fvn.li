import {Head, Link, usePage} from '@inertiajs/react';
import {lazy, Suspense, useCallback, useState} from 'react';

const TinyMCEEditor = lazy(() => import('@/components/editor/TinyMCEEditor'));

interface ReviewGame {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string;
}

interface ReviewUser {
    id: number;
    name: string;
    avatar?: string;
}

interface ReviewRater {
    id: number;
    name: string;
    external_platform?: string;
}

interface Review {
    id: number;
    rating: number;
    review?: string;
    published_at?: string;
    is_reviewed: boolean;
    has_spoilers: boolean;
    event_id?: string;
    source_platform?: string;
    game?: ReviewGame | null;
    user?: ReviewUser | null;
    rater?: ReviewRater | null;
}

interface ReviewShowProps {
    review: Review;
    metaTags?: {
        title?: string;
        description?: string;
    };
}

function SpoilerContent({html}: {html: string}) {
    const [revealed, setRevealed] = useState(false);
    if (revealed) {
        return <div className="prose dark:prose-invert max-w-none" dangerouslySetInnerHTML={{__html: html}} />;
    }
    return (
        <button
            onClick={() => setRevealed(true)}
            className="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 transition-colors hover:bg-yellow-100 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
        >
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
            This review contains spoilers — click to reveal
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
        <form onSubmit={handleSubmit} className="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
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
                                    className={`h-7 w-7 cursor-pointer transition-colors ${
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
                        height={250}
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
                    className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {isSubmitting ? 'Saving...' : 'Update Review'}
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function ReviewShow({review: initialReview, metaTags}: ReviewShowProps) {
    const {auth} = (usePage().props as { auth?: { user?: { id: number } | null } }) ?? {};
    const currentUserId = (auth?.user as { id: number } | null)?.id ?? null;
    const [review, setReview] = useState(initialReview);
    const [isEditing, setIsEditing] = useState(false);
    const authorName = review.user?.name ?? review.rater?.name ?? 'Unknown';
    const isUserReview = Boolean(review.user);
    const isOwnReview = isUserReview && currentUserId === review.user?.id;

    return (
        <>
            <Head title={metaTags?.title || `Review by ${authorName}`} />

            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav className="text-sm text-gray-500 dark:text-gray-400">
                    {review.game && (
                        <>
                            <Link href={route('games.show', review.game.slug)} className="hover:text-blue-600 dark:hover:text-blue-400">
                                {review.game.name}
                            </Link>
                            <span className="mx-2">/</span>
                        </>
                    )}
                    <span>Review</span>
                </nav>

                {/* Main review card */}
                <div className="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    {/* Game info */}
                    {review.game && (
                        <Link
                            href={route('games.show', review.game.slug)}
                            className="mb-4 flex items-center gap-3 rounded-lg bg-gray-50 p-3 transition-colors hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                        >
                            {review.game.thumb_url && (
                                <img
                                    src={review.game.thumb_url}
                                    alt={review.game.name}
                                    className="h-12 w-12 rounded object-cover"
                                />
                            )}
                            <div>
                                <div className="font-medium text-gray-900 dark:text-gray-100">{review.game.name}</div>
                                <div className="text-xs text-gray-500 dark:text-gray-400">View game page</div>
                            </div>
                        </Link>
                    )}

                    {/* Author and rating */}
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            {review.user?.avatar && (
                                <img src={review.user.avatar} alt="" className="h-8 w-8 rounded-full" />
                            )}
                            <div>
                                <div className="flex items-center gap-2">
                                    {isUserReview ? (
                                        <Link
                                            href={route('users.reviews', review.user!.id)}
                                            className="font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                                        >
                                            {authorName}
                                        </Link>
                                    ) : review.rater ? (
                                        <Link
                                            href={route('raters.show', review.rater.id)}
                                            className="font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                                        >
                                            {authorName}
                                        </Link>
                                    ) : (
                                        <span className="font-medium text-gray-900 dark:text-gray-100">{authorName}</span>
                                    )}
                                    {isUserReview && (
                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                            FVN.li
                                        </span>
                                    )}
                                </div>
                                {review.published_at && (
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        {new Date(review.published_at).toLocaleDateString('en-US', {
                                            month: 'long',
                                            day: 'numeric',
                                            year: 'numeric',
                                        })}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Star rating */}
                        <div className="flex items-center gap-1">
                            {Array.from({length: 5}, (_, i) => (
                                <svg
                                    key={i}
                                    className={`h-6 w-6 ${i < review.rating ? 'fill-yellow-400 text-yellow-400' : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}`}
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>
                            ))}
                            <span className="ml-1 text-lg font-medium text-gray-700 dark:text-gray-300">{review.rating}/5</span>
                        </div>
                    </div>

                    {isEditing && review.game ? (
                        <InlineReviewEditor
                            review={review}
                            onSaved={(updated) => {
                                setReview(updated);
                                setIsEditing(false);
                            }}
                            onCancel={() => setIsEditing(false)}
                        />
                    ) : (
                        <>
                            {/* Review text */}
                            {review.review && review.is_reviewed && (
                                <div className="mt-4">
                                    {review.has_spoilers && (
                                        <span className="mb-2 mr-1 inline-block rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Spoilers
                                        </span>
                                    )}
                                    {review.has_spoilers ? (
                                        <SpoilerContent html={review.review} />
                                    ) : (
                                        <div className="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300" dangerouslySetInnerHTML={{__html: review.review}} />
                                    )}
                                </div>
                            )}

                            {!review.review && (
                                <p className="mt-4 text-gray-500 dark:text-gray-400 italic">Rating only — no written review.</p>
                            )}

                            {isOwnReview && review.game && (
                                <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                    <button
                                        onClick={() => setIsEditing(true)}
                                        className="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit this review
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Back link */}
                {review.game && (
                    <div className="text-center">
                        <Link
                            href={`${route('games.show', review.game.slug)}#review-${review.id}`}
                            className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >
                            View all reviews for {review.game.name}
                        </Link>
                    </div>
                )}
            </div>
        </>
    );
}
