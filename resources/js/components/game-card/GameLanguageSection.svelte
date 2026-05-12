<script lang="ts">
    import { Button } from '@/components/ui';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    let {
        languages,
        selectedLanguages = [],
        hiddenLanguageCount,
        languagesExpanded,
        setLanguagesExpanded,
        languageContainerRef = null,
        setLanguageContainer,
        setLanguageRef,
        handleLanguage,
    }: {
        languages: GameCardGame['supported_languages'];
        selectedLanguages?: string[];
        hiddenLanguageCount: number;
        languagesExpanded: boolean;
        setLanguagesExpanded: (expanded: boolean) => void;
        languageContainerRef?: HTMLDivElement | null;
        setLanguageContainer: (element: HTMLDivElement | null) => void;
        setLanguageRef: (index: number) => (element: HTMLButtonElement | null) => void;
        handleLanguage: (iso: string) => void;
    } = $props();

    function containerRefAction(node: HTMLDivElement) {
        setLanguageContainer(node);
        return {
            destroy() {
                setLanguageContainer(null);
            },
        };
    }

    function langRefAction(node: HTMLButtonElement, index: number) {
        setLanguageRef(index)(node);
        return {
            destroy() {
                setLanguageRef(index)(null);
            },
        };
    }
</script>

{#if languages && languages.length > 0}
    <div class="h-auto border-t border-gray-100 pt-2 dark:border-gray-700/50">
        <div class="flex items-center gap-1">
            <div
                bind:this={languageContainerRef}
                use:containerRefAction
                class="relative flex flex-1 flex-wrap items-start gap-1 transition-all duration-300 {languagesExpanded
                    ? 'max-h-none'
                    : 'h-6 overflow-hidden'}"
            >
                {#each languages as language, index (language.iso_code)}
                    {@const isActive = selectedLanguages.includes(language.iso_code)}
                    <Button
                        type="button"
                        variant="outline"
                        tone="neutral"
                        size="xs"
                        action={(node) => langRefAction(node as HTMLButtonElement, index)}
                        onclick={() => handleLanguage(language.iso_code)}
                        class="inline-flex cursor-pointer items-center rounded border px-1.5 py-1 text-xs transition-colors {isActive
                            ? 'border-indigo-700 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:bg-indigo-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-indigo-900/20'}"
                        title={language.ref_name}
                        aria-label={language.ref_name}
                        aria-pressed={isActive}
                    >
                        <span class="fi fi-{language.flag_code} rounded-xs"></span>
                    </Button>
                {/each}
            </div>
            {#if hiddenLanguageCount > 0 || languagesExpanded}
                <Button
                    type="button"
                    variant="ghost"
                    tone="neutral"
                    size="icon-sm"
                    onclick={() => setLanguagesExpanded(!languagesExpanded)}
                    class="group flex h-6 w-6 flex-shrink-0 cursor-pointer items-center justify-center rounded-full transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title={languagesExpanded ? 'Show less' : `Show ${hiddenLanguageCount} more languages`}
                    ariaLabel={languagesExpanded ? 'Show less' : `Show ${hiddenLanguageCount} more languages`}
                >
                    <svg
                        class="h-4 w-4 text-gray-400 transition-all duration-200 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300 {languagesExpanded
                            ? 'rotate-180'
                            : 'rotate-0'}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </Button>
            {/if}
        </div>
    </div>
{/if}
