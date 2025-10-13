import React from 'react';
import GameCard from '@/components/game-card';
import type {CurrentFilters} from '@/types';

interface Game {
    id: number;
    name: string;
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
    is_suspended: boolean;
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

interface GamesGridProps {
    games: Game[];
    currentFilters: CurrentFilters;
    onPlatformClick: (platform: string) => void;
    onLanguageClick: (language: string) => void;
    onTagClick: (tag: string) => void;
    onStatusClick: (status: string) => void;
    onNsfwToggle: () => void;
    onPaidToggle: () => void;
    onDemoToggle: () => void;
    updateFilters: (filters: Partial<CurrentFilters>) => void;
}

export default function GamesGrid({
    games,
    currentFilters,
    onPlatformClick,
    onLanguageClick,
    onTagClick,
    onStatusClick,
    onNsfwToggle,
    onPaidToggle,
    onDemoToggle,
}: GamesGridProps) {
    if (games.length === 0) {
        return (
            <div className="py-12 text-center">
                <div className="text-lg text-gray-400">
                    No games found
                </div>
                <p className="mt-2 text-gray-500">
                    Try adjusting your search criteria or check back later.
                </p>
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {games.map((game) => (
                <GameCard
                    key={game.id}
                    game={game}
                    selectedPlatforms={currentFilters.selectedPlatforms || []}
                    selectedLanguages={currentFilters.selectedLanguages || []}
                    selectedTags={currentFilters.selectedTags || []}
                    selectedStatuses={currentFilters.selectedStatuses || []}
                    nsfw={currentFilters.nsfw || false}
                    showPaid={currentFilters.showPaid || false}
                    showDemo={currentFilters.showDemo || false}
                    onPlatformClick={onPlatformClick}
                    onLanguageClick={onLanguageClick}
                    onTagClick={onTagClick}
                    onStatusClick={onStatusClick}
                    onNsfwToggle={onNsfwToggle}
                    onPaidToggle={onPaidToggle}
                    onDemoToggle={onDemoToggle}
                />
            ))}
        </div>
    );
}