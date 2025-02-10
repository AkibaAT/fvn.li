import {ref, Ref} from 'vue';
import axios from 'axios';

interface Review {
    id: number;
    rating: number;
    review: string | null;
    is_reviewed: boolean;
    published_at: string;
    rater: {
        id: string;
    };
}

interface ReviewsResponse {
    data: Review[];
    links: any[];
    meta: any;
}

export class ReviewsStore {
    private static instance: ReviewsStore;

    public reviews: Ref<ReviewsResponse | null>;
    public loadingReviews: Ref<boolean>;

    private constructor() {
        this.reviews = ref(null);
        this.loadingReviews = ref(false);
    }

    public static getInstance(): ReviewsStore {
        if (!ReviewsStore.instance) {
            ReviewsStore.instance = new ReviewsStore();
        }
        return ReviewsStore.instance;
    }

    public async loadReviews(
        gameId: number,
        perPage: number,
        page: number = 1,
        showAllRatings: boolean = false,
        selectedRating: number | null = null
    ): Promise<void> {
        this.loadingReviews.value = true;
        try {
            const response = await axios.get<ReviewsResponse>(`/api/games/${gameId}/reviews`, {
                params: {
                    perPage,
                    page,
                    showAllRatings,
                    selectedRating
                }
            });

            if (response.data) {
                this.reviews.value = response.data;
            } else {
                console.error('No data returned from reviews API');
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
        } finally {
            this.loadingReviews.value = false;
        }
    }
}

// Export a function to get the singleton instance
export const useReviewsStore = () => ReviewsStore.getInstance();
