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
    import { formatLatestDate, formatReleaseDates, formatWordCount, formatWordCountBreakdown } from '@/utils/game-card-display';

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
        orderedTags,
    } = card;

    const auth = $derived(card.auth);
    const isIgnored = $derived(card.isIgnored);

    const { game, compact, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, nsfw, showPaid, showDemo, showSale } =
        $derived(props);

    const wordCountLabel = $derived(formatWordCount(game, { compact: true, withLanguage: false }));
    const wordCountTitle = $derived(formatWordCountBreakdown(game) ?? formatWordCount(game) ?? undefined);
    const dateLabel = $derived(formatLatestDate(game));
    const datesTitle = $derived(formatReleaseDates(game) ?? undefined);
    const rowSpanClass = $derived(compact ? 'row-span-4' : auth?.user ? 'row-span-9 max-sm:row-span-8' : 'row-span-8 max-sm:row-span-7');
</script>

<Card
    variant="flat"
    padding="none"
    hover
    class="group relative grid grid-rows-subgrid content-start gap-x-0 gap-y-1.5 px-3 pt-3 pb-3.5 {rowSpanClass}"
    data-game-card
>
    {#if auth?.user && !compact}
        <GameIgnoreButton gameId={game.id} {isIgnored} class="absolute top-4 right-4 z-10" />
    {/if}

    <div class="relative mb-2">
        <GameImage {game} {thumbnailUrl} />

        <div class="absolute bottom-2 left-2 flex flex-wrap gap-1">
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
                class="bg-surface shadow-sm"
            />
        </div>

        {#if storePlatform && !compact}
            <StoreGlyph
                {storePlatform}
                overlay
                active={props.selectedStorePlatforms?.includes(storePlatform)}
                onclick={handleStorePlatform}
                class="absolute right-2 bottom-2"
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
        <div class="flex items-center justify-between gap-2 pt-1.5 max-sm:flex-col max-sm:items-start max-sm:gap-0.5">
            <span class="min-w-0 truncate text-ui leading-snug {wordCountLabel ? 'text-fg-muted' : 'text-fg-faint'}" title={wordCountTitle}>
                {wordCountLabel ?? '—'}
            </span>
            <Rating score={game.rating_score} count={game.rating_count} class="shrink-0" />
        </div>

        <div class="truncate text-xs leading-snug text-fg-faint max-sm:hidden" title={datesTitle}>{dateLabel ?? ''}</div>

        <PlatformGlyphs platforms={supportedPlatforms} {selectedPlatforms} onPlatformClick={handlePlatform} class="pt-1.5" />

        <LanguageGlyphs languages={game.supported_languages ?? []} {selectedLanguages} onLanguageClick={handleLanguage} />

        <GameTagList tags={orderedTags} {selectedTags} onTagClick={handleTag} limit={5} class="content-start" />

        {#if auth?.user}
            <GameCardUserSection
                gameId={game.id}
                gameName={game.name}
                isPaid={game.is_paid}
                userProgress={game.user_progress?.[0] ?? null}
                listMemberships={game.user_list_memberships ?? []}
            />
        {/if}
    {/if}
</Card>
