<script lang="ts">
    import PhotoPlaceholderIcon from '@/components/icons/PhotoPlaceholder.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Badge, Card } from '@/components/ui';
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
        compact ? 'line-clamp-2 text-xs font-medium text-fg group-hover:underline' : 'line-clamp-2 text-sm font-medium text-fg group-hover:underline',
    );
    const ratingClass = $derived(compact ? 'flex items-center gap-0.5 text-[10px] text-accent' : 'flex items-center gap-0.5 text-xs text-accent');
    const starClass = $derived(compact ? 'h-3 w-3 fill-current' : 'h-3.5 w-3.5 fill-current');
    const placeholderClass = $derived(compact ? 'h-6 w-6 text-fg-faint' : 'h-8 w-8 text-fg-faint');
</script>

{#if games && games.length > 0}
    <Card {id} variant="flat" padding="lg" class={compact ? 'mt-6' : 'mt-6 scroll-mt-28'}>
        <h2 class="mb-4 text-xl font-semibold text-fg">{title}</h2>
        <div class={gridClass}>
            {#each games as game (game.id)}
                <Link href={route('games.show', game.slug)} class="group block">
                    <Card variant="flat" padding="none" hover class="flex h-full flex-col overflow-hidden">
                        <div class="relative aspect-[315/250] w-full overflow-hidden bg-surface-alt">
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
                                <p class="mt-1 line-clamp-1 text-xs text-fg-muted">{game.authors}</p>
                            {/if}
                            <div class={compact ? 'mt-auto flex items-center gap-1 pt-1' : 'mt-auto flex items-center gap-2 pt-2'}>
                                {#if typeof game.rating_score === 'number' && game.rating_score > 0}
                                    <span class={ratingClass}>
                                        <StarIcon class={starClass} />
                                        {game.rating_score.toFixed(1)}
                                    </span>
                                {/if}
                                {#if game.status}
                                    <Badge tone="neutral" size="sm">{game.status}</Badge>
                                {/if}
                            </div>
                        </div>
                    </Card>
                </Link>
            {/each}
        </div>
    </Card>
{/if}
