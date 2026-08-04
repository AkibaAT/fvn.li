import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        conditions: ['browser'],
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
            'ziggy-js': resolve(import.meta.dirname, 'vendor/tightenco/ziggy'),
        },
    },
    test: {
        include: ['resources/**/*.test.ts'],
        exclude: ['tests/e2e/**', 'tests/Browser/**', 'node_modules/**'],
        environment: 'jsdom',
        setupFiles: ['tests/frontend/setup.ts'],
        passWithNoTests: true,
        coverage: {
            provider: 'v8',
            reportsDirectory: 'build/frontend-coverage',
            reporter: ['text', 'text-summary', 'html', 'lcov'],
            include: [
                'resources/js/components/games/screenshotState.ts',
                'resources/js/constants/**/*.ts',
                'resources/js/utils/accessibility.ts',
                'resources/js/utils/csrf.ts',
                'resources/js/utils/date-formatting.ts',
                'resources/js/utils/dialog.ts',
                'resources/js/utils/safe-highlight.ts',
                'resources/js/utils/status-indicators.ts',
                'resources/js/utils/style-html.ts',
                'resources/js/utils/toast.ts',
            ],
            exclude: ['resources/js/**/*.test.ts', 'resources/js/**/*.d.ts', 'resources/js/utils/http.ts'],
            thresholds: {
                lines: 70,
                statements: 70,
                functions: 70,
                branches: 60,
            },
        },
    },
});
