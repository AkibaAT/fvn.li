<script lang="ts">
    import { untrack } from 'svelte';
    import GameCardUserSection from '@/components/GameCardUserSection.svelte';
    import GameImage from '@/components/game-card/GameImage.svelte';
    import GameFlags from '@/components/games/GameFlags.svelte';
    import GameIgnoreButton from '@/components/games/GameIgnoreButton.svelte';
    import GameTagList from '@/components/games/GameTagList.svelte';
    import GlyphStrip from '@/components/games/GlyphStrip.svelte';
    import ListRow from '@/components/lists/ListRow.svelte';
    import { Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import {
        GAME_TAG_LIMIT,
        canReceiveUpdates,
        formatReleasedDate,
        formatUpdatedDate,
        formatWordCount,
        formatWordCountBreakdown,
    } from '@/utils/game-card-display';

    let props: GameCardProps = $props();

    const card = untrack(() => useGameCard(props));
    const {
        thumbnailUrl,
        authorsInlineHtml,
        supportedPlatforms,
        storePlatform,
        handleTag,
        handlePlatform,
        handleLanguage,
        handleStatus,
        handleStorePlatform,
        handleNsfwToggle,
        handlePaidToggle,
        handleDemoToggle,
        handleSaleToggle,
        handleLostToggle,
        orderedTags,
    } = card;

    const auth = $derived(card.auth);
    const isIgnored = $derived(card.isIgnored);

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
        showLost,
    } = $derived(props);

    const wordCount = $derived(formatWordCount(game));
    const wordCountBreakdown = $derived(formatWordCountBreakdown(game) ?? undefined);
    const hasRating = $derived(typeof game.rating_score === 'number' && game.rating_score > 0);
    const releasedDate = $derived(formatReleasedDate(game));
    const updatedDate = $derived(formatUpdatedDate(game));
</script>

<ListRow data-game-list-row>
    {#snippet media()}
        <GameImage {game} {thumbnailUrl} class="h-18 w-25" />
    {/snippet}

    {#snippet body()}
        <h2 class="flex flex-wrap items-center gap-x-1.25 text-md leading-tight font-semibold text-fg">
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
                {showLost}
                onStatusClick={handleStatus}
                onNsfwToggle={handleNsfwToggle}
                onPaidToggle={handlePaidToggle}
                onDemoToggle={handleDemoToggle}
                onSaleToggle={handleSaleToggle}
                onLostToggle={handleLostToggle}
            />
        </h2>

        {#if game.authors}
            <div class="mt-1 truncate text-ui leading-snug text-fg-muted [&_a:hover]:underline">
                <span class="sr-only">Authors: </span>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html authorsInlineHtml}
            </div>
        {/if}

        <div class="meta-list mt-1 text-ui leading-snug xl:hidden">
            {#if wordCount}
                <span class="text-fg-muted" title={wordCountBreakdown}>{wordCount}</span>
            {/if}
            {#if releasedDate}
                <span class="text-fg-faint">Released {releasedDate}</span>
            {/if}
            {#if updatedDate}
                <span class="text-fg-faint">Updated {updatedDate}</span>
            {/if}
        </div>

        <div class="mt-1.5 flex flex-col gap-y-1 sm:flex-row sm:items-center sm:gap-x-4">
            <GameTagList
                tags={orderedTags}
                {selectedTags}
                onTagClick={handleTag}
                limit={GAME_TAG_LIMIT}
                class="min-w-0 flex-nowrap overflow-hidden max-sm:block max-sm:truncate sm:flex-1"
            />

            <div class="flex min-w-0 items-center gap-3 sm:shrink-0">
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
                    class="min-w-0 justify-between md:w-60 lg:w-72"
                />
                <span class="ml-auto shrink-0 md:hidden">
                    <Rating score={game.rating_score} count={game.rating_count} />
                </span>
            </div>
        </div>

        <GameCardUserSection
            gameId={game.id}
            gameName={game.name}
            canReceiveUpdates={canReceiveUpdates(game)}
            userProgress={game.user_progress?.[0] ?? null}
            listMemberships={game.user_list_memberships ?? []}
            privateTags={game.user_private_tags ?? []}
        />
    {/snippet}

    {#snippet aside()}
        <div class="hidden w-30 shrink-0 text-right text-ui leading-snug xl:block">
            {#if wordCount}
                <span class="text-fg-muted" title={wordCountBreakdown}>{wordCount}</span>
            {:else}
                <span class="text-fg-faint">—</span>
            {/if}
        </div>

        <div class="hidden w-40 shrink-0 text-right text-xs leading-snug xl:block">
            {#if releasedDate}
                <div class="text-fg-muted">Released {releasedDate}</div>
                {#if updatedDate}
                    <div class="text-fg-faint">Updated {updatedDate}</div>
                {/if}
            {:else}
                <span class="text-ui text-fg-faint">—</span>
            {/if}
        </div>

        <div class="hidden w-24 shrink-0 text-right md:block">
            {#if hasRating}
                <Rating score={game.rating_score} count={game.rating_count} />
            {:else}
                <span class="text-ui text-fg-faint">—</span>
            {/if}
        </div>

        {#if auth?.user}
            <GameIgnoreButton gameId={game.id} {isIgnored} />
        {/if}
    {/snippet}
</ListRow>
