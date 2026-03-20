<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    type Tag = NonNullable<GameCardGame['tags']>[0];

    let {
        tag,
        isActive = false,
        onclick,
    }: {
        tag: Tag;
        isActive?: boolean;
        onclick: (tagId: number) => void;
    } = $props();
</script>

<button
    onclick={() => onclick(tag.id)}
    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200 {isActive
        ? 'cursor-pointer border-2 border-blue-700 bg-blue-600 text-white shadow-md dark:border-blue-500 dark:bg-blue-700'
        : 'cursor-pointer border border-gray-200 bg-white text-gray-600 hover:bg-blue-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-blue-900/20'}"
    title={isActive ? 'Click to remove this filter' : 'Click to filter by this tag'}
>
    {tag.name}
</button>
