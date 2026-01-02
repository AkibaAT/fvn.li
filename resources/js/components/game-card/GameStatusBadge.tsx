import type {GameCardGame} from '@/hooks/useGameCard';

interface GameStatusBadgeProps {
    game: GameCardGame;
    isActive?: boolean;
    onClick: (status: string) => void;
    variant?: 'default' | 'overlay';
}

export default function GameStatusBadge({game, isActive = false, onClick, variant = 'default'}: GameStatusBadgeProps) {
    if (!game.status) return null;

    // Overlay variant for image positioning
    if (variant === 'overlay') {
        return (
            <button
                type="button"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onClick(String(game.status));
                }}
                className={`absolute right-3 top-3 z-20 cursor-pointer rounded-full px-2.5 py-1 text-xs font-bold shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-105 ${
                    game.status === 'Released'
                        ? 'bg-emerald-500/90 text-white'
                        : game.status === 'In development'
                            ? 'bg-sky-500/90 text-white'
                            : 'bg-gray-500/90 text-white'
                } ${
                    isActive
                        ? 'ring-2 ring-white/50'
                        : ''
                }`}
                aria-label={`Filter by status: ${game.status}`}
                title={`Filter by status: ${game.status}`}
            >
                {game.status === 'In development' ? 'In Dev' : game.status}
            </button>
        );
    }

    // Default variant for content area
    return (
        <button
            type="button"
            onClick={() => onClick(String(game.status))}
            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                game.status === 'Released'
                    ? 'border-emerald-300 bg-emerald-200 text-emerald-800 dark:border-emerald-700/60 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : game.status === 'In development'
                        ? 'border-sky-300 bg-sky-200 text-sky-800 dark:border-sky-700/60 dark:bg-sky-900/40 dark:text-sky-300'
                        : 'border-gray-300 bg-gray-200 text-gray-800 dark:border-gray-700/60 dark:bg-gray-900/40 dark:text-gray-300'
            } ${
                isActive
                    ? `border-2 ring-1 ${
                        game.status === 'Released'
                            ? 'ring-emerald-300 dark:ring-emerald-300'
                            : game.status === 'In development'
                                ? 'ring-sky-300 dark:ring-sky-300'
                                : 'ring-gray-300 dark:ring-gray-500'
                    }`
                    : ''
            }`}
            aria-label={`Filter by status: ${game.status}`}
            title={`Filter by status: ${game.status}`}
        >
            {game.status}
        </button>
    );
}