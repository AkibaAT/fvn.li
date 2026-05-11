/**
 * Hook for store platform icons (itch.io, Steam, other)
 * These represent where the game is hosted/sold, not what OS it runs on
 */

export type StorePlatform = 'itch_io' | 'steam' | 'other';

export interface StorePlatformIconMeta {
    color: string;
    title: string;
    label: string;
}

export function useStorePlatformIcons() {
    const storePlatformIcons: Record<StorePlatform, StorePlatformIconMeta> = {
        itch_io: {
            color: 'text-orange-600 dark:text-orange-400',
            title: 'itch.io',
            label: 'itch.io',
        },
        steam: {
            color: 'text-blue-600 dark:text-blue-400',
            title: 'Steam',
            label: 'Steam',
        },
        other: {
            color: 'text-gray-600 dark:text-gray-400',
            title: 'Other Platform',
            label: 'Other',
        },
    };

    const getStorePlatformIcon = (platform: StorePlatform): StorePlatformIconMeta => {
        return storePlatformIcons[platform];
    };

    const getAllStorePlatforms = (): StorePlatform[] => {
        return Object.keys(storePlatformIcons) as StorePlatform[];
    };

    const getStorePlatformFromString = (platform: string): StorePlatform => {
        if (platform === 'itch_io' || platform === 'steam' || platform === 'other') {
            return platform as StorePlatform;
        }
        return 'other';
    };

    return {
        storePlatformIcons,
        getStorePlatformIcon,
        getAllStorePlatforms,
        getStorePlatformFromString,
    };
}
