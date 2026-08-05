import http from '@/utils/http';

export type ReviewReportReason = 'hate_speech' | 'spam' | 'harassment' | 'spoilers' | 'off_topic' | 'other';

export async function submitReviewReport(ratingId: number, reason: ReviewReportReason, details: string | null): Promise<string> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('browser-api.review-reports.store', { rating: ratingId }), {
        reason,
        details,
    });
    if (!data.success) throw new Error(data.message || 'Failed to submit report');
    return data.message || 'Report submitted.';
}
