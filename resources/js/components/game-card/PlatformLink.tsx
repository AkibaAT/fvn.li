import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import { route } from 'ziggy-js';

interface PlatformLinkProps {
    url: string;
    platform?: 'itch_io' | 'steam' | 'other';
    gameId: number;
    className?: string;
}

function OtherPlatformIcon({ className }: { className?: string }) {
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

export default function PlatformLink({
    url,
    platform = 'other',
    gameId,
    className = '',
}: PlatformLinkProps) {
    const getPlatformLabel = () => {
        switch (platform) {
            case 'itch_io':
                return 'Visit on itch.io';
            case 'steam':
                return 'Visit on Steam';
            case 'other':
                return 'Visit Game Page';
            default:
                return 'Visit Game Page';
        }
    };

    const getPlatformIcon = () => {
        switch (platform) {
            case 'itch_io':
                return <ItchioIcon className="h-4 w-4" />;
            case 'steam':
                return <SteamIcon className="h-4 w-4" />;
            case 'other':
                return <OtherPlatformIcon className="h-4 w-4" />;
        }
    };

    const getPlatformColor = () => {
        switch (platform) {
            case 'itch_io':
                return 'text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300';
            case 'steam':
                return 'text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300';
            case 'other':
                return 'text-gray-600 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300';
        }
    };

    const defaultClassName = `inline-flex items-center gap-2 font-medium transition-colors ${getPlatformColor()}`;

    // Generate the tracking URL - fallback to direct URL if route helper fails (e.g., during SSR)
    let trackingUrl: string;
    try {
        trackingUrl = route('track.external-project', {
            game_id: gameId,
            url: url,
        });
    } catch (error) {
        // Fallback to direct URL if route is not available (SSR context)
        trackingUrl = url;
    }

    return (
        <a
            href={trackingUrl}
            target="_blank"
            rel="noopener noreferrer"
            className={className || defaultClassName}
            title={getPlatformLabel()}
            aria-label={`${getPlatformLabel()} - opens in new window`}
        >
            {getPlatformIcon()}
            <span>{getPlatformLabel()}</span>
        </a>
    );
}

