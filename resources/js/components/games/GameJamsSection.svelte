<script lang="ts">
    import { Badge, Card } from '@/components/ui';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { parseCriteriaRankings } from '@/utils/game-show';
    import type { GameJam } from '@/types/game-show';

    let { gameJams }: { gameJams: GameJam[] } = $props();
</script>

{#if gameJams.length > 0}
    <Card id="game-jams" variant="flat" padding="lg" class="mb-6 scroll-mt-32">
        <h2 class="mb-4 text-title font-semibold text-fg">Game Jams</h2>
        <div class="space-y-4">
            {#each gameJams as jam (jam.id)}
                <div class="border-b border-border pb-4 last:border-0 last:pb-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-md font-medium text-fg">
                            {#if jam.url}
                                <a href={jam.url} target="_blank" rel="noopener" class="hover:underline">{jam.name}</a>
                            {:else}
                                {jam.name}
                            {/if}
                        </h3>
                        {#if jam.pivot?.ranking}
                            <Badge tone="neutral" size="sm">Rank {jam.pivot.ranking}</Badge>
                        {/if}
                    </div>
                    {#if jam.start_date && jam.end_date}
                        <p class="mt-0.5 text-ui text-fg-faint">
                            {formatLocalDate(jam.start_date)} - {formatLocalDate(jam.end_date)}{#if jam.host}&nbsp;&middot; Hosted by {jam.host}{/if}
                        </p>
                    {:else if jam.host}
                        <p class="mt-0.5 text-ui text-fg-faint">Hosted by {jam.host}</p>
                    {/if}
                    {#if jam.theme}
                        <p class="mt-2 text-sm text-fg-muted">
                            <span class="font-medium text-fg">Theme:</span>
                            {jam.theme}
                        </p>
                    {/if}
                    {#if jam.submission_count}
                        <p class="mt-1 text-sm text-fg-muted">
                            <span class="font-medium text-fg">Submissions:</span>
                            {jam.submission_count.toLocaleString()}
                            {#if jam.participant_count}
                                ({jam.participant_count.toLocaleString()} participants)
                            {/if}
                        </p>
                    {/if}
                    {#if jam.pivot?.criteria_rankings}
                        {@const parsed = parseCriteriaRankings(jam.pivot.criteria_rankings)}
                        {@const entries = Object.entries(parsed).filter(([, details]) => details?.rank)}
                        {#if entries.length > 0}
                            <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
                                {#each entries as [criteria, details] (criteria)}
                                    <div class="flex items-baseline justify-between gap-2">
                                        <dt class="text-fg-muted">{criteria}</dt>
                                        <dd class="text-fg tabular-nums">
                                            {details.rank}
                                            {#if details.score}<span class="text-fg-faint">({details.score})</span>{/if}
                                        </dd>
                                    </div>
                                {/each}
                            </dl>
                        {/if}
                    {/if}
                </div>
            {/each}
        </div>
    </Card>
{/if}
