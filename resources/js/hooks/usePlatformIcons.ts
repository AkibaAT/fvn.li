import AndroidIcon from '@/components/icons/Android.svelte';
import AppleIcon from '@/components/icons/Apple.svelte';
import LinuxIcon from '@/components/icons/Linux.svelte';
import WebIcon from '@/components/icons/Web.svelte';
import WindowsIcon from '@/components/icons/Windows.svelte';
import type { Component } from 'svelte';

export type GameCardPlatform = 'windows' | 'linux' | 'mac' | 'android' | 'web';

export interface PlatformIconMeta {
    icon: Component;
    color: string;
    title: string;
}

export function usePlatformIcons() {
    const platformIcons: Record<GameCardPlatform, PlatformIconMeta> = {
        windows: {
            icon: WindowsIcon,
            color: 'text-platform-windows',
            title: 'Windows',
        },
        linux: {
            icon: LinuxIcon,
            color: 'text-platform-linux',
            title: 'Linux',
        },
        mac: {
            icon: AppleIcon,
            color: 'text-platform-mac',
            title: 'Mac',
        },
        android: {
            icon: AndroidIcon,
            color: 'text-platform-android',
            title: 'Android',
        },
        web: {
            icon: WebIcon,
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
