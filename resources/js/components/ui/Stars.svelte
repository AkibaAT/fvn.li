<script lang="ts">
    import StarIcon from '@/components/icons/Star.svelte';
    interface Props {
        rating: number;
        class?: string;
    }

    let { rating, class: className = '' }: Props = $props();

    const stars = [1, 2, 3, 4, 5];
</script>

<div class={`flex items-center gap-0.5 text-accent ${className}`}>
    {#each stars as i (i)}
        <StarIcon class={`h-5 w-5 ${i <= Math.floor(rating) ? 'fill-current' : 'fill-border'}`} />
    {/each}
</div>
