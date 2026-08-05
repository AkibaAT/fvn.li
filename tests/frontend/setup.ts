import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/svelte';
import { afterEach, vi } from 'vitest';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.restoreAllMocks();
    // Node-environment suites (SSR rendering) have no DOM to reset.
    if (typeof document !== 'undefined') {
        document.head.innerHTML = '';
        document.body.innerHTML = '';
    }
});
