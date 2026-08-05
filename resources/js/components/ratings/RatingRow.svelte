<script lang="ts">
    import { Button, PlatformIcon, Stars } from '@/components/ui';
    import ExternalLinkIcon from '@/components/icons/ExternalLink.svelte';
    import { Link } from '@inertiajs/svelte';
    import type { RatingRowData } from './types';

    type Props = {
        row: RatingRowData;
        reviewStyle: string;
        showRater?: boolean;
    };

    let { row, reviewStyle, showRater = false }: Props = $props();

    const formattedDate = $derived(
        row.date
            ? new Date(row.date).toLocaleDateString(undefined, {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric',
              })
            : '',
    );
</script>

<div class="p-4 sm:p-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <Link
                    href={route('games.show', { game: row.game.slug })}
                    class="text-lg font-medium text-blue-600 hover:underline dark:text-blue-400"
                >
                    {row.game.name}
                </Link>
                {#if row.previousRatingCount && row.onOpenHistory}
                    <Button type="button" variant="link" tone="neutral" onclick={row.onOpenHistory}>
                        ({row.previousRatingCount} previous {row.previousRatingCount > 1 ? 'ratings' : 'rating'})
                    </Button>
                {/if}
                {#if row.game.primaryUrl}
                    <a
                        href={route('track.external-project', { game_id: row.game.id, url: row.game.primaryUrl })}
                        target="_blank"
                        rel="noopener"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        title="Open on external platform"
                    >
                        <ExternalLinkIcon class="h-4 w-4" />
                    </a>
                {/if}
            </div>
            {#if showRater && row.rater}
                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <span
                        >by <Link href={route('raters.show', row.rater.id)} class="text-gray-800 hover:underline dark:text-gray-100"
                            >{row.rater.name}</Link
                        ></span
                    >
                    {#if row.rater.externalPlatform}<PlatformIcon platform={row.rater.externalPlatform} />{/if}
                </div>
            {/if}
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
            <Stars rating={row.score} />
            {#if showRater}<div class="text-lg font-semibold text-gray-900 dark:text-gray-100">{row.score.toFixed(1)}</div>{/if}
            {#if formattedDate}<span class="text-sm text-gray-500 dark:text-gray-400">{formattedDate}</span>{/if}
            {#if row.eventId}
                <a
                    href={`https://itch.io/event/${row.eventId}`}
                    target="_blank"
                    rel="noopener"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    title="View on itch.io"
                >
                    <ExternalLinkIcon class="h-4 w-4" />
                </a>
            {/if}
        </div>
    </div>
    {#if row.review}
        <div class="mx-auto prose mt-2 text-gray-600 dark:text-gray-300 dark:prose-invert" style={reviewStyle}>
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html row.review}
        </div>
    {/if}
</div>
