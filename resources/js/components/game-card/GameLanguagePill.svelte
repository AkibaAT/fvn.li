<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    type Language = NonNullable<GameCardGame['supported_languages']>[0];

    let {
        language,
        isActive = false,
        onclick,
        ref = $bindable(null),
    }: {
        language: Language;
        isActive?: boolean;
        onclick: (iso: string) => void;
        ref?: HTMLButtonElement | null;
    } = $props();
</script>

<button
    bind:this={ref}
    onclick={() => onclick(language.iso_code)}
    class="inline-flex cursor-pointer items-center rounded border px-1.5 py-1 text-xs transition-colors {isActive
        ? 'border-indigo-700 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-700'
        : 'border-gray-200 bg-white text-gray-700 hover:bg-indigo-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-indigo-900/20'}"
    title={language.ref_name}
    aria-label={language.ref_name}
    aria-pressed={isActive}
>
    <span class="fi fi-{language.flag_code} rounded-xs"></span>
</button>
