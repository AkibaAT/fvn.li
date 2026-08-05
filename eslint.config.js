import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import svelte from 'eslint-plugin-svelte';
import globals from 'globals';
import ts from 'typescript-eslint';

export default ts.config(
    js.configs.recommended,
    ...ts.configs.recommended,
    ...svelte.configs['flat/recommended'],
    {
        files: ['**/*.svelte'],
        languageOptions: {
            parserOptions: {
                parser: ts.parser,
            },
        },
    },
    {
        files: ['**/*.svelte.ts'],
        languageOptions: {
            parser: ts.parser,
        },
    },
    {
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'no-undef': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                },
            ],
        },
    },
    {
        // The axios instance is the api layer's private transport; components
        // go through the typed modules in resources/js/api instead.
        files: ['resources/js/**/*.{ts,svelte,svelte.ts}'],
        ignores: ['resources/js/api/**', 'resources/js/utils/http.ts', 'resources/js/utils/http.test.ts', 'resources/js/types/global.d.ts'],
        rules: {
            'no-restricted-imports': [
                'error',
                {
                    paths: [
                        {
                            name: '@/utils/http',
                            importNames: ['default'],
                            message: 'Import a typed function from resources/js/api instead of using the HTTP transport directly.',
                        },
                        {
                            name: 'axios',
                            message: 'Import a typed function from resources/js/api instead of using axios directly.',
                        },
                    ],
                },
            ],
        },
    },
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'storage',
            'build',
            'bootstrap/ssr',
            'resources/js/ziggy.js',
            'eslint.config.js',
            'vite.config.ts',
        ],
    },
    prettier,
);
