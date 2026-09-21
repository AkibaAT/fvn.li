<script lang="ts">
    import { untrack } from 'svelte';
    import GameCardUserSection from '@/components/GameCardUserSection.svelte';
    import GameImage from '@/components/game-card/GameImage.svelte';
    import GameFlags from '@/components/games/GameFlags.svelte';
    import GameIgnoreButton from '@/components/games/GameIgnoreButton.svelte';
    import GlyphStrip from '@/components/games/GlyphStrip.svelte';
    import ListRow from '@/components/lists/ListRow.svelte';
    import { Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import { usePlatformIcons } from '@/hooks/usePlatformIcons';
    import { page } from '@inertiajs/svelte';
    import { useStorePlatformIcons } from '@/hooks/useStorePlatformIcons';
    import { formatReleasedDate, formatReleaseDates, formatUpdatedDate, formatWordCount } from '@/utils/game-card-display';

    /**
     * Browse-row rendering of a game, sharing `ListRow` with the home catalogue and
     * the reading lists. Takes the same props as `GameCard` so the games page can
     * swap between the two without reshaping its data.
     */
    let props: GameCardProps = $props();

    const {
        thumbnailUrl,
        authorsInlineHtml,
        handleTag,
        handlePlatform,
        handleLanguage,
        handleStatus,
        handleStorePlatform,
        handleNsfwToggle,
        handlePaidToggle,
        handleDemoToggle,
        handleSaleToggle,
        orderedTags,
    } = untrack(() => useGameCard(props));

    const {
        game,
        selectedTags,
        selectedPlatforms,
        selectedLanguages,
        selectedStatuses,
        selectedStorePlatforms,
        nsfw,
        showPaid,
        showDemo,
        showSale,
        ignoredGameIds,
    } = $derived(props);

    const { getSupportedPlatforms } = usePlatformIcons();
    const { getStorePlatformFromString } = useStorePlatformIcons();

    const auth = $derived((page as any).props?.auth);
    const isIgnored = $derived(ignoredGameIds?.includes(game.id) || false);
    const supportedPlatforms = $derived(getSupportedPlatforms(game));
    const storePlatform = $derived(game.platform ? getStorePlatformFromString(game.platform) : 'itch_io');

    const TAG_LIMIT = 8;
    const visibleTags = $derived(orderedTags.slice(0, TAG_LIMIT));
    const hiddenTagCount = $derived(Math.max(0, orderedTags.length - TAG_LIMIT));

    const wordCountLabel = $derived(formatWordCount(game) ?? 'Word count pending');
    const datesLabel = $derived(formatReleaseDates(game));
    const releasedDate = $derived(formatReleasedDate(game));
    const updatedDate = $derived(formatUpdatedDate(game));
</script>

<ListRow data-game-list-row>
    {#snippet media()}
        <GameImage {game} {thumbnailUrl} aspectClass="h-[72px] w-[100px]" />
    {/snippet}

    {#snippet body()}
        <h2 class="flex flex-wrap items-center gap-x-[5px] text-[15px] leading-tight font-semibold tracking-[-0.01em] text-fg">
            <a href={route('games.show', game.slug)} class="hover:underline" aria-label="View details for {game.effective_name}">
                {game.effective_name}
            </a>
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
            />
        </h2>

        {#if game.authors}
            <div class="mt-1 truncate text-[12.5px] leading-snug text-fg-muted [&_a:hover]:underline">
                <span class="sr-only">Authors: </span>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html authorsInlineHtml}
            </div>
        {/if}

        <!-- Below `xl` the meta stays inline; from `xl` the columns on the right carry it. -->
        <div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-[12.5px] leading-snug xl:hidden">
            <span class="text-fg-muted">{wordCountLabel}</span>
            {#if datesLabel}
                <span class="text-fg-faint">· {datesLabel}</span>
            {/if}
            <!-- The rating column starts at `md`, so the inline copy covers phones only. -->
            <span class="ml-auto shrink-0 md:hidden">
                <Rating score={game.rating_score} count={game.rating_count} />
            </span>
        </div>

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

        <GameCardUserSection
            gameId={game.id}
            gameName={game.name}
            isPaid={game.is_paid}
            userProgress={game.user_progress?.[0] ?? null}
            listMemberships={game.user_list_memberships ?? []}
        />
    {/snippet}

    {#snippet aside()}
        <div class="hidden w-30 shrink-0 text-right text-[12.5px] leading-snug text-fg-muted xl:block">
            {wordCountLabel}
        </div>

        <div class="hidden w-40 shrink-0 text-right text-[12px] leading-snug xl:block">
            {#if releasedDate}
                <div class="text-fg-muted">Released {releasedDate}</div>
                {#if updatedDate}
                    <div class="text-fg-faint">Updated {updatedDate}</div>
                {/if}
            {:else}
                <div class="text-fg-faint">Release date pending</div>
            {/if}
        </div>

        <div class="hidden w-24 shrink-0 text-right md:block">
            <Rating score={game.rating_score} count={game.rating_count} />
        </div>

        {#if auth?.user}
            <GameIgnoreButton gameId={game.id} {isIgnored} />
        {/if}
    {/snippet}
</ListRow>
