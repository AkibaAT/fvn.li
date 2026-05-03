import axios from 'axios';

const http = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

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
