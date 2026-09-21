<script lang="ts">
    import { untrack } from 'svelte';
    import GameFlags from './GameFlags.svelte';
    import GameTagList from './GameTagList.svelte';
    import GlyphStrip from './GlyphStrip.svelte';
    import ListRow from '@/components/lists/ListRow.svelte';
    import { Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import { formatWordCount, formatWordCountBreakdown } from '@/utils/game-card-display';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { gameCoverAltText } from '@/utils/imageAltText';

    let props: GameCardProps = $props();

    const {
        thumbnailUrl,
        authorsInlineHtml,
        supportedPlatforms,
        storePlatform,
        handlePlatform,
        handleLanguage,
        handleTag,
        handleStatus,
        handleStorePlatform,
        handleNsfwToggle,
        handlePaidToggle,
        handleDemoToggle,
        handleSaleToggle,
        orderedTags,
    } = untrack(() => useGameCard(props));

    const { game, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, selectedStorePlatforms, nsfw, showPaid, showDemo, showSale } =
        $derived(props);

    const wordCount = $derived(formatWordCount(game));
    const wordCountBreakdown = $derived(formatWordCountBreakdown(game) ?? undefined);
    const rowDate = $derived(
        formatLocalDate(game.latest_version_published_at ?? null) ?? formatLocalDate(game.initially_published_at ?? null) ?? null,
    );
    const version = $derived.by(() => {
        const raw = typeof game.latest_version_number === 'string' ? game.latest_version_number.trim() : '';
        if (!raw) return null;
        return /^v/i.test(raw) ? raw : `v${raw}`;
    });

    const hasAuthorLine = $derived(Boolean(game.authors) || Boolean(wordCount) || Boolean(rowDate));
    const hasFlags = $derived.by(() => {
        const status = (typeof game.status === 'string' ? game.status.trim() : '').toLowerCase();
        const hasStatusFlag = Boolean(status) && status !== 'released' && status !== 'published';
        return hasStatusFlag || Boolean(game.is_nsfw) || Boolean(game.is_paid) || Boolean(game.has_demo) || Boolean(game.is_on_sale);
    });
    const hasRating = $derived(typeof game.rating_score === 'number' && game.rating_score > 0);
    const showSmFlagsLine = $derived(hasFlags || hasRating);
</script>

<ListRow data-game-update-row>
    {#snippet media()}
        <div class="h-9 w-12 shrink-0 overflow-hidden rounded-sm bg-surface-alt sm:h-11.5 sm:w-16 lg:h-13 lg:w-18">
            {#if thumbnailUrl}
                <img
                    src={thumbnailUrl}
                    alt={gameCoverAltText(game.effective_name)}
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover"
                />
            {/if}
        </div>
    {/snippet}

    {#snippet body()}
        <div class="flex items-center gap-1.5">
            <h2 class="min-w-0 truncate text-sm leading-tight font-semibold text-fg">
                <a href={route('games.show', game.slug)} class="hover:underline" aria-label="View details for {game.effective_name}">
                    {game.effective_name}
                </a>
            </h2>

            <GameFlags
                {game}
                {selectedStatuses}
                {nsfw}
                {showPaid}
                {showDemo}
                {showSale}
                onStatusClick={handleStatus}
                onNsfwToggle={handleNsfwToggle}
                onPaidToggle={handlePaidToggle}
                onDemoToggle={handleDemoToggle}
                onSaleToggle={handleSaleToggle}
                class="hidden shrink-0 md:inline-flex"
            />

            {#if version}
                <span class="ml-auto shrink-0 text-ui text-fg md:hidden">{version}</span>
            {/if}
        </div>

        {#if hasAuthorLine}
            <div class="mt-1.5 flex items-baseline gap-2">
                <span class="min-w-0 truncate text-ui leading-snug text-fg-muted">
                    {#if game.authors}
                        <span class="[&_a:hover]:underline">
                            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                            {@html authorsInlineHtml}
                        </span>
                    {/if}
                    {#if wordCount}
                        <span class="xl:hidden">
                            {#if game.authors}<span class="text-fg-faint"> · </span>{/if}
                            <span>{wordCount}</span>
                        </span>
                    {/if}
                    {#if rowDate && (game.authors || wordCount)}
                        <span class="text-fg-faint md:hidden"> · {rowDate}</span>
                    {/if}
                </span>

                <span class="ml-auto hidden shrink-0 md:block lg:hidden">
                    <Rating score={game.rating_score} count={game.rating_count} />
                </span>
            </div>
        {/if}

        {#if showSmFlagsLine}
            <div class="mt-1.5 flex items-center justify-between gap-2 md:hidden">
                <GameFlags
                    {game}
                    {selectedStatuses}
                    {nsfw}
                    {showPaid}
                    {showDemo}
                    {showSale}
                    onStatusClick={handleStatus}
                    onNsfwToggle={handleNsfwToggle}
                    onPaidToggle={handlePaidToggle}
                    onDemoToggle={handleDemoToggle}
                    onSaleToggle={handleSaleToggle}
                    class="shrink-0"
                />
                <Rating score={game.rating_score} count={game.rating_count} />
            </div>
        {/if}

        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1">
            <GameTagList tags={orderedTags} {selectedTags} onTagClick={handleTag} limit={8} class="min-w-0 flex-1 flex-nowrap overflow-hidden" />

            <GlyphStrip
                {storePlatform}
                isStoreActive={selectedStorePlatforms?.includes(storePlatform)}
                onStoreClick={handleStorePlatform}
                platforms={supportedPlatforms}
                {selectedPlatforms}
                onPlatformClick={handlePlatform}
                languages={game.supported_languages ?? []}
                {selectedLanguages}
                onLanguageClick={handleLanguage}
                class="shrink-0"
            />
        </div>
    {/snippet}

    {#snippet aside()}
        <div class="hidden w-30 shrink-0 text-right text-ui leading-snug xl:block">
            {#if wordCount}
                <span class="text-fg-muted" title={wordCountBreakdown}>{wordCount}</span>
            {:else}
                <span class="text-fg-faint">—</span>
            {/if}
        </div>

        <div class="hidden w-18 shrink-0 text-right lg:block">
            {#if hasRating}
                <Rating score={game.rating_score} count={game.rating_count} />
            {:else}
                <span class="text-ui text-fg-faint">—</span>
            {/if}
        </div>

        <div class="hidden w-22 shrink-0 text-right md:block">
            {#if version || rowDate}
                {#if version}
                    <div class="truncate text-ui font-medium text-fg">{version}</div>
                {/if}
                {#if rowDate}
                    <div class="text-xs text-fg-faint">{rowDate}</div>
                {/if}
            {:else}
                <span class="text-ui text-fg-faint">—</span>
            {/if}
        </div>
    {/snippet}
</ListRow>
