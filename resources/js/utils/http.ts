import axios from 'axios';

const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export async function refreshCsrfToken(): Promise<string> {
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

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.token);
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

        throw error;
    },
);

export default http;

export function getAuthHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
    };
}

export async function authenticatedFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const makeRequest = (token: string): Promise<Response> => {
        const headers: Record<string, string> = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        };
        if (!(typeof FormData !== 'undefined' && options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { ...headers, ...options.headers, 'X-CSRF-TOKEN': token },
        });
    };

    let response = await makeRequest(getCsrfToken());
    if (response.status === 419) {
        try {
            response = await makeRequest(await refreshCsrfToken());
        } catch {
            return response;
        }
    }

    return response;
}

function responseMessage(data: unknown): string | undefined {
    if (typeof data === 'object' && data !== null && 'message' in data) {
        const message = (data as { message?: unknown }).message;
        return typeof message === 'string' ? message : undefined;
    }
}

export async function readJsonResponse<T = unknown>(response: Response): Promise<T> {
    const body = await response.text();
    let data: unknown = {};

    if (body.trim() !== '') {
        try {
            data = JSON.parse(body);
        } catch {
            throw new Error(response.ok ? 'Server returned invalid JSON.' : `Request failed with status ${response.status}.`);
        }
    }

    if (!response.ok) {
        throw new Error(responseMessage(data) || `Request failed with status ${response.status}.`);
    }

    return data as T;
}

export async function apiFetch<T>(url: string, options: RequestInit = {}): Promise<T> {
    return readJsonResponse<T>(
        await authenticatedFetch(url, {
            ...options,
            headers: { Accept: 'application/json', ...options.headers },
        }),
    );
}
