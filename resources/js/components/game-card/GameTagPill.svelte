<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import { Button } from '@/components/ui';

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

<Button
    onclick={() => onclick(tag.id)}
    variant={isActive ? 'solid' : 'outline'}
    tone="primary"
    size="xs"
    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200 {isActive
        ? 'border-2 border-blue-700 shadow-md dark:border-blue-500'
        : 'border-gray-200 text-gray-600 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200'}"
    title={isActive ? 'Click to remove this filter' : 'Click to filter by this tag'}
>
    {tag.name}
</Button>
