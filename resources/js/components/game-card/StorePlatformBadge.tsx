import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import type { StorePlatform, StorePlatformIconMeta } from '@/hooks/useStorePlatformIcons';

interface StorePlatformBadgeProps {
    platform: StorePlatform;
    iconMeta: StorePlatformIconMeta;
    isActive?: boolean;
    onClick?: (platform: StorePlatform) => void;
}

function OtherPlatformIcon({className}: {className?: string}) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
        </svg>
    );
}

export default function StorePlatformBadge({
    platform,
    iconMeta,
    isActive = false,
    onClick,
}: StorePlatformBadgeProps) {
    const handleClick = () => {
        if (onClick) {
            onClick(platform);
        }
    };

    const baseClasses = 'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-all';
    const interactiveClasses = onClick ? 'cursor-pointer' : '';

    const stateClasses = isActive
        ? 'border-2 border-current bg-opacity-20 shadow-sm'
        : 'border border-gray-200 bg-white dark:border-gray-600/50 dark:bg-gray-700/50';

    const renderIcon = () => {
        switch (platform) {
            case 'itch_io':
                return <ItchioIcon className="h-4 w-4" />;
            case 'steam':
                return <SteamIcon className="h-4 w-4" />;
            case 'other':
                return <OtherPlatformIcon className="h-4 w-4" />;
        }
    };

    return (
        <button
            onClick={handleClick}
            disabled={!onClick}
            className={`${baseClasses} ${interactiveClasses} ${stateClasses} ${iconMeta.color}`}
            title={iconMeta.title}
            aria-label={`${iconMeta.title} store`}
            aria-pressed={isActive}
        >
            {renderIcon()}
            <span className="hidden sm:inline">{iconMeta.label}</span>
        </button>
    );
}

