import {usePage} from '@inertiajs/react';
import {useState, useCallback, useImperativeHandle, forwardRef, lazy, Suspense} from 'react';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {gamePageKeys} from '@/hooks/api/useGamePage';

const TinyMCEEditor = lazy(() => import('@/components/editor/TinyMCEEditor'));

interface UserReview {
    id: number;
    rating: number;
    review: string;
    has_spoilers: boolean;
    published_at: string;
    updated_at: string;
}

interface UserReviewFormProps {
    gameId: number;
    gameName: string;
    initialReview?: UserReview | null;
}

interface InertiaPageProps {
    auth?: {
        user: { id: number; name: string } | null;
    };
}

export interface UserReviewFormHandle {
    startEditing: () => void;
}

const UserReviewForm = forwardRef<UserReviewFormHandle, UserReviewFormProps>(function UserReviewForm({gameId, gameName, initialReview = null}, ref) {
    const {auth} = (usePage().props as InertiaPageProps) ?? {};
    const isAuthenticated = Boolean(auth?.user);
    const queryClient = useQueryClient();

    const [isEditing, setIsEditing] = useState(false);
    const [rating, setRating] = useState(initialReview?.rating ?? 0);
    const [hoveredRating, setHoveredRating] = useState(0);
    const [reviewText, setReviewText] = useState(initialReview?.review ?? '');
    const [hasSpoilers, setHasSpoilers] = useState(initialReview?.has_spoilers ?? false);
    const [userReview, setUserReview] = useState<UserReview | null>(initialReview);
    const [message, setMessage] = useState<{type: 'success' | 'error'; text: string} | null>(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const showMessage = useCallback((text: string, type: 'success' | 'error') => {
        setMessage({text, type});
        setTimeout(() => setMessage(null), 5000);
    }, []);

    const submitMutation = useMutation({
        mutationFn: async () => {
            const response = await window.axios.post(
                route('react-api.user-reviews.store', {game: gameId}),
                {rating, review: reviewText, has_spoilers: hasSpoilers}
            );
            return response.data;
        },
        onSuccess: (data) => {
            setUserReview(data.review);
            setIsEditing(false);
            showMessage(data.message, 'success');
            queryClient.invalidateQueries({queryKey: gamePageKeys.reviews(gameId, {} as any)});
        },
        onError: (error: any) => {
            const msg = error?.response?.data?.message || 'Failed to submit review';
            showMessage(msg, 'error');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async () => {
            const response = await window.axios.delete(
                route('react-api.user-reviews.destroy', {game: gameId})
            );
            return response.data;
        },
        onSuccess: (data) => {
            setUserReview(null);
            setRating(0);
            setReviewText('');
            setHasSpoilers(false);
            setIsEditing(false);
            setShowDeleteConfirm(false);
            showMessage(data.message, 'success');
            queryClient.invalidateQueries({queryKey: gamePageKeys.reviews(gameId, {} as any)});
        },
        onError: (error: any) => {
            const msg = error?.response?.data?.message || 'Failed to delete review';
            showMessage(msg, 'error');
        },
    });

    if (!isAuthenticated) {
        return (
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-700 dark:bg-gray-800/50">
                <p className="text-sm text-gray-600 dark:text-gray-400">
                    <a href={route('login')} className="text-blue-600 hover:underline dark:text-blue-400">
                        Sign in
                    </a>
                    {' '}to leave a review for this game.
                </p>
            </div>
        );
    }

    const handleStartEdit = () => {
        if (userReview) {
            setRating(userReview.rating);
            setReviewText(userReview.review || '');
            setHasSpoilers(userReview.has_spoilers);
        }
        setIsEditing(true);
    };

    useImperativeHandle(ref, () => ({ startEditing: handleStartEdit }), [userReview]);

    const handleCancel = () => {
        setIsEditing(false);
        if (userReview) {
            setRating(userReview.rating);
            setReviewText(userReview.review || '');
            setHasSpoilers(userReview.has_spoilers);
        } else {
            setRating(0);
            setReviewText('');
            setHasSpoilers(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (rating === 0) {
            showMessage('Please select a rating', 'error');
            return;
        }
        submitMutation.mutate();
    };

    // Display existing review — compact, since the full text is already in the reviews list
    if (userReview && !isEditing) {
        return (
            <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">Your Review</span>
                        <div className="flex items-center gap-0.5">
                            {Array.from({length: 5}, (_, i) => (
                                <svg
                                    key={i}
                                    className={`h-4 w-4 ${i < userReview.rating ? 'fill-yellow-400 text-yellow-400' : 'fill-gray-300 text-gray-300 dark:fill-gray-600 dark:text-gray-600'}`}
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                    />
                                </svg>
                            ))}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleStartEdit}
                            className="text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Edit
                        </button>
                        <button
                            onClick={() => setShowDeleteConfirm(true)}
                            className="text-sm text-red-600 hover:underline dark:text-red-400"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                {/* Delete confirmation */}
                {showDeleteConfirm && (
                    <div className="mt-3 flex items-center gap-2 rounded border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                        <span className="text-sm text-red-800 dark:text-red-200">Delete your review?</span>
                        <button
                            onClick={() => deleteMutation.mutate()}
                            disabled={deleteMutation.isPending}
                            className="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700 disabled:opacity-50"
                        >
                            {deleteMutation.isPending ? 'Deleting...' : 'Confirm'}
                        </button>
                        <button
                            onClick={() => setShowDeleteConfirm(false)}
                            className="rounded bg-gray-200 px-2 py-1 text-xs text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300"
                        >
                            Cancel
                        </button>
                    </div>
                )}

                {message && (
                    <div className={`mt-2 text-sm ${message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                        {message.text}
                    </div>
                )}
            </div>
        );
    }

    // Compact prompt when no review exists and user hasn't clicked to write
    if (!userReview && !isEditing) {
        return (
            <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <button
                    onClick={handleStartEdit}
                    className="flex w-full items-center gap-2 text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                >
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Write a review for {gameName}
                </button>
            </div>
        );
    }

    // Review form (create or edit mode)
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h3 className="mb-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                {userReview ? 'Edit Your Review' : 'Write a Review'}
            </h3>

            <form onSubmit={handleSubmit}>
                {/* Star Rating */}
                <div className="mb-3">
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
                                    aria-label={`${starValue} star${starValue !== 1 ? 's' : ''}`}
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
                <div className="mb-3">
                    <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                        Review (optional)
                    </label>
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
                    <div className="mb-3">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={hasSpoilers}
                                onChange={(e) => setHasSpoilers(e.target.checked)}
                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                            />
                            <span className="text-sm text-gray-600 dark:text-gray-400">
                                This review contains spoilers
                            </span>
                        </label>
                    </div>
                )}

                {/* Actions */}
                <div className="flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={rating === 0 || submitMutation.isPending}
                        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {submitMutation.isPending
                            ? 'Submitting...'
                            : userReview
                                ? 'Update Review'
                                : 'Submit Review'}
                    </button>
                    {isEditing && (
                        <button
                            type="button"
                            onClick={handleCancel}
                            className="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                    )}
                </div>

                {message && (
                    <div className={`mt-2 text-sm ${message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                        {message.text}
                    </div>
                )}
            </form>
        </div>
    );
});

export default UserReviewForm;
