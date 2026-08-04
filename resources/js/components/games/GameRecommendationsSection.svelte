<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import { gameCoverAltText } from '@/utils/imageAltText';

    type RecommendedGame = {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
        authors?: string;
        platform?: string;
        rating_score?: number;
        status?: string;
    };

    let {
        games,
        title,
        id = undefined,
        compact = false,
    }: {
        games: RecommendedGame[];
        title: string;
        id?: string;
        compact?: boolean;
    } = $props();

    const gridClass = $derived(
        compact
            ? 'grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6'
            : 'grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6',
    );
    const titleClass = $derived(
        compact
            ? 'line-clamp-2 text-xs font-medium text-gray-900 group-hover:text-blue-600 dark:text-gray-100 dark:group-hover:text-blue-400'
            : 'line-clamp-2 text-sm font-medium text-gray-900 group-hover:text-blue-600 dark:text-gray-100 dark:group-hover:text-blue-400',
    );
    const ratingClass = $derived(
        compact
            ? 'flex items-center gap-0.5 text-[10px] text-yellow-600 dark:text-yellow-400'
            : 'flex items-center gap-0.5 text-xs text-yellow-600 dark:text-yellow-400',
    );
    const starClass = $derived(compact ? 'h-3 w-3 fill-current' : 'h-3.5 w-3.5 fill-current');
    const placeholderClass = $derived(compact ? 'h-6 w-6 text-gray-400' : 'h-8 w-8 text-gray-400');
</script>

{#if games && games.length > 0}
    <Card {id} padding="lg" class={compact ? 'mt-6' : 'mt-6 scroll-mt-28'}>
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
        <div class={gridClass}>
            {#each games as game (game.id)}
                <Link href={route('games.show', game.slug)} class="group block">
                    <Card
                        variant="glass"
                        padding="none"
                        class="flex h-full flex-col overflow-hidden rounded-xl shadow transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="relative aspect-[315/250] w-full overflow-hidden bg-gray-200 dark:bg-gray-700">
                            {#if game.thumb_url}
                                <img
                                    src={game.thumb_url}
                                    alt={gameCoverAltText(game.name)}
                                    class="h-full w-full {game.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                                    loading="lazy"
                                />
                            {:else}
                                <div class="flex h-full w-full items-center justify-center">
                                    <svg class={placeholderClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                </div>
                            {/if}
                        </div>
                        <div class={compact ? 'flex flex-1 flex-col p-2' : 'flex flex-1 flex-col p-3'}>
                            <h3 class={titleClass}>{game.name}</h3>
                            {#if !compact && game.authors}
                                <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{game.authors}</p>
                            {/if}
                            <div class={compact ? 'mt-auto flex items-center gap-1 pt-1' : 'mt-auto flex items-center gap-2 pt-2'}>
                                {#if typeof game.rating_score === 'number' && game.rating_score > 0}
                                    <span class={ratingClass}>
                                        <svg class={starClass} viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                            />
                                        </svg>
                                        {game.rating_score.toFixed(1)}
                                    </span>
                                {/if}
                                {#if game.status}
                                    <span
                                        class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        {game.status}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </Card>
                </Link>
            {/each}
        </div>
    </Card>
{/if}
