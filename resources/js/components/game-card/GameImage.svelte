<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import GamepadIcon from '@/components/icons/Gamepad.svelte';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';
    import { gameCoverAltText } from '@/utils/imageAltText';
    import type { Snippet } from 'svelte';

    interface GameImageProps {
        /** Needs only the identity/platform fields; any game payload shape fits. */
        game: Pick<GameCardGame, 'effective_name' | 'slug' | 'platform'>;
        thumbnailUrl: string | null;
        /** Sizing classes for the link box; defaults to the card cover ratio. */
        class?: string;
        /** Extra classes for the img, e.g. hover transitions. */
        imgClass?: string;
        /** Alt text override; defaults to `<name> cover image`. */
        alt?: string;
        /** Img title attribute. */
        title?: string;
        /** No-image content rendered instead of the default gamepad placeholder. */
        fallback?: Snippet;
        /** Overlays rendered inside the box after the image, e.g. carousel controls. */
        children?: Snippet;
        /** Forwarded to the link, e.g. carousel keyboard controls. */
        onkeydown?: (event: KeyboardEvent) => void;
    }

    let {
        game,
        thumbnailUrl,
        class: className = 'aspect-[315/250]',
        imgClass = '',
        alt,
        title,
        fallback,
        children,
        onkeydown,
    }: GameImageProps = $props();

    const gameName = $derived(game.effective_name);
    const altText = $derived(alt ?? gameCoverAltText(gameName));
    const isSteamGame = $derived(game.platform === 'steam');
    const objectFitClass = $derived(isSteamGame ? 'object-contain' : 'object-cover');
</script>

<Link
    href={route('games.show', game.slug)}
    class="relative block overflow-hidden rounded-md bg-surface-alt {className}"
    aria-label="View details for {gameName}"
    {onkeydown}
>
    {#if thumbnailUrl}
        <img src={thumbnailUrl} alt={altText} {title} loading="lazy" decoding="async" class="h-full w-full {objectFitClass} {imgClass}" />
    {:else if fallback}
        {@render fallback()}
    {:else}
        <div class="flex h-full w-full items-center justify-center text-fg-faint">
            <GamepadIcon class="h-8 w-8" />
        </div>
    {/if}
    {#if children}
        {@render children()}
    {/if}
</Link>
