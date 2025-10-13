import type {GameCardPlatform, PlatformIconMeta} from '@/hooks/usePlatformIcons';

interface GamePlatformPillProps {
    platform: GameCardPlatform;
    isActive?: boolean;
    iconMeta: PlatformIconMeta;
    onClick: (platform: GameCardPlatform) => void;
}

export default function GamePlatformPill({platform, isActive = false, iconMeta, onClick}: GamePlatformPillProps) {
    return (
        <button
            key={platform}
            onClick={() => onClick(platform)}
            className={`inline-flex cursor-pointer items-center rounded border px-2 py-1 text-xs transition-colors ${
                isActive
                    ? 'border-blue-700 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-700'
                    : 'border-gray-200 bg-white text-gray-700 hover:bg-blue-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-blue-900/20'
            }`}
            title={iconMeta.title}
            aria-label={iconMeta.title}
            aria-pressed={isActive}
        >
            <i
                className={`${iconMeta.icon} ${iconMeta.color}`}
                aria-hidden="true"
            />
        </button>
    );
}