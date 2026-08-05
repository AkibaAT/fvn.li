import http from '@/utils/http';

export interface UserReview {
    id: number;
    rating: number;
    review: string;
    has_spoilers: boolean;
    published_at: string;
    updated_at: string;
}

export interface UserReviewPayload {
    rating: number;
    review: string;
    has_spoilers: boolean;
}

export async function submitUserReview(gameId: number, payload: UserReviewPayload): Promise<{ message: string; review: UserReview }> {
    const { data } = await http.post<{ message: string; review: UserReview }>(
        route('browser-api.user-reviews.store', { game: gameId }),
        payload,
    );
    return data;
}

export async function deleteUserReview(gameId: number): Promise<string> {
    const { data } = await http.delete<{ message: string }>(route('browser-api.user-reviews.destroy', { game: gameId }));
    return data.message;
}
