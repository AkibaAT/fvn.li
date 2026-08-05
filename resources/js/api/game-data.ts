import type { RouteGraphData } from '@/types/route-graph';
import http from '@/utils/http';

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
    const { data } = await http.get<VersionComparisonData>(
        route('api.games.compare-versions', {
            game: gameId,
            fromVersionId,
            toVersionId,
        }),
    );

    return data;
}

interface RouteGraphParams {
    gameSlug: string;
    versionId: number;
    includeUnreachable?: boolean;
}

export async function fetchRouteGraph({ gameSlug, versionId, includeUnreachable }: RouteGraphParams): Promise<RouteGraphData> {
    const { data } = await http.get<RouteGraphData>(
        route('browser-api.games.version.route-graph', {
            game: gameSlug,
            version: versionId,
        }),
        {
            params: includeUnreachable ? { include_unreachable: 1 } : undefined,
        },
    );

    return data;
}

export async function parseSaveFile(gameSlug: string, versionId: number, file: File): Promise<string[]> {
    const formData = new FormData();
    formData.append('file', file);

    const { data } = await http.post<{ seen_labels: string[] }>(
        route('browser-api.games.version.parse-save', {
            game: gameSlug,
            version: versionId,
        }),
        formData,
    );

    return data.seen_labels;
}
