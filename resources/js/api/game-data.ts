import { getCsrfToken } from '@/utils/http';

interface GameVersion {
    id: number;
    version: string;
    published_at: string;
}

interface Language {
    id: string;
    name: string;
    flag: string;
}

interface CharacterStats {
    from: number;
    to: number;
    diff: number;
}

interface FileStats {
    count: number;
    size: number;
}

interface FileCategory {
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
