import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

export default {
    preprocess: vitePreprocess(),

    // Accessibility problems fail the compile instead of scrolling past in the log.
    onwarn(warning, defaultHandler) {
        if (warning.code.startsWith('a11y_')) {
            const at = warning.start ? `:${warning.start.line}:${warning.start.column}` : '';
            throw new Error(`a11y error in ${warning.filename ?? 'unknown file'}${at}\n${warning.message}\nhttps://svelte.dev/e/${warning.code}`);
        }

        defaultHandler(warning);
    },
};
