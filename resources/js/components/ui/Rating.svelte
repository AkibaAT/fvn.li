<script lang="ts" module>
    export interface RatingProps {
        score?: number | null;
        count?: number | null;
        class?: string;
    }
</script>

<script lang="ts">
    import { cn } from '@/utils/cn';
    import StarIcon from '@/components/icons/Star.svelte';

    let { score, count, class: className = '' }: RatingProps = $props();

    const hasScore = $derived(typeof score === 'number' && Number.isFinite(score) && score > 0);
    const hasCount = $derived(typeof count === 'number' && Number.isFinite(count) && count > 0);

    const ariaLabel = $derived(`Rated ${score?.toFixed(1)} out of 5${hasCount ? ` from ${count?.toLocaleString()} reviews` : ''}`);
</script>

{#if hasScore}
    <span
        class={cn('inline-flex items-center gap-1 text-fg', className)}
        role="img"
        aria-label={ariaLabel}
        title={ariaLabel}
    >
        <StarIcon class="h-2.75 w-2.75 fill-current text-accent" />
        <span class="text-xs leading-none font-semibold text-fg" aria-hidden="true">{score?.toFixed(1)}</span>
        {#if hasCount}
            <span class="text-xs leading-none text-fg-faint" aria-hidden="true">({count?.toLocaleString()})</span>
        {/if}
    </span>
{/if}
