<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import { GAME_CARD_GRID_CLASS } from '@/utils/view-mode';

    let {
        games,
        title,
        id = undefined,
    }: {
        games: GameCardGame[];
        title: string;
        id?: string;
    } = $props();
</script>

{#if games.length > 0}
    <section {id} class="mt-10 scroll-mt-32">
        <h2 class="mb-3.5 text-title leading-none font-semibold text-fg">{title}</h2>
        <div class={GAME_CARD_GRID_CLASS}>
            {#each games as game (game.id)}
                <GameCard {game} compact />
            {/each}
        </div>
    </section>
{/if}
