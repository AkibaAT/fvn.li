<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import CollapsiblePillSection from './CollapsiblePillSection.svelte';

    let {
        orderedTags,
        selectedTags = [],
        tagsExpanded,
        setTagsExpanded,
        handleTag,
    }: {
        orderedTags: GameCardGame['tags'];
        selectedTags?: string[];
        tagsExpanded: boolean;
        setTagsExpanded: (expanded: boolean) => void;
        handleTag: (tagId: number) => void;
    } = $props();

    const COLLAPSED_TAG_LIMIT = 10;
    const renderedTags = $derived(tagsExpanded ? (orderedTags ?? []) : (orderedTags ?? []).slice(0, COLLAPSED_TAG_LIMIT));
    const totalHiddenTagCount = $derived(tagsExpanded ? 0 : Math.max(0, (orderedTags?.length ?? 0) - renderedTags.length));
</script>

{#if orderedTags && orderedTags.length > 0}
    <CollapsiblePillSection
        expanded={tagsExpanded}
        hiddenCount={totalHiddenTagCount}
        onToggle={() => setTagsExpanded(!tagsExpanded)}
        itemName="tags"
        collapsedClass="h-15 overflow-hidden"
    >
        {#each renderedTags as tag (tag.id)}
            {@const isActive = selectedTags.includes(String(tag.id))}
            <button
                data-tag-id={tag.id}
                onclick={() => handleTag(tag.id)}
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200 {isActive
                    ? 'cursor-pointer border-2 border-blue-700 bg-blue-600 text-white shadow-md dark:border-blue-500 dark:bg-blue-700'
                    : 'cursor-pointer border border-gray-200 bg-white text-gray-600 hover:bg-blue-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-blue-900/20'}"
                title={isActive ? 'Click to remove this filter' : 'Click to filter by this tag'}
            >
                {tag.name}
            </button>
        {/each}
    </CollapsiblePillSection>
{/if}
