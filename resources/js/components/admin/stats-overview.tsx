import Stars from '@/components/ui/stars';
import React from 'react';

interface GameStats {
    total: number;
    visible: number;
    listing_rate: number;
    latest_update: string | null;
}

interface RatingStats {
    total: number;
    reviews: {
        total: number;
        review_rate: number;
    };
    average_rating: number;
    visible_games: {
        total: number;
        reviews: number;
        review_rate: number;
        average_rating: number;
    };
    latest: string | null;
}

interface StatsOverviewProps {
    gameStats: GameStats;
    ratingStats: RatingStats;
}

const StatsOverview: React.FC<StatsOverviewProps> = ({
                                                         gameStats,
                                                         ratingStats,
                                                     }) => {
    const formatNumber = (num: number) => {
        return new Intl.NumberFormat().format(num);
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return null;

        const date = new Date(dateString);
        const now = new Date();
        const diffInMs = now.getTime() - date.getTime();
        const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
        const diffInDays = Math.floor(diffInHours / 24);

        let timeAgo = '';
        if (diffInDays > 0) {
            timeAgo = `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
        } else if (diffInHours > 0) {
            timeAgo = `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
        } else {
            timeAgo = 'Less than an hour ago';
        }

        const formattedDate = date.toLocaleString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });

        return {timeAgo, formattedDate};
    };

    const gameLatestUpdate = formatDate(gameStats.latest_update);
    const ratingLatest = formatDate(ratingStats.latest);

    return (
        <div className="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
            {/* Game Stats */}
            <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Games
                </h2>
                <dl className="grid grid-cols-2 gap-4">
                    <div>
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Total Games
                        </dt>
                        <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(gameStats.total)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Listed Games
                        </dt>
                        <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {formatNumber(gameStats.visible)}
                        </dd>
                        <dd className="text-sm text-gray-500 dark:text-gray-400">
                            Listing rate:{' '}
                            {gameStats.listing_rate
                                ? gameStats.listing_rate.toFixed(1)
                                : '0.0'}
                            %
                        </dd>
                    </div>
                </dl>
                {gameLatestUpdate && (
                    <div className="mt-4 text-sm">
                        <span className="text-gray-500 dark:text-gray-400">
                            Latest Update:
                        </span>
                        <span className="ml-1 text-gray-900 dark:text-gray-100">
                            {gameLatestUpdate.timeAgo}
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {' '}
                                ({gameLatestUpdate.formattedDate})
                            </span>
                        </span>
                    </div>
                )}
            </div>

            {/* Rating Stats */}
            <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                    Ratings
                </h2>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {/* All Ratings */}
                    <div>
                        <h3 className="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                            All Ratings
                        </h3>
                        <dl className="grid grid-cols-2 gap-4">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Ratings
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {formatNumber(ratingStats.total)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Reviews
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {formatNumber(ratingStats.reviews.total)}
                                </dd>
                                <dd className="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate:{' '}
                                    {ratingStats.reviews.review_rate
                                        ? ratingStats.reviews.review_rate.toFixed(
                                            1,
                                        )
                                        : '0.0'}
                                    %
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Average Rating
                                </dt>
                                <dd className="mt-1 flex items-center gap-2">
                                    <span className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                        {ratingStats.average_rating
                                            ? Number(
                                                ratingStats.average_rating,
                                            ).toFixed(2)
                                            : 'N/A'}
                                    </span>
                                    <Stars
                                        rating={
                                            Number(
                                                ratingStats.average_rating,
                                            ) || 0
                                        }
                                    />
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {/* Listed Games */}
                    <div>
                        <h3 className="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">
                            Listed Games
                        </h3>
                        <dl className="grid grid-cols-2 gap-4">
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Ratings
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {formatNumber(
                                        ratingStats.visible_games.total,
                                    )}
                                </dd>
                                <dd className="text-sm text-gray-500 dark:text-gray-400">
                                    (
                                    {ratingStats.visible_games.total &&
                                    ratingStats.total
                                        ? (
                                            (ratingStats.visible_games.total /
                                                Math.max(
                                                    ratingStats.total,
                                                    1,
                                                )) *
                                            100
                                        ).toFixed(1)
                                        : '0.0'}
                                    % of all)
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Reviews
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {formatNumber(
                                        ratingStats.visible_games.reviews,
                                    )}
                                </dd>
                                <dd className="text-sm text-gray-500 dark:text-gray-400">
                                    Review rate:{' '}
                                    {ratingStats.visible_games.review_rate
                                        ? ratingStats.visible_games.review_rate.toFixed(
                                            1,
                                        )
                                        : '0.0'}
                                    %
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Average Rating
                                </dt>
                                <dd className="mt-1 flex items-center gap-2">
                                    <span className="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                        {ratingStats.visible_games
                                            .average_rating
                                            ? Number(
                                                ratingStats.visible_games
                                                    .average_rating,
                                            ).toFixed(2)
                                            : 'N/A'}
                                    </span>
                                    <Stars
                                        rating={
                                            Number(
                                                ratingStats.visible_games
                                                    .average_rating,
                                            ) || 0
                                        }
                                    />
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                {ratingLatest && (
                    <div className="mt-4 text-sm">
                        <span className="text-gray-500 dark:text-gray-400">
                            Latest Rating:
                        </span>
                        <span className="ml-1 text-gray-900 dark:text-gray-100">
                            {ratingLatest.timeAgo}
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {' '}
                                ({ratingLatest.formattedDate})
                            </span>
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
};

export default StatsOverview;
