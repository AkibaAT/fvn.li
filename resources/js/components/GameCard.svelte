<script lang="ts">
    import { untrack } from 'svelte';
    import GameCardUserSection from './GameCardUserSection.svelte';
    import GameImage from './game-card/GameImage.svelte';
    import GameFlags from './games/GameFlags.svelte';
    import GameIgnoreButton from './games/GameIgnoreButton.svelte';
    import GlyphStrip from './games/GlyphStrip.svelte';
    import { page } from '@inertiajs/svelte';
    import { Card, Rating } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import { usePlatformIcons } from '@/hooks/usePlatformIcons';
    import { useStorePlatformIcons } from '@/hooks/useStorePlatformIcons';
    import { formatReleaseDates, formatWordCount } from '@/utils/game-card-display';

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

    const { game, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, nsfw, showPaid, showDemo, showSale, ignoredGameIds } =
        $derived(props);

    const { getSupportedPlatforms } = usePlatformIcons();
    const { getStorePlatformFromString } = useStorePlatformIcons();

    const auth = $derived((page as any).props?.auth);
    const isIgnored = $derived(ignoredGameIds?.includes(game.id) || false);

    const supportedPlatforms = $derived(getSupportedPlatforms(game));
    const storePlatform = $derived(game.platform ? getStorePlatformFromString(game.platform) : 'itch_io');

    const TAG_LIMIT = 5;
    const visibleTags = $derived(orderedTags.slice(0, TAG_LIMIT));
    const hiddenTagCount = $derived(Math.max(0, orderedTags.length - TAG_LIMIT));

    const wordCountLabel = $derived(formatWordCount(game) ?? 'Word count pending');
    const datesLabel = $derived(formatReleaseDates(game));
</script>

<Card variant="flat" padding="none" hover class="group relative flex h-full flex-col gap-2 p-2">
    {#if auth?.user}
        <GameIgnoreButton gameId={game.id} {isIgnored} class="absolute top-3 right-3 z-10" />
    {/if}

    <GameImage {game} {thumbnailUrl} />

    <div class="flex flex-1 flex-col gap-[3px] px-1 pt-0.5 pb-1">
        <h2 class="line-clamp-2 text-[15px] leading-[1.3] font-semibold tracking-[-0.01em] break-words text-fg">
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
                class="ml-[5px] align-middle"
            />
        </h2>

        {#if game.authors}
            <div class="truncate text-[12.5px] leading-snug text-fg-muted [&_a:hover]:underline">
                <span class="sr-only">Authors: </span>
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html authorsInlineHtml}
            </div>
        {/if}

        <div class="mt-[5px] flex items-center justify-between gap-2">
            <span class="truncate text-[12.5px] leading-none text-fg-muted">{wordCountLabel}</span>
            <Rating score={game.rating_score} count={game.rating_count} />
        </div>

        {#if datesLabel}
            <div class="text-[12px] leading-snug text-fg-faint">{datesLabel}</div>
        {/if}

        <GlyphStrip
            {storePlatform}
            isStoreActive={props.selectedStorePlatforms?.includes(storePlatform)}
            onStoreClick={handleStorePlatform}
            platforms={supportedPlatforms}
            {selectedPlatforms}
            onPlatformClick={handlePlatform}
            languages={game.supported_languages ?? []}
            {selectedLanguages}
            onLanguageClick={handleLanguage}
            variant="card"
            class="mt-[5px]"
        />

        {#if orderedTags.length > 0}
            <div class="mt-1 text-[12px] leading-snug text-fg-muted" data-tag-list>
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

        <GameCardUserSection
            gameId={game.id}
            gameName={game.name}
            isPaid={game.is_paid}
            userProgress={game.user_progress?.[0] ?? null}
            listMemberships={game.user_list_memberships ?? []}
        />
    </div>
</Card>
