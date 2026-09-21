type PlatformFlags = {
    windows: boolean;
    linux: boolean;
    mac: boolean;
    android: boolean;
    web: boolean;
};

type VersionWithPlatforms = Partial<Record<'is_windows' | 'is_linux' | 'is_mac' | 'is_android' | 'is_web', boolean>>;

type VersionWithLanguageStats = {
    languageStats?: Array<{
        words?: number;
        language: {
            id: number;
            iso_code: string;
        };
    }>;
};

export function getGamePlatforms(platforms: PlatformFlags, latestVersion?: VersionWithPlatforms): PlatformFlags {
    if (platforms && Object.values(platforms).some(Boolean)) return platforms;

    if (latestVersion) {
        return {
            windows: latestVersion.is_windows ?? false,
            linux: latestVersion.is_linux ?? false,
            mac: latestVersion.is_mac ?? false,
            android: latestVersion.is_android ?? false,
            web: latestVersion.is_web ?? false,
        };
    }

    return platforms;
}

export function getVersionWordCount(version: VersionWithLanguageStats): string | null {
    const words = version.languageStats?.find((stats) => String(stats.language.iso_code) === 'eng' || String(stats.language.id) === 'eng')?.words;
    return words ? words.toLocaleString() : null;
}

export function gamesFilterUrl(filters: Record<string, string | number | boolean | Array<string | number>>): string {
    return route('games.index', { ...filters, noDefaults: true });
}

export function shouldCollapseReview(reviewHtml?: string): boolean {
    return (reviewHtml?.length || 0) > 900;
}

export function parseCriteriaRankings(criteriaRankings: unknown): Record<string, { rank?: string; score?: string }> {
    if (!criteriaRankings) return {};

    if (typeof criteriaRankings === 'string') {
        try {
            return JSON.parse(criteriaRankings) as Record<string, { rank?: string; score?: string }>;
        } catch {
            return {};
        }
    }

    return criteriaRankings as Record<string, { rank?: string; score?: string }>;
}
