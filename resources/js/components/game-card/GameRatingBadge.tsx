interface GameRatingBadgeProps {
    ratingCount: number | null | undefined;
    ratingScore: number | null | undefined;
}

export default function GameRatingBadge({ratingCount, ratingScore}: GameRatingBadgeProps) {
    if (!ratingCount || ratingCount === 0) return null;

    // Format rating count: 1234 -> "1.2k", 12345 -> "12k"
    const formatCount = (count: number): string => {
        if (count >= 1000) {
            return `${(count / 1000).toFixed(1).replace(/\.0$/, '')}k`;
        }
        return count.toString();
    };

    return (
        <div className="absolute left-3 top-3 z-20 flex items-center gap-1.5 rounded-lg bg-black/60 px-2.5 py-1.5 shadow-lg backdrop-blur-md">
            {/* Star icon */}
            <svg
                className="h-4 w-4 text-amber-400"
                fill="currentColor"
                viewBox="0 0 20 20"
                aria-hidden="true"
            >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            {/* Rating count as primary, score as secondary */}
            <span className="text-sm font-bold text-white">
                {formatCount(ratingCount)}
            </span>
            {ratingScore && (
                <span className="text-xs text-white/70">
                    ({ratingScore.toFixed(1)})
                </span>
            )}
        </div>
    );
}
