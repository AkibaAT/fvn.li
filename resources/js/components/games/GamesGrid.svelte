<script lang="ts">
    import GameCard from '@/components/GameCard.svelte';
    import type { CurrentFilters } from '@/types';

    interface Game {
        id: number;
        name: string;
        effective_name: string;
        slug: string;
        description?: string;
        thumb_url?: string;
        optimized_thumbnails?: {
            default?: { path: string; width: number; height: number };
        };
        rating_score?: number;
        rating_count?: number;
        status: string;
        game_engine?: string;
        is_nsfw: boolean;
        is_paid: boolean;
        has_demo: boolean;
        is_delisted: boolean;
        authors?: string;
        tags?: Array<{ id: number; name: string; slug: string }>;
        gameJams?: Array<{ id: number; name: string }>;
        supported_languages?: Array<{
            iso_code: string;
            ref_name: string;
            flag_code: string;
        }>;
        is_windows?: boolean;
        is_linux?: boolean;
        is_mac?: boolean;
        is_android?: boolean;
        is_web?: boolean;
        english_word_count?: number;
        trending_score?: number;
        initially_published_at?: string;
        latest_version_published_at?: string;
        rating?: number;
        created_at: string;
        updated_at: string;
        [key: string]: unknown;
    }

    interface Props {
        games: Game[];
        currentFilters: CurrentFilters;
        ignoredGameIds?: number[];
        onPlatformClick: (platform: string) => void;
        onLanguageClick: (language: string) => void;
        onTagClick: (tag: string) => void;
        onStatusClick: (status: string) => void;
        onStorePlatformClick: (platform: string) => void;
        onNsfwToggle: () => void;
        onPaidToggle: () => void;
        onDemoToggle: () => void;
        onSaleToggle: () => void;
        onDelistedToggle: () => void;
        updateFilters: (filters: Partial<CurrentFilters>) => void;
        onIgnoreToggle?: (gameId: number, isIgnored: boolean, ignoredGameIds: number[]) => void;
    }

    let {
        games,
        currentFilters,
        ignoredGameIds,
        onPlatformClick,
        onLanguageClick,
        onTagClick,
        onStatusClick,
        onStorePlatformClick,
        onNsfwToggle,
        onPaidToggle,
        onDemoToggle,
        onSaleToggle,
        onDelistedToggle,
        onIgnoreToggle,
    }: Props = $props();
</script>

{#if games.length === 0}
    <div class="py-12 text-center">
        <div class="text-lg text-gray-400">No games found</div>
        <p class="mt-2 text-gray-500">Try adjusting your search criteria or check back later.</p>
    </div>
{:else}
    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {#each games as game (game.id)}
            <GameCard
                {game}
                selectedPlatforms={currentFilters.selectedPlatforms || []}
                selectedLanguages={currentFilters.selectedLanguages || []}
                selectedTags={currentFilters.selectedTags || []}
                selectedStatuses={currentFilters.selectedStatuses || []}
                selectedStorePlatforms={currentFilters.selectedStorePlatforms || []}
                nsfw={currentFilters.nsfw || false}
                showPaid={currentFilters.showPaid || false}
                showDemo={currentFilters.showDemo || false}
                showSale={currentFilters.showSale || false}
                delisted={currentFilters.delisted || false}
                {ignoredGameIds}
                {onPlatformClick}
                {onLanguageClick}
                {onTagClick}
                {onStatusClick}
                {onStorePlatformClick}
                {onNsfwToggle}
                {onPaidToggle}
                {onDemoToggle}
                {onSaleToggle}
                {onDelistedToggle}
                {onIgnoreToggle}
            />
        {/each}
    </div>
{/if}
