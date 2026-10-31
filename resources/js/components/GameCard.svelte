<script lang="ts">
    import { untrack } from 'svelte';
    import GameCardUserSection from './GameCardUserSection.svelte';
    import GameImage from './game-card/GameImage.svelte';
    import GameFlags from './games/GameFlags.svelte';
    import GameIgnoreButton from './games/GameIgnoreButton.svelte';
    import GameTagList from './games/GameTagList.svelte';
    import LanguageGlyphs from './games/glyphs/LanguageGlyphs.svelte';
    import PlatformGlyphs from './games/glyphs/PlatformGlyphs.svelte';
    import StoreGlyph from './games/glyphs/StoreGlyph.svelte';
    import { Card, Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import {
        GAME_TAG_LIMIT,
        canReceiveUpdates,
        formatReleasedDate,
        formatReleaseDates,
        formatUpdatedDate,
        formatWordCount,
        formatWordCountBreakdown,
    } from '@/utils/game-card-display';

    interface Props extends GameCardProps {
        compact?: boolean;
    }

    let props: Props = $props();

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

    const { game, compact, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, nsfw, showPaid, showDemo, showSale, showLost } =
        $derived(props);

    const wordCountLabel = $derived(formatWordCount(game, { compact: true }));
    const wordCountTitle = $derived(formatWordCountBreakdown(game) ?? undefined);
    const releasedDate = $derived(formatReleasedDate(game));
    const updatedDate = $derived(formatUpdatedDate(game));
    const datesTitle = $derived(formatReleaseDates(game) ?? undefined);
    const rowSpanClass = $derived(compact ? 'row-span-4' : auth?.user ? 'row-span-9 max-sm:row-span-8' : 'row-span-8 max-sm:row-span-7');
</script>

<Card
    variant="flat"
    padding="none"
    hover
    class="group relative grid grid-cols-1 grid-rows-subgrid content-start gap-x-0 gap-y-1.5 px-3 pt-3 pb-3.5 {rowSpanClass}"
    data-game-card
>
    {#if auth?.user && !compact}
        <GameIgnoreButton gameId={game.id} {isIgnored} class="absolute top-4 right-4 z-10" />
    {/if}

    <div class="relative mb-2">
        <GameImage {game} {thumbnailUrl} />

        <div class="absolute right-9 bottom-1.25 left-2 flex flex-nowrap gap-1 overflow-hidden">
            {#snippet flags(limit: number)}
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
                    {limit}
                    class="bg-surface shadow-sm"
                />
            {/snippet}
            <div class="contents sm:hidden">{@render flags(1)}</div>
            <div class="hidden sm:contents">{@render flags(2)}</div>
        </div>

        {#if storePlatform && !compact}
            <StoreGlyph
                {storePlatform}
                overlay
                active={props.selectedStorePlatforms?.includes(storePlatform)}
                onclick={handleStorePlatform}
                class="absolute right-1.75 bottom-1.5"
            />
        {/if}
    </div>

    <h2 class="line-clamp-2 text-md leading-snug font-semibold break-words text-fg">
        <a href={route('games.show', game.slug)} class="hover:underline" aria-label="View details for {game.effective_name}">
            {game.effective_name}
        </a>
    </h2>

    <div class="truncate text-ui leading-snug text-fg-muted [&_a:hover]:underline">
        {#if game.authors}
            <span class="sr-only">Authors: </span>
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html authorsInlineHtml}
        {/if}
    </div>

    {#if compact}
        <Rating score={game.rating_score} count={game.rating_count} class="pt-1" />
    {:else}
        <div class="flex items-center justify-between gap-x-2 pt-1.5">
            <span
                class="min-w-0 truncate text-ui leading-snug {wordCountLabel ? 'text-fg-muted' : 'text-fg-faint'}"
                title={wordCountTitle ?? wordCountLabel ?? undefined}
            >
                {wordCountLabel ?? '—'}
            </span>
            <Rating score={game.rating_score} count={game.rating_count} class="shrink-0" />
        </div>

        <div class="text-xs leading-snug max-sm:hidden" title={datesTitle}>
            {#if releasedDate}
                <div class="truncate text-fg-muted">Released {releasedDate}</div>
                {#if updatedDate}
                    <div class="truncate text-fg-faint">Updated {updatedDate}</div>
                {/if}
            {/if}
        </div>

        <PlatformGlyphs platforms={supportedPlatforms} {selectedPlatforms} onPlatformClick={handlePlatform} class="-ml-0.75" />

        <LanguageGlyphs languages={game.supported_languages ?? []} {selectedLanguages} onLanguageClick={handleLanguage} class="-my-1.25 -ml-0.75" />

        <GameTagList tags={orderedTags} {selectedTags} onTagClick={handleTag} limit={GAME_TAG_LIMIT} class="content-start" />

        {#if auth?.user}
            <GameCardUserSection
                gameId={game.id}
                gameName={game.name}
                canReceiveUpdates={canReceiveUpdates(game)}
                userProgress={game.user_progress?.[0] ?? null}
                listMemberships={game.user_list_memberships ?? []}
                privateTags={game.user_private_tags ?? []}
            />
        {/if}
    {/if}
</Card>
