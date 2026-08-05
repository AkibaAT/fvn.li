<script lang="ts">
    import NoSymbolIcon from '@/components/icons/NoSymbol.svelte';
    import NoSymbolSolidIcon from '@/components/icons/NoSymbolSolid.svelte';
    import { untrack } from 'svelte';
    import GameCardUserSection from './GameCardUserSection.svelte';
    import GameImage from './game-card/GameImage.svelte';
    import GameTitle from './game-card/GameTitle.svelte';
    import GameMetadata from './game-card/GameMetadata.svelte';
    import GamePlatformPill from './game-card/GamePlatformPill.svelte';
    import { toggleIgnoredGame } from '@/api';
    import GameLanguageSection from './game-card/GameLanguageSection.svelte';
    import GameTagSection from './game-card/GameTagSection.svelte';
    import GameStatusBadge from './game-card/GameStatusBadge.svelte';
    import GameContentBadge from './game-card/GameContentBadge.svelte';
    import StorePlatformBadge from './game-card/StorePlatformBadge.svelte';
    import { Button, Card } from '@/components/ui';
    import { useGameCard, type GameCardProps } from '@/hooks/useGameCard.svelte';
    import { usePlatformIcons } from '@/hooks/usePlatformIcons';
    import { useStorePlatformIcons } from '@/hooks/useStorePlatformIcons';
    import type { Game } from '@/types';
    import { page } from '@inertiajs/svelte';

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
        handleDelistedToggle,
        orderedTags,
        tagsExpanded,
        setTagsExpanded,
        languagesExpanded,
        setLanguagesExpanded,
    } = untrack(() => useGameCard(props));

    const {
        game,
        selectedTags,
        selectedPlatforms,
        selectedLanguages,
        selectedStatuses,
        nsfw,
        showPaid,
        showDemo,
        showSale,
        delisted,
        ignoredGameIds,
        onIgnoreToggle,
        fixedHeight = false,
    } = $derived(props);
    const { getSupportedPlatforms, getPlatformIcon } = usePlatformIcons();
    const { getStorePlatformIcon, getStorePlatformFromString } = useStorePlatformIcons();
    const auth = $derived((page as any).props?.auth);

    let isIgnored = $derived(untrack(() => ignoredGameIds?.includes(game.id) || false));
    let isTogglingIgnore = $state(false);

    const supportedPlatforms = $derived(getSupportedPlatforms(game));
    const storePlatform = $derived(game.platform ? getStorePlatformFromString(game.platform) : 'itch_io');

    const handleIgnoreToggle = async (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!auth?.user || isTogglingIgnore) return;

        isTogglingIgnore = true;
        try {
            const result = await toggleIgnoredGame(game.id);
            isIgnored = result.isIgnored;
            if (onIgnoreToggle) {
                onIgnoreToggle(game.id, result.isIgnored, result.ignoredGameIds);
            }
        } catch (error) {
            console.error('Failed to toggle ignore status:', error);
        } finally {
            isTogglingIgnore = false;
        }
    };

    const showFooterBadges = $derived(
        game.is_nsfw || Boolean((game as Game).is_on_sale) || game.is_paid || game.has_demo || game.is_delisted || Boolean(game.status),
    );
</script>

<Card
    variant="glass"
    padding="none"
    hover
    class="group relative flex {fixedHeight
        ? 'h-[43rem]'
        : 'h-full'} flex-col overflow-hidden transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl"
>
    {#if auth?.user}
        <Button
            onclick={handleIgnoreToggle}
            disabled={isTogglingIgnore}
            variant="ghost"
            tone={isIgnored ? 'danger' : 'neutral'}
            size="icon-md"
            class="absolute top-2 right-2 z-10 rounded-full bg-white/90 p-2 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-white dark:bg-gray-800/90 dark:hover:bg-gray-800"
            title={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
            aria-label={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
        >
            {#if isIgnored}
                <NoSymbolSolidIcon class="h-5 w-5 text-red-600 dark:text-red-400" />
            {:else}
                <NoSymbolIcon class="h-5 w-5 text-gray-600 dark:text-gray-400" />
            {/if}
        </Button>
    {/if}

    <GameImage {game} {thumbnailUrl} aspectClass="aspect-[315/250]" />

    <div class="flex flex-1 flex-col p-4">
        <div class="grid flex-1 auto-rows-min gap-y-3">
            <GameTitle {game} {authorsInlineHtml} />

            <GameMetadata {game} />

            {#if storePlatform}
                <div class="flex items-center gap-2">
                    <StorePlatformBadge
                        platform={storePlatform}
                        iconMeta={getStorePlatformIcon(storePlatform)}
                        isActive={props.selectedStorePlatforms?.includes(storePlatform)}
                        onclick={handleStorePlatform}
                    />
                </div>
            {/if}

            <div class="h-8 border-t border-gray-100 pt-2 dark:border-gray-700/50">
                <div class="flex h-6 flex-nowrap items-center gap-1 overflow-hidden">
                    {#each supportedPlatforms as platform (platform)}
                        {@const isActive = selectedPlatforms?.includes(platform)}
                        <GamePlatformPill {platform} {isActive} iconMeta={getPlatformIcon(platform)} onclick={handlePlatform} />
                    {/each}
                </div>
            </div>

            <GameLanguageSection
                languages={game.supported_languages}
                {selectedLanguages}
                {languagesExpanded}
                {setLanguagesExpanded}
                {handleLanguage}
            />

            <GameTagSection {orderedTags} {selectedTags} {tagsExpanded} {setTagsExpanded} {handleTag} />

            {#if showFooterBadges}
                <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700/50">
                    <GameStatusBadge {game} isActive={selectedStatuses?.includes(String(game.status))} onclick={handleStatus} />
                    <GameContentBadge
                        {game}
                        {nsfw}
                        {showPaid}
                        {showDemo}
                        {showSale}
                        showDelisted={delisted}
                        onNsfwToggle={handleNsfwToggle}
                        onPaidToggle={handlePaidToggle}
                        onDemoToggle={handleDemoToggle}
                        onSaleToggle={handleSaleToggle}
                        onDelistedToggle={handleDelistedToggle}
                    />
                </div>
            {/if}

            {#if !fixedHeight}
                <GameCardUserSection gameId={game.id} gameName={game.name} isPaid={game.is_paid} userProgress={game.user_progress?.[0] ?? null} />
            {/if}
        </div>
    </div>
</Card>
