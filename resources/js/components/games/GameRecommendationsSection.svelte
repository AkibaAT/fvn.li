<script lang="ts">
    import PhotoPlaceholderIcon from '@/components/icons/PhotoPlaceholder.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
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
                                    <PhotoPlaceholderIcon class={placeholderClass} />
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
                                        <StarIcon class={starClass} />
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
