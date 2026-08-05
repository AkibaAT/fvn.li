export type RatingDistribution = Record<number, number>;

export type StatsBlock = {
    total_ratings: number;
    reviewed_count: number;
    review_percentage: number;
    average_rating: number;
    unique_games: number;
    rating_distribution: RatingDistribution;
};

export type GlobalStats = {
    first_rating?: string | null;
    latest_rating?: string | null;
    all_games: StatsBlock;
    visible_games: StatsBlock;
};

export type RatingRowData = {
    id: number;
    score: number;
    date?: string | null;
    review?: string | null;
    eventId?: number | null;
    game: {
        id: number;
        name: string;
        slug: string;
        primaryUrl?: string | null;
    };
    rater?: {
        id: number;
        name: string;
        externalPlatform?: string;
    };
    previousRatingCount?: number;
    onOpenHistory?: () => void;
};

const emptyStatsBlock = (): StatsBlock => ({
    total_ratings: 0,
    reviewed_count: 0,
    review_percentage: 0,
    average_rating: 0,
    unique_games: 0,
    rating_distribution: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 },
});

export function emptyStats(): GlobalStats {
    return {
        first_rating: null,
        latest_rating: null,
        all_games: emptyStatsBlock(),
        visible_games: emptyStatsBlock(),
    };
}
