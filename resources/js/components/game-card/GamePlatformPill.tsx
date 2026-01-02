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
                    ? 'border-teal-700 bg-teal-600 text-white dark:border-teal-500 dark:bg-teal-700'
                    : 'border-gray-200 bg-white text-gray-700 hover:bg-teal-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-teal-900/20'
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