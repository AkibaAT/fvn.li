import axios from 'axios';
import '../css/app.css';

import {initializeAppearance} from '@/hooks/use-appearance';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {ReactQueryDevtools} from '@tanstack/react-query-devtools';
import {createInertiaApp} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createRoot} from 'react-dom/client';
import PersistentLayout from './layouts/PersistentLayout';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60, // 1 minute
            refetchOnWindowFocus: false,
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'FVN.li';

// Initialize a global axios instance configured for Laravel & Inertia
if (typeof window !== 'undefined') {
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    const csrf =
        typeof document !== 'undefined'
            ? document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content')
            : undefined;
    if (csrf) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
    }

    // Add response interceptor to handle CSRF token expiration (419 errors)
    // This happens when a tab is left open longer than the session lifetime
    let isRefreshingToken = false;
    let pendingRequests: Array<{
        resolve: (value: unknown) => void;
        reject: (reason: unknown) => void;
        config: ReturnType<typeof axios.request> extends Promise<infer R> ? Parameters<typeof axios.request>[0] : never;
    }> = [];

    axios.interceptors.response.use(
        (response) => response,
        async (error) => {
            const originalRequest = error.config;

            // Handle 419 (CSRF token mismatch) errors
            if (error.response?.status === 419 && !originalRequest._retry) {
                if (isRefreshingToken) {
                    // If already refreshing, queue this request
                    return new Promise((resolve, reject) => {
                        pendingRequests.push({ resolve, reject, config: originalRequest });
                    });
                }

                originalRequest._retry = true;
                isRefreshingToken = true;

                try {
                    // Fetch a fresh CSRF token
                    const response = await fetch('/csrf-token', {
                        method: 'GET',
                        credentials: 'same-origin',
                    });
                    const data = await response.json();

                    if (data.token) {
                        // Update axios defaults
                        axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;

                        // Update the meta tag so authenticatedFetch also gets the new token
                        const metaTag = document.querySelector('meta[name="csrf-token"]');
                        if (metaTag) {
                            metaTag.setAttribute('content', data.token);
                        }

                        // Update the original request's headers
                        originalRequest.headers['X-CSRF-TOKEN'] = data.token;

                        // Process any queued requests
                        pendingRequests.forEach(({ resolve, config }) => {
                            if (config.headers) {
                                config.headers['X-CSRF-TOKEN'] = data.token;
                            }
                            resolve(axios(config));
                        });
                        pendingRequests = [];

                        // Retry the original request
                        return axios(originalRequest);
                    }
                } catch (refreshError) {
                    // If refresh fails, reject all pending requests
                    pendingRequests.forEach(({ reject }) => reject(refreshError));
                    pendingRequests = [];
                    return Promise.reject(refreshError);
                } finally {
                    isRefreshingToken = false;
                }
            }

            return Promise.reject(error);
        }
    );

    // Expose to window for code using window.axios
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    (window as any).axios = axios;
}

interface PageComponent {
    default: {
        layout?: ((pageContent: React.ReactNode) => React.ReactNode) | undefined;
    };
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        return resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ).then((page) => {
            // Force all pages to use persistent layout
            (page as PageComponent).default.layout = (pageContent: React.ReactNode) => <PersistentLayout children={pageContent} />;
            return page;
        });
    },
    setup({el, App, props}) {
        const root = createRoot(el);

        root.render(
            <QueryClientProvider client={queryClient}>
                <App {...props} />
                <ReactQueryDevtools initialIsOpen={false} />
            </QueryClientProvider>
        );
    },
    progress: {
        color: 'var(--color-progress-primary)',
    },
});

// Initialize appearance after hydration (preload script in Blade prevents initial flash)
initializeAppearance();
