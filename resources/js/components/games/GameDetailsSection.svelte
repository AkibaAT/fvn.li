<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { getLanguageFlag, parseCriteriaRankings } from '@/utils/game-show';

    interface GameDetailsSectionProps {
        game: any;
        detailItems: Array<{ label: string; value: string }>;
        visibleSupportedLanguages: any[];
    }

    let { game, detailItems, visibleSupportedLanguages }: GameDetailsSectionProps = $props();
</script>

<div id="details" class="mb-6 grid scroll-mt-28 grid-cols-1 gap-6 md:grid-cols-2">
    <Card variant="flat" padding="lg">
        <h2 class="mb-4 text-xl font-semibold text-fg">Game Details</h2>
        <dl class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            {#each detailItems as item (item.label)}
                <div>
                    <dt class="text-sm text-fg-faint">{item.label}</dt>
                    <dd class="text-fg">{item.value}</dd>
                </div>
            {/each}
        </dl>

        {#if visibleSupportedLanguages.length > 0}
            <div class="mt-4">
                <h3 class="mb-2 text-sm font-semibold text-fg">Supported Languages</h3>
                <div class="flex flex-wrap gap-1" aria-label="Languages">
                    {#each visibleSupportedLanguages as sl (sl.iso_code)}
                        <img
                            src={getLanguageFlag(sl.language.flag_code)}
                            alt={sl.language.ref_name}
                            title={sl.language.ref_name}
                            class="h-4 w-4 rounded-sm"
                        />
                    {/each}
                </div>
            </div>
        {/if}
    </Card>

    <Card variant="flat" padding="lg">
        <h2 class="mb-4 text-xl font-semibold text-fg">Tags</h2>
        <div class="flex flex-wrap items-center gap-2">
            {#each game.tags || [] as tag (tag.id)}
                <Link
                    href={route('games.index', { selectedTags: [tag.id], noDefaults: true })}
                    class="rounded-full bg-surface-alt px-3 py-1 text-sm text-fg-muted transition-colors hover:bg-border hover:text-fg"
                >
                    {tag.name}
                </Link>
            {/each}
        </div>
    </Card>

    {#if game.game_jams && game.game_jams.length > 0}
        <Card variant="flat" padding="lg" class="md:col-span-2">
            <h2 class="mb-4 text-xl font-semibold text-fg">Game Jams</h2>
            <div class="space-y-4">
                {#each game.game_jams as jam (jam.id)}
                    <div class="border-b border-border pb-3 last:border-0 last:pb-0">
                        <h3 class="font-medium text-fg">
                            {#if jam.url}
                                <a href={jam.url} target="_blank" rel="noopener">
                                    <span
                                        class="inline-flex items-center rounded-md border border-border bg-surface-alt px-2 py-1 text-sm text-fg-muted transition-colors hover:border-border-strong hover:text-fg"
                                    >
                                        {jam.name}
                                    </span>
                                </a>
                            {:else}
                                <span class="inline-flex items-center rounded-md border border-border bg-surface-alt px-2 py-1 text-sm text-fg-muted">
                                    {jam.name}
                                </span>
                            {/if}
                        </h3>
                        {#if jam.start_date && jam.end_date}
                            <p class="text-sm text-fg-muted">
                                {formatLocalDate(jam.start_date)} - {formatLocalDate(jam.end_date)}
                            </p>
                        {/if}
                        {#if jam.theme}
                            <p class="mt-1 text-sm text-fg-muted">
                                <span class="font-medium">Theme:</span>
                                {jam.theme}
                            </p>
                        {/if}
                        {#if jam.submission_count}
                            <p class="mt-1 text-sm text-fg-muted">
                                <span class="font-medium">Submissions:</span>
                                {jam.submission_count.toLocaleString()}
                                {#if jam.participant_count}
                                    <span class="ml-1 text-fg-muted">({jam.participant_count.toLocaleString()} participants)</span>
                                {/if}
                            </p>
                        {/if}
                        {#if jam.pivot?.ranking}
                            <p class="mt-1 text-sm text-fg-muted">
                                <span class="font-medium">Game Rank:</span>
                                <span class="ml-1 rounded-full border border-border bg-surface-alt px-1.5 py-0.5 text-xs font-medium text-fg-muted">
                                    {jam.pivot.ranking}
                                </span>
                            </p>
                        {/if}
                        {#if jam.pivot?.criteria_rankings}
                            {@const parsed = parseCriteriaRankings(jam.pivot.criteria_rankings)}
                            <div class="mt-1 text-sm text-fg-muted">
                                <span class="font-medium">Criteria Rankings:</span>
                                <ul class="mt-1 ml-4 list-disc space-y-1">
                                    {#each Object.entries(parsed) as [criteria, details] (criteria)}
                                        <li>
                                            <span class="font-medium">{criteria}:</span>
                                            {#if details?.rank}
                                                {details.rank}
                                                {#if details.score}
                                                    <span
                                                        class="ml-1 rounded-[3px] border border-border bg-surface-alt px-1 py-0.5 text-xs text-fg-muted"
                                                    >
                                                        (Score: {details.score})
                                                    </span>
                                                {/if}
                                            {/if}
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        {/if}
                        {#if jam.host}
                            <p class="mt-2 text-xs text-fg-muted">Hosted by {jam.host}</p>
                        {/if}
                    </div>
                {/each}
            </div>
        </Card>
    {/if}
</div>
