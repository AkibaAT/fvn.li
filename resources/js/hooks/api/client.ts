/**
 * Shared API client utilities for TanStack Query
 */

export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export async function apiFetch<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            ...options.headers,
        },
    });

    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }

    return response.json();
}

export interface ApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
}

export async function apiGet<T>(url: string): Promise<T> {
    const response = await apiFetch<ApiResponse<T>>(url);
    if (!response.success) {
        throw new Error(response.message || 'Request failed');
    }
    return response.data;
}

export async function apiPost<T>(url: string, body?: unknown): Promise<T> {
    const response = await apiFetch<ApiResponse<T>>(url, {
        method: 'POST',
        body: body ? JSON.stringify(body) : undefined,
    });
    if (!response.success) {
        throw new Error(response.message || 'Request failed');
    }
    return response.data;
}
