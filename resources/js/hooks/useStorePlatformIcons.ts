/** Where a game is hosted or sold, independent of the OS it runs on. */
export type StorePlatform = 'itch_io' | 'steam' | 'other';

const STORE_PLATFORM_TITLES: Record<StorePlatform, string> = {
    itch_io: 'itch.io',
    steam: 'Steam',
    other: 'Other Platform',
};

export function useStorePlatformIcons() {
    const getStorePlatformIcon = (platform: StorePlatform) => ({ title: STORE_PLATFORM_TITLES[platform] });

    const getStorePlatformFromString = (platform: string): StorePlatform =>
        platform === 'itch_io' || platform === 'steam' || platform === 'other' ? platform : 'other';

    return {
        getStorePlatformIcon,
        getStorePlatformFromString,
    };
}
