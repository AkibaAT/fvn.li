import { getCsrfToken } from './client';
import http from '@/utils/http';

export interface GameStats {
    itchioUsername?: string;
    ownedGamesCount: number;
    gamesWithLinksCount: number;
}

export interface GameVersion {
    id: number;
    version: string;
    published_at: string;
}

export interface Language {
    id: string;
    name: string;
    flag: string;
}

export interface CharacterStats {
    from: number;
    to: number;
    diff: number;
}

export interface FileStats {
    count: number;
    size: number;
}

export interface FileCategory {
    category: string;
    from: FileStats;
    to: FileStats;
    diff: FileStats;
    fileTypes: {
        [extension: string]: {
            from: FileStats;
            to: FileStats;
            diff: FileStats;
        };
    };
}

export interface VersionComparisonData {
    fromVersion: GameVersion;
    toVersion: GameVersion;
    characters: string[];
    languages: Language[];
    characterDiffs: {
        [character: string]: {
            [languageId: string]: CharacterStats;
        };
    };
    languageTotals: {
        from: { [languageId: string]: number };
        to: { [languageId: string]: number };
        diff: { [languageId: string]: number };
    };
    fileCategories: FileCategory[];
}

export async function fetchGameStats(): Promise<GameStats> {
    const response = await fetch(route('browser-api.dashboard.game-stats'));
    const data = await response.json();
    if (!data.success) throw new Error('Failed to fetch game stats');
    return data.stats;
}

interface VersionComparisonParams {
    gameId: number;
    fromVersionId?: number;
    toVersionId?: number;
}

export async function fetchVersionComparison({ gameId, fromVersionId, toVersionId }: VersionComparisonParams): Promise<VersionComparisonData> {
    const response = await fetch(
        route('api.games.compare-versions', {
            game: gameId,
            fromVersionId,
            toVersionId,
        }),
        {
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        },
    );

    if (!response.ok) throw new Error('Failed to fetch comparison data');
    return response.json();
}

interface ToggleNotificationsParams {
    gameId: number;
    receiveUpdates: boolean;
}

export async function toggleGameNotifications({
    gameId,
    receiveUpdates,
}: ToggleNotificationsParams): Promise<{ success: boolean; receive_updates: boolean }> {
    const response = await http.patch(`/browser-api/user-progress/${gameId}/toggle-updates`, {
        receive_updates: receiveUpdates,
    });
    return response.data;
}
