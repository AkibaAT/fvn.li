export type GameCardPlatform = 'windows' | 'linux' | 'mac' | 'android' | 'web';

export interface PlatformIconMeta {
    icon: string;
    color: string;
    title: string;
}

export function usePlatformIcons() {
    const platformIcons: Record<GameCardPlatform, PlatformIconMeta> = {
        windows: {
            icon: 'icon-windows',
            color: 'text-platform-windows',
            title: 'Windows',
        },
        linux: {
            icon: 'icon-linux',
            color: 'text-platform-linux',
            title: 'Linux',
        },
        mac: {
            icon: 'icon-apple',
            color: 'text-platform-mac',
            title: 'macOS',
        },
        android: {
            icon: 'icon-android',
            color: 'text-platform-android',
            title: 'Android',
        },
        web: {
            icon: 'icon-web',
            color: 'text-platform-web',
            title: 'Web',
        },
    };

    const getPlatformIcon = (platform: GameCardPlatform): PlatformIconMeta => {
        return platformIcons[platform];
    };

    const getAllPlatforms = (): GameCardPlatform[] => {
        return Object.keys(platformIcons) as GameCardPlatform[];
    };

    const getSupportedPlatforms = (game: Record<string, unknown>): GameCardPlatform[] => {
        const platforms = getAllPlatforms();
        const supported: GameCardPlatform[] = [];
        
        for (const platform of platforms) {
            const platformKey = `is_${platform}` as keyof typeof game;
            if (game[platformKey]) {
                supported.push(platform);
            }
        }
        
        return supported;
    };

    return {
        platformIcons,
        getPlatformIcon,
        getAllPlatforms,
        getSupportedPlatforms,
    };
}