import http from '@/utils/http';

/**
 * Get the CSRF token from the meta tag
 */
export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Get default headers for authenticated requests
 */
export function getAuthHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
    };
}

/**
 * Refresh the CSRF token from the server
 * Updates both the meta tag and returns the new token
 */
export async function refreshCsrfToken(): Promise<string> {
    const response = await fetch('/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
    });
    const data = await response.json();

    if (data.token) {
        // Update the meta tag so subsequent calls to getCsrfToken() get the new token
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            metaTag.setAttribute('content', data.token);
        }

        // Also update axios defaults on the http instance
        http.defaults.headers.common['X-CSRF-TOKEN'] = data.token;

        return data.token;
    }

    throw new Error('Failed to refresh CSRF token');
}

/**
 * Make an authenticated fetch request with CSRF token
 * Automatically retries once with a fresh token on 419 (CSRF mismatch) errors
 */
export async function authenticatedFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const makeRequest = (token: string): Promise<Response> => {
        const defaultHeaders: Record<string, string> = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        };
        const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
        if (!isFormData) {
            defaultHeaders['Content-Type'] = 'application/json';
        }

        const defaultOptions: RequestInit = {
            credentials: 'same-origin',
            headers: defaultHeaders,
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers,
                'X-CSRF-TOKEN': token, // Ensure fresh token is used
            },
        };

        return fetch(url, mergedOptions);
    };

    // First attempt with current token
    let response = await makeRequest(getCsrfToken());

    // If we get a 419 (CSRF token mismatch), refresh the token and retry once
    if (response.status === 419) {
        try {
            const newToken = await refreshCsrfToken();
            response = await makeRequest(newToken);
        } catch {
            // If token refresh fails, return the original 419 response
            return response;
        }
    }

    return response;
}
