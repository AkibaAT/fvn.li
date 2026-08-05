import http from '@/utils/http';

export interface BugReportSummary {
    id: number;
    page_title?: string;
    description: string;
    status: string;
    status_label: string;
    status_color: string;
    unread_count: number;
    created_at: string;
}

export interface BugReportComment {
    id: number;
    message: string;
    is_from_admin: boolean;
    user: { id: number; name: string };
    created_at: string;
}

export interface BugReportDetail {
    id: number;
    page_url: string;
    page_title?: string;
    description: string;
    status: string;
    status_label: string;
    status_color: string;
    is_closed: boolean;
    created_at: string;
    resolved_at?: string;
}

export interface SubmitBugReportPayload {
    page_url: string;
    page_title: string;
    description: string;
    request_parameters: Record<string, string>;
}

function requireSuccess<T extends { success: boolean; message?: string }>(data: T): T {
    if (!data.success) throw new Error(data.message || 'Bug report request failed.');
    return data;
}

export async function submitBugReport(payload: SubmitBugReportPayload): Promise<string> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('browser-api.bug-reports.store'), payload);
    return requireSuccess(data).message || 'Bug report submitted.';
}

export async function fetchBugReport(id: number): Promise<{ report: BugReportDetail; comments: BugReportComment[] }> {
    const { data } = await http.get<{
        success: boolean;
        message?: string;
        report: BugReportDetail;
        comments: BugReportComment[];
    }>(route('browser-api.bug-reports.show', { bugReport: id }));
    return requireSuccess(data);
}

export async function addBugReportComment(id: number, message: string): Promise<BugReportComment> {
    const { data } = await http.post<{ success: boolean; message?: string; comment: BugReportComment }>(
        route('browser-api.bug-reports.comments.store', { bugReport: id }),
        { message },
    );
    return requireSuccess(data).comment;
}

export async function closeBugReport(id: number): Promise<void> {
    const { data } = await http.post<{ success: boolean; message?: string }>(route('browser-api.bug-reports.close', { bugReport: id }));
    requireSuccess(data);
}
