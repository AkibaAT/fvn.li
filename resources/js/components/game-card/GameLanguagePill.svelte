<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import { Button } from '@/components/ui';

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

<Button
    bind:ref
    onclick={() => onclick(language.iso_code)}
    variant={isActive ? 'solid' : 'outline'}
    tone="info"
    size="xs"
    class="inline-flex cursor-pointer items-center rounded border px-1.5 py-1 text-xs transition-colors {isActive
        ? 'border-indigo-700 dark:border-indigo-500'
        : 'border-gray-200 text-gray-700 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200'}"
    title={language.ref_name}
    aria-label={language.ref_name}
    aria-pressed={isActive}
>
    <span class="fi fi-{language.flag_code} rounded-xs"></span>
</Button>
