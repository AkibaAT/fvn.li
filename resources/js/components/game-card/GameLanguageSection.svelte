<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import CollapsiblePillSection from './CollapsiblePillSection.svelte';

    let {
        languages,
        selectedLanguages = [],
        languagesExpanded,
        setLanguagesExpanded,
        handleLanguage,
    }: {
        languages: GameCardGame['supported_languages'];
        selectedLanguages?: string[];
        languagesExpanded: boolean;
        setLanguagesExpanded: (expanded: boolean) => void;
        handleLanguage: (iso: string) => void;
    } = $props();

    const COLLAPSED_LANGUAGE_LIMIT = 8;
    const renderedLanguages = $derived(languagesExpanded ? (languages ?? []) : (languages ?? []).slice(0, COLLAPSED_LANGUAGE_LIMIT));
    const totalHiddenLanguageCount = $derived(languagesExpanded ? 0 : Math.max(0, (languages?.length ?? 0) - renderedLanguages.length));
</script>

{#if languages && languages.length > 0}
    <CollapsiblePillSection
        expanded={languagesExpanded}
        hiddenCount={totalHiddenLanguageCount}
        onToggle={() => setLanguagesExpanded(!languagesExpanded)}
        itemName="languages"
        collapsedClass="h-6 overflow-hidden"
    >
        {#each renderedLanguages as language (language.iso_code)}
            {@const isActive = selectedLanguages.includes(language.iso_code)}
            <button
                onclick={() => handleLanguage(language.iso_code)}
                class="inline-flex cursor-pointer items-center rounded border px-1.5 py-1 text-xs transition-colors {isActive
                    ? 'border-indigo-700 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-700'
                    : 'border-gray-200 bg-white text-gray-700 hover:bg-indigo-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-indigo-900/20'}"
                title={language.ref_name}
                aria-label={language.ref_name}
                aria-pressed={isActive}
            >
                <span class="fi fi-{language.flag_code} rounded-xs"></span>
            </button>
        {/each}
    </CollapsiblePillSection>
{/if}
