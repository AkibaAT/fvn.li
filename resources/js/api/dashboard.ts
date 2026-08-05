import http from '@/utils/http';

export interface AdditionRequest {
    id: number;
    game_url: string;
    platform?: string;
    status: string;
    status_label: string;
    status_color: string;
    created_at: string;
    reviewed_at?: string;
    rejection_reason?: string;
    game?: { id: number; name: string; slug: string };
    reviewer?: { name: string };
}

export interface AdditionSubmissionResult {
    success_count: number;
    duplicate_count: number;
    invalid_count: number;
    already_exists_count?: number;
    errors: string[];
}

export async function fetchAdditionRequests(filters: { status: string; search?: string }): Promise<AdditionRequest[]> {
    const { data } = await http.get<{ success: boolean; message?: string; requests: AdditionRequest[] }>(
        route('browser-api.dashboard.addition-requests.index'),
        { params: filters },
    );
    if (!data.success) throw new Error(data.message || 'Failed to load addition requests.');
    return data.requests;
}

export async function submitAdditionRequests(urls: string): Promise<{
    success: boolean;
    message?: string;
    result: AdditionSubmissionResult;
}> {
    const { data } = await http.post(route('browser-api.dashboard.addition-requests.submit'), { urls });
    return data;
}

export async function cancelAdditionRequest(id: number): Promise<void> {
    const { data } = await http.post<{ success: boolean; message?: string }>(
        route('browser-api.dashboard.addition-requests.cancel', { request: id }),
        {},
    );
    if (!data.success) throw new Error(data.message || 'Failed to cancel addition request.');
}

export async function disconnectSocialAccount(provider: string): Promise<string | undefined> {
    const { data } = await http.delete<{ success?: boolean; message?: string }>(route('user.disconnect', { provider }), {
        headers: { Accept: 'application/json' },
    });
    if (!data.success) throw new Error(data.message || 'Failed to disconnect account.');
    return data.message;
}
