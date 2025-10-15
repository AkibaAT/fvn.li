import type {GameCardGame} from '@/hooks/useGameCard';

interface GameContentBadgeProps {
    game: GameCardGame;
    nsfw?: boolean;
    showPaid?: boolean;
    showDemo?: boolean;
    showSale?: boolean;
    onNsfwToggle?: () => void;
    onPaidToggle?: () => void;
    onDemoToggle?: () => void;
    onSaleToggle?: () => void;
}

export default function GameContentBadge({game, nsfw, showPaid, showDemo, showSale, onNsfwToggle, onPaidToggle, onDemoToggle, onSaleToggle}: GameContentBadgeProps) {
    return (
        <>
            {game.is_nsfw && (
                <button
                    type="button"
                    onClick={onNsfwToggle}
                    className={`cursor-pointer rounded-full border border-red-300 bg-red-200 px-3 py-1.5 text-xs font-bold text-red-800 dark:border-red-700/60 dark:bg-red-900/40 dark:text-red-300 ${
                        nsfw
                            ? 'border-2 ring-1 ring-red-300 dark:ring-red-300'
                            : ''
                    }`}
                    aria-label="Filter by NSFW content"
                    title="Filter by NSFW content"
                >
                    🔞 NSFW
                </button>
            )}
            {Boolean(
                (game as unknown as Record<string, unknown>).is_on_sale,
            ) && (
                <button
                    type="button"
                    onClick={onSaleToggle}
                    className={`cursor-pointer rounded-full border border-rose-300 bg-rose-200 px-3 py-1.5 text-xs font-bold text-rose-800 dark:border-rose-700/60 dark:bg-rose-900/40 dark:text-rose-300 ${
                        showSale
                            ? 'border-2 ring-1 ring-rose-300 dark:ring-rose-300'
                            : ''
                    }`}
                    aria-label="Filter by games on sale"
                    title="Filter by games on sale"
                >
                    🔖 Sale
                </button>
            )}
            {game.is_paid && (
                <button
                    type="button"
                    onClick={onPaidToggle}
                    className={`cursor-pointer rounded-full border border-amber-300 bg-amber-200 px-3 py-1.5 text-xs font-bold text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/40 dark:text-amber-300 ${
                        showPaid
                            ? 'border-2 ring-1 ring-amber-300 dark:ring-amber-300'
                            : ''
                    }`}
                    aria-label="Filter by paid games"
                    title="Filter by paid games"
                >
                    💰 Paid
                </button>
            )}
            {game.has_demo && (
                <button
                    type="button"
                    onClick={onDemoToggle}
                    className={`cursor-pointer rounded-full border border-sky-300 bg-sky-200 px-3 py-1.5 text-xs font-bold text-sky-800 dark:border-sky-700/60 dark:bg-sky-900/40 dark:text-sky-300 ${
                        showDemo
                            ? 'border-2 ring-1 ring-sky-300 dark:ring-sky-300'
                            : ''
                    }`}
                    aria-label="Filter by has demo"
                    title="Filter by has demo"
                >
                    🎮 Demo
                </button>
            )}
        </>
    );
}
