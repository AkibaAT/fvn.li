<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import GamepadIcon from '@/components/icons/Gamepad.svelte';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import { gameCoverAltText } from '@/utils/imageAltText';

    let {
        game,
        thumbnailUrl,
        aspectClass = 'aspect-[315/250]',
    }: {
        game: GameCardGame;
        thumbnailUrl: string | null;
        aspectClass?: string;
    } = $props();

    const gameName = $derived(game.effective_name);
    const isSteamGame = $derived(game.platform === 'steam');
    const objectFitClass = $derived(isSteamGame ? 'object-contain' : 'object-cover');
</script>

<Link
    href={route('games.show', game.slug)}
    class="relative block overflow-hidden rounded-md bg-surface-alt {aspectClass}"
    aria-label="View details for {gameName}"
>
    {#if thumbnailUrl}
        <img src={thumbnailUrl} alt={gameCoverAltText(gameName)} loading="lazy" decoding="async" class="h-full w-full {objectFitClass}" />
    {:else}
        <div class="flex h-full w-full items-center justify-center text-fg-faint">
            <GamepadIcon class="h-8 w-8" />
        </div>
    {/if}
</Link>
