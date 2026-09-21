<script lang="ts">
    import { cn } from '@/utils/cn';

    interface GameTag {
        id: number;
        name: string;
    }

    interface GameTagListProps {
        tags: GameTag[];
        selectedTags?: string[];
        onTagClick: (tagId: number) => void;
        limit?: number;
        class?: string;
    }

    let { tags, selectedTags = [], onTagClick, limit = 8, class: className = '' }: GameTagListProps = $props();

    const visibleTags = $derived(tags.slice(0, limit));
    const hiddenTagCount = $derived(Math.max(0, tags.length - limit));
</script>

{#snippet tagButton(tag: GameTag)}
    {@const isTagActive = selectedTags.includes(String(tag.id))}
    <button
        type="button"
        data-tag-id={tag.id}
        onclick={() => onTagClick(tag.id)}
        class="cursor-pointer hover:underline {isTagActive ? 'font-semibold text-fg' : 'text-fg-muted'}"
        title={isTagActive ? 'Click to remove this filter' : 'Click to filter by this tag'}
    >
        {tag.name}
    </button>
{/snippet}

<div class={cn('flex flex-wrap gap-x-1 text-xs leading-snug text-fg-muted', className)} data-tag-list>
    {#each visibleTags as tag, index (tag.id)}
        {@const isLast = index === visibleTags.length - 1}
        <span class="whitespace-nowrap">
            {@render tagButton(tag)}{#if !isLast}<span class="text-fg-faint">,</span>{/if}
            {#if isLast && hiddenTagCount > 0}<span class="text-fg-faint">+{hiddenTagCount}</span>{/if}
        </span>
    {/each}
</div>
