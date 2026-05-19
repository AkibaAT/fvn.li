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

export function getLanguageFlag(flagCode: string): string {
    return `https://flagicons.lipis.dev/flags/1x1/${flagCode}.svg`;
}

export function getVersionWordCount(version: VersionWithLanguageStats): string {
    return (
        version.languageStats
            ?.find((stats) => String(stats.language.iso_code) === 'eng' || String(stats.language.id) === 'eng')
            ?.words?.toLocaleString() || '-'
    );
}

export function shouldCollapseReview(reviewHtml?: string): boolean {
    return (reviewHtml?.length || 0) > 900;
}

export function getPublicListColors(type: string): {
    border: string;
    bg: string;
    text: string;
    darkBg: string;
    darkText: string;
} {
    return (
        {
            reading: {
                border: 'border-blue-500',
                bg: 'bg-blue-100',
                text: 'text-blue-800',
                darkBg: 'dark:bg-blue-900/20',
                darkText: 'dark:text-blue-400',
            },
            completed: {
                border: 'border-green-500',
                bg: 'bg-green-100',
                text: 'text-green-800',
                darkBg: 'dark:bg-green-900/20',
                darkText: 'dark:text-green-400',
            },
            plan_to_read: {
                border: 'border-yellow-500',
                bg: 'bg-yellow-100',
                text: 'text-yellow-800',
                darkBg: 'dark:bg-yellow-900/20',
                darkText: 'dark:text-yellow-400',
            },
            on_hold: {
                border: 'border-orange-500',
                bg: 'bg-orange-100',
                text: 'text-orange-800',
                darkBg: 'dark:bg-orange-900/20',
                darkText: 'dark:text-orange-400',
            },
            dropped: {
                border: 'border-red-500',
                bg: 'bg-red-100',
                text: 'text-red-800',
                darkBg: 'dark:bg-red-900/20',
                darkText: 'dark:text-red-400',
            },
        }[type] || {
            border: 'border-gray-500',
            bg: 'bg-gray-100',
            text: 'text-gray-800',
            darkBg: 'dark:bg-gray-900/20',
            darkText: 'dark:text-gray-400',
        }
    );
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

export function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}
