import { getCsrfToken, setCsrfToken } from '@/utils/csrf';
import axios from 'axios';

// Shared transport for the typed modules in resources/js/api — components call
// those modules instead of importing this instance directly (enforced by lint).
const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

async function refreshCsrfToken(): Promise<string> {
    const response = await fetch('/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const data = await response.json();

    if (!data.token) {
        throw new Error('Failed to refresh CSRF token');
    }

    setCsrfToken(data.token);
    http.defaults.headers.common['X-CSRF-TOKEN'] = data.token;

    return data.token;
}

http.interceptors.request.use((config) => {
    const token = getCsrfToken();
    if (token) {
        config.headers?.set('X-CSRF-TOKEN', token);
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        const config = error.config as (typeof error.config & { __csrfRetried?: boolean }) | undefined;

        if (error.response?.status === 419 && config && !config.__csrfRetried) {
            config.__csrfRetried = true;
            config.headers?.set('X-CSRF-TOKEN', await refreshCsrfToken());

            return http(config);
        }

        // Error responses carry the user-facing explanation in their JSON body;
        // surface it as the error message so plain `error.message` consumers show it.
        if (axios.isAxiosError(error)) {
            const message = responseMessage(error.response?.data);
            if (message) error.message = message;
        }

        throw error;
    },
);

export default http;

/**
 * Laravel validation errors from a failed request, keyed by field.
 */
export function httpValidationErrors(error: unknown): Record<string, string | string[]> | undefined {
    if (!axios.isAxiosError(error)) {
        return undefined;
    }

    const errors = (error.response?.data as { errors?: unknown } | undefined)?.errors;

    return typeof errors === 'object' && errors !== null ? (errors as Record<string, string | string[]>) : undefined;
}

function responseMessage(data: unknown): string | undefined {
    if (typeof data === 'object' && data !== null && 'message' in data) {
        const message = (data as { message?: unknown }).message;
        return typeof message === 'string' ? message : undefined;
    }
}
