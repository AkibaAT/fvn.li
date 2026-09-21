<script lang="ts">
    import { formatLocalDate } from '@/utils/date-formatting';
    import { Badge, Button, PlatformIcon, Stars } from '@/components/ui';
    import ExternalLinkIcon from '@/components/icons/ExternalLink.svelte';
    import { Link } from '@inertiajs/svelte';
    import type { RatingRowData } from './types';

    type Props = {
        row: RatingRowData;
        reviewStyle: string;
        showRater?: boolean;
    };

    let { row, reviewStyle, showRater = false }: Props = $props();
    let spoilerRevealed = $state(false);

    const formattedDate = $derived(formatLocalDate(row.date) ?? '');
    const sourcePlatform = $derived(row.sourcePlatform ?? row.rater?.externalPlatform);
</script>

{#snippet platformMark()}
    {#if row.isFvnReview}
        <Badge variant="outline" tone="neutral">FVN.li</Badge>
    {:else if sourcePlatform}
        <PlatformIcon platform={sourcePlatform} />
    {/if}
{/snippet}

<div class="p-4 sm:p-6">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <Link href={route('games.show', { game: row.game.slug })} class="text-lg font-medium text-fg hover:underline">
                    {row.game.name}
                </Link>
                {#if !showRater}{@render platformMark()}{/if}
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
                        class="text-fg-muted hover:text-fg"
                        title="Open on external platform"
                    >
                        <ExternalLinkIcon class="h-4 w-4" />
                    </a>
                {/if}
            </div>
            {#if showRater && (row.user || row.rater)}
                <div class="flex items-center gap-2 text-sm text-fg-muted">
                    {#if row.user}
                        <span class="inline-flex items-center gap-1">
                            by
                            <Link href={route('users.reviews', row.user.id)} class="inline-flex items-center gap-1 text-fg hover:underline">
                                {#if row.user.avatar}
                                    <img src={row.user.avatar} alt="" aria-hidden="true" class="h-4 w-4 rounded-full" />
                                {/if}
                                {row.user.name}
                            </Link>
                        </span>
                    {:else if row.rater}
                        <span>by <Link href={route('raters.show', row.rater.id)} class="text-fg hover:underline">{row.rater.name}</Link></span>
                    {/if}
                    {@render platformMark()}
                </div>
            {/if}
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:justify-end">
            <Stars rating={row.score} />
            {#if showRater}<div class="text-lg font-semibold text-fg">{row.score.toFixed(1)}</div>{/if}
            {#if formattedDate}<span class="text-sm text-fg-faint">{formattedDate}</span>{/if}
            {#if row.eventId}
                <a
                    href={`https://itch.io/event/${row.eventId}`}
                    target="_blank"
                    rel="noopener"
                    class="text-fg-muted hover:text-fg"
                    title="View on itch.io"
                >
                    <ExternalLinkIcon class="h-4 w-4" />
                </a>
            {/if}
        </div>
    </div>
    {#if row.review}
        {#if row.hasSpoilers && !spoilerRevealed}
            <Button type="button" variant="outline" tone="warning" size="xs" class="mt-2" onclick={() => (spoilerRevealed = true)}>
                Contains spoilers. Click to reveal.
            </Button>
        {:else}
            <div class="mx-auto prose mt-2 text-fg-muted dark:prose-invert" class:fvn-review={row.isFvnReview} style={reviewStyle}>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html row.review}
            </div>
        {/if}
    {/if}
</div>
