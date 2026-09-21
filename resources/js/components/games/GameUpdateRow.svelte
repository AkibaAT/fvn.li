<script lang="ts">
    import { untrack } from 'svelte';
    import GameFlags from './GameFlags.svelte';
    import GlyphStrip from './GlyphStrip.svelte';
    import ListRow from '@/components/lists/ListRow.svelte';
    import { Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import { usePlatformIcons } from '@/hooks/usePlatformIcons';
    import { useStorePlatformIcons } from '@/hooks/useStorePlatformIcons';
    import { formatWordCount } from '@/utils/game-card-display';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { gameCoverAltText } from '@/utils/imageAltText';

    let props: GameCardProps = $props();

    const {
        thumbnailUrl,
        authorsInlineHtml,
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

    const { getSupportedPlatforms } = usePlatformIcons();
    const { getStorePlatformFromString } = useStorePlatformIcons();

    const supportedPlatforms = $derived(getSupportedPlatforms(game));
    const storePlatform = $derived(game.platform ? getStorePlatformFromString(game.platform) : 'itch_io');
    const wordCount = $derived(formatWordCount(game, null));
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
    const TAG_LIMIT = 8;
    const visibleTags = $derived(orderedTags.slice(0, TAG_LIMIT));
    const hiddenTagCount = $derived(Math.max(0, orderedTags.length - TAG_LIMIT));

    const hasRating = $derived(typeof game.rating_score === 'number' && game.rating_score > 0);
    const showSmFlagsLine = $derived(hasFlags || hasRating);
</script>

<ListRow data-game-update-row>
    {#snippet media()}
        <div class="h-9 w-12 shrink-0 overflow-hidden rounded-[4px] bg-surface-alt sm:h-[46px] sm:w-16 lg:h-[52px] lg:w-[72px]">
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
            <h2 class="min-w-0 truncate text-[14px] leading-tight font-semibold tracking-[-0.01em] text-fg">
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
                <span class="ml-auto shrink-0 text-[12.5px] text-fg md:hidden">{version}</span>
            {/if}
        </div>

        {#if hasAuthorLine}
            <!-- `mt-1.5` keeps >=3px clearance below the 18px-tall flag chips, which satisfies the
                 WCAG 2.2 AA target-size (2.5.8) spacing exception without growing them to 24px. -->
            <div class="mt-1.5 flex items-baseline gap-2">
                <span class="min-w-0 truncate text-[12.5px] leading-snug text-fg-muted">
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

        <!-- Tags fill the line so the glyph strip lands at the body's right edge. -->
        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1">
            {#if orderedTags.length > 0}
                <div class="min-w-0 flex-1 truncate text-[12px] leading-snug text-fg-muted" data-tag-list>
                    {#each visibleTags as tag, index (tag.id)}
                        {@const isTagActive = selectedTags?.includes(String(tag.id)) ?? false}
                        {#if index > 0}<span class="text-fg-faint">, </span>{/if}
                        <button
                            type="button"
                            data-tag-id={tag.id}
                            onclick={() => handleTag(tag.id)}
                            class="cursor-pointer hover:underline {isTagActive ? 'font-semibold text-fg' : 'text-fg-muted'}"
                            title={isTagActive ? 'Click to remove this filter' : 'Click to filter by this tag'}
                        >
                            {tag.name}
                        </button>
                    {/each}
                    {#if hiddenTagCount > 0}
                        <span class="text-fg-faint"> +{hiddenTagCount}</span>
                    {/if}
                </div>
            {/if}

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
                variant="row"
                class="shrink-0"
            />
        </div>
    {/snippet}

    {#snippet aside()}
        <div class="hidden w-30 shrink-0 text-right text-[12.5px] leading-snug text-fg-muted xl:block">{wordCount ?? '—'}</div>

        <div class="hidden w-[72px] shrink-0 text-right lg:block">
            <Rating score={game.rating_score} count={game.rating_count} />
        </div>

        <div class="hidden w-[88px] shrink-0 text-right md:block">
            {#if version}
                <div class="truncate text-[12.5px] font-medium text-fg">{version}</div>
            {/if}
            {#if rowDate}
                <div class="text-[12px] text-fg-faint">{rowDate}</div>
            {/if}
        </div>
    {/snippet}
</ListRow>
