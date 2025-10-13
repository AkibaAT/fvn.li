import type {GameCardGame} from '@/hooks/useGameCard';

interface GameStatusBadgeProps {
    game: GameCardGame;
    isActive?: boolean;
    onClick: (status: string) => void;
}

export default function GameStatusBadge({game, isActive = false, onClick}: GameStatusBadgeProps) {
    if (!game.status) return null;

    return (
        <button
            type="button"
            onClick={() => onClick(String(game.status))}
            className={`cursor-pointer rounded-full border px-3 py-1.5 text-xs font-bold ${
                game.status === 'Released'
                    ? 'border-emerald-300 bg-emerald-200 text-emerald-800 dark:border-emerald-700/60 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : game.status === 'In development'
                        ? 'border-blue-300 bg-blue-200 text-blue-800 dark:border-blue-700/60 dark:bg-blue-900/40 dark:text-blue-300'
                        : 'border-gray-300 bg-gray-200 text-gray-800 dark:border-gray-700/60 dark:bg-gray-900/40 dark:text-gray-300'
            } ${
                isActive
                    ? `border-2 ring-1 ${
                        game.status === 'Released'
                            ? 'ring-emerald-300 dark:ring-emerald-300'
                            : game.status === 'In development'
                                ? 'ring-blue-300 dark:ring-blue-300'
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