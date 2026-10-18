<script lang="ts">
    import { Badge } from '@/components/ui';
    import { cn } from '@/utils/cn';
    import type { PrivateTagRef } from '@/types/game';

    let { tags, class: className = '' }: { tags: PrivateTagRef[]; class?: string } = $props();
</script>

{#if tags.length > 0}
    <ul class={cn('flex flex-wrap gap-1.5', className)} aria-label="Private tags">
        {#each tags as tag (tag.id)}
            <li>
                <Badge tone="neutral" size="sm" class="border-dashed font-medium">{tag.name}</Badge>
            </li>
        {/each}
    </ul>
{/if}
