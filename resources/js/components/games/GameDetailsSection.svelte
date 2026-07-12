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
    <Card padding="lg">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Game Details</h2>
        <dl class="grid grid-cols-1 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            {#each detailItems as item (item.label)}
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{item.label}</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{item.value}</dd>
                </div>
            {/each}
        </dl>

        {#if visibleSupportedLanguages.length > 0}
            <div class="mt-4">
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Supported Languages</h3>
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

    <Card padding="lg">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Tags</h2>
        <div class="flex flex-wrap items-center gap-2">
            {#each game.tags || [] as tag (tag.id)}
                <Link
                    href={route('games.index', { selectedTags: [tag.id], noDefaults: true })}
                    class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    {tag.name}
                </Link>
            {/each}
        </div>
    </Card>

    {#if game.game_jams && game.game_jams.length > 0}
        <Card padding="lg" class="md:col-span-2">
            <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Game Jams</h2>
            <div class="space-y-4">
                {#each game.game_jams as jam (jam.id)}
                    <div class="border-b border-gray-200 pb-3 last:border-0 last:pb-0 dark:border-gray-700">
                        <h3 class="font-medium text-gray-900 dark:text-gray-100">
                            {#if jam.url}
                                <a href={jam.url} target="_blank" rel="noopener" class="hover:text-blue-600 dark:hover:text-blue-400">
                                    <span
                                        class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                    >
                                        {jam.name}
                                    </span>
                                </a>
                            {:else}
                                <span
                                    class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-sm text-blue-700 dark:bg-blue-900/50 dark:text-blue-300"
                                >
                                    {jam.name}
                                </span>
                            {/if}
                        </h3>
                        {#if jam.start_date && jam.end_date}
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {formatLocalDate(jam.start_date)} - {formatLocalDate(jam.end_date)}
                            </p>
                        {/if}
                        {#if jam.theme}
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Theme:</span>
                                {jam.theme}
                            </p>
                        {/if}
                        {#if jam.submission_count}
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Submissions:</span>
                                {jam.submission_count.toLocaleString()}
                                {#if jam.participant_count}
                                    <span class="ml-1 text-gray-600 dark:text-gray-400">({jam.participant_count.toLocaleString()} participants)</span>
                                {/if}
                            </p>
                        {/if}
                        {#if jam.pivot?.ranking}
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Game Rank:</span>
                                <span
                                    class="ml-1 rounded-full bg-blue-200 px-1.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-800 dark:text-blue-200"
                                >
                                    {jam.pivot.ranking}
                                </span>
                            </p>
                        {/if}
                        {#if jam.pivot?.criteria_rankings}
                            {@const parsed = parseCriteriaRankings(jam.pivot.criteria_rankings)}
                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Criteria Rankings:</span>
                                <ul class="mt-1 ml-4 list-disc space-y-1">
                                    {#each Object.entries(parsed) as [criteria, details] (criteria)}
                                        <li>
                                            <span class="font-medium">{criteria}:</span>
                                            {#if details?.rank}
                                                {details.rank}
                                                {#if details.score}
                                                    <span
                                                        class="ml-1 rounded bg-blue-100 px-1 py-0.5 text-xs text-blue-600 dark:bg-blue-900/30 dark:text-blue-400"
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
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Hosted by {jam.host}</p>
                        {/if}
                    </div>
                {/each}
            </div>
        </Card>
    {/if}
</div>
