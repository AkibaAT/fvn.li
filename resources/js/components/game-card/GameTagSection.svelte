<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    let {
        orderedTags,
        selectedTags = [],
        hiddenTagCount,
        tagsExpanded,
        setTagsExpanded,
        tagContainerRef = $bindable(null),
        setTagRef,
        handleTag,
    }: {
        orderedTags: GameCardGame['tags'];
        selectedTags?: string[];
        hiddenTagCount: number;
        tagsExpanded: boolean;
        setTagsExpanded: (expanded: boolean) => void;
        tagContainerRef?: HTMLDivElement | null;
        setTagRef: (index: number) => (element: HTMLButtonElement | null) => void;
        handleTag: (tagId: number) => void;
    } = $props();

    function tagRefAction(node: HTMLButtonElement, index: number) {
        setTagRef(index)(node);
        return {
            destroy() {
                setTagRef(index)(null);
            }
        };
    }
</script>

{#if orderedTags && orderedTags.length > 0}
    <div class="border-t border-gray-100 pt-2 dark:border-gray-700/50">
        <div class="flex items-center gap-1.5">
            <div
                bind:this={tagContainerRef}
                class="relative flex flex-1 flex-wrap items-start gap-1.5 transition-all duration-300 {tagsExpanded ? 'max-h-none' : 'h-15 overflow-hidden'}"
            >
                {#each orderedTags as tag, index (tag.id)}
                    {@const isActive = selectedTags.includes(String(tag.id))}
                    <button
                        use:tagRefAction={index}
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
            </div>
            {#if hiddenTagCount > 0 && !tagsExpanded}
                <button
                    onclick={() => setTagsExpanded(!tagsExpanded)}
                    class="group flex h-6 w-6 flex-shrink-0 cursor-pointer items-center justify-center rounded-full transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Show {hiddenTagCount} more tags"
                    aria-label="Show {hiddenTagCount} more tags"
                >
                    <svg
                        class="h-4 w-4 rotate-0 text-gray-400 transition-all duration-200 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            {/if}
        </div>
    </div>
{/if}
