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
 * Make an authenticated fetch request with CSRF token
 */
export async function authenticatedFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const defaultOptions: RequestInit = {
        credentials: 'same-origin',
        headers: getAuthHeaders(),
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers,
        },
    };

    return fetch(url, mergedOptions);
}
