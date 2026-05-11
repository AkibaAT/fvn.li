<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

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

<Link href={route('games.show', game.slug)} class="relative block overflow-hidden rounded-t-2xl bg-gray-100 dark:bg-gray-700">
    <div class="relative {aspectClass}">
        {#if thumbnailUrl}
            <img
                src={thumbnailUrl}
                alt={gameName}
                loading="lazy"
                decoding="async"
                class="h-full w-full {objectFitClass} transition-transform duration-500 group-hover:scale-110"
            />
        {:else}
            <div class="flex h-full w-full items-center justify-center text-gray-400">
                <div class="text-center">
                    <div class="mb-3 text-5xl opacity-50">🎮</div>
                    <div class="text-sm font-medium">No Image Available</div>
                </div>
            </div>
        {/if}

        <!-- Gradient overlay -->
        <div class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>

        <!-- Hover CTA -->
        <div class="absolute inset-0 flex items-center justify-center opacity-0 transition-all duration-300 group-hover:opacity-100">
            <div
                class="translate-y-4 transform rounded-xl bg-white/90 px-6 py-3 font-bold text-gray-900 shadow-xl backdrop-blur-sm transition-all duration-300 group-hover:translate-y-0 hover:bg-white"
            >
                View Details →
            </div>
        </div>
    </div>
</Link>
