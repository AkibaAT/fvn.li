<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import GameListRow from '@/components/games/GameListRow.svelte';
    import ListGroup from '@/components/lists/ListGroup.svelte';
    import type { GameCardGame, GameCardProps } from '@/hooks/useGameCard.svelte';
    import type { CurrentFilters } from '@/types';
    import { GAME_CARD_GRID_CLASS, type ViewMode } from '@/utils/view-mode';

    type FilterHandlers = Pick<
        GameCardProps,
        | 'onPlatformClick'
        | 'onLanguageClick'
        | 'onTagClick'
        | 'onStatusClick'
        | 'onStorePlatformClick'
        | 'onNsfwToggle'
        | 'onPaidToggle'
        | 'onDemoToggle'
        | 'onSaleToggle'
    >;

    interface Props extends FilterHandlers {
        games: GameCardGame[];
        /** Active catalogue filters; omitted where cards link out to the catalogue instead. */
        currentFilters?: CurrentFilters;
        ignoredGameIds?: number[];
        viewMode?: ViewMode;
    }

    let { games, currentFilters = {}, ignoredGameIds, viewMode = 'grid', ...handlers }: Props = $props();

    function cardProps(game: GameCardGame): GameCardProps {
        return {
            game,
            selectedTags: currentFilters.selectedTags || [],
            selectedPlatforms: currentFilters.selectedPlatforms || [],
            selectedLanguages: currentFilters.selectedLanguages || [],
            selectedStatuses: currentFilters.selectedStatuses || [],
            selectedStorePlatforms: currentFilters.selectedStorePlatforms || [],
            nsfw: currentFilters.nsfw || false,
            showPaid: currentFilters.showPaid || false,
            showDemo: currentFilters.showDemo || false,
            showSale: currentFilters.showSale || false,
            ignoredGameIds,
            ...handlers,
        };
    }
</script>

{#if games.length === 0}
    <div class="py-12 text-center">
        <div class="text-md text-fg-muted">No games found</div>
        <p class="mt-2 text-ui text-fg-faint">Try adjusting your search criteria or check back later.</p>
    </div>
{:else if viewMode === 'list'}
    <ListGroup class="px-3.5 max-sm:px-2.5">
        {#each games as game (game.id)}
            <GameListRow {...cardProps(game)} />
        {/each}
    </ListGroup>
{:else}
    <div class={GAME_CARD_GRID_CLASS}>
        {#each games as game (game.id)}
            <GameCard {...cardProps(game)} />
        {/each}
    </div>
{/if}
