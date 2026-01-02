import type {GameCardGame} from '@/hooks/useGameCard';

interface GameContentBadgeProps {
    game: GameCardGame;
    nsfw?: boolean;
    showPaid?: boolean;
    showDemo?: boolean;
    showSale?: boolean;
    showDelisted?: boolean;
    onNsfwToggle?: () => void;
    onPaidToggle?: () => void;
    onDemoToggle?: () => void;
    onSaleToggle?: () => void;
    onDelistedToggle?: () => void;
    variant?: 'default' | 'overlay';
}

// Compact icon-only button for overlay variant
function OverlayBadge({
    onClick,
    isActive,
    bgColor,
    activeRingColor,
    title,
    children
}: {
    onClick?: () => void;
    isActive?: boolean;
    bgColor: string;
    activeRingColor: string;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onClick?.();
            }}
            className={`flex h-7 w-7 cursor-pointer items-center justify-center rounded ${bgColor} shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 ${
                isActive ? `ring-2 ${activeRingColor}` : ''
            }`}
            aria-label={title}
            title={title}
        >
            {children}
        </button>
    );
}

export default function GameContentBadge({game, nsfw, showPaid, showDemo, showSale, showDelisted, onNsfwToggle, onPaidToggle, onDemoToggle, onSaleToggle, onDelistedToggle, variant = 'default'}: GameContentBadgeProps) {
    const isOnSale = Boolean((game as unknown as Record<string, unknown>).is_on_sale);
    const hasBadges = game.is_nsfw || isOnSale || game.is_paid || game.has_demo || game.is_delisted;

    if (!hasBadges) return null;

    // Overlay variant - compact icon-only badges
    if (variant === 'overlay') {
        return (
            <div className="absolute bottom-3 right-3 z-20 flex items-center gap-1.5">
                {game.is_nsfw && (
                    <OverlayBadge
                        onClick={onNsfwToggle}
                        isActive={nsfw}
                        bgColor="bg-red-500/90"
                        activeRingColor="ring-white/50"
                        title="NSFW content"
                    >
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clipRule="evenodd" />
                        </svg>
                    </OverlayBadge>
                )}
                {isOnSale && (
                    <OverlayBadge
                        onClick={onSaleToggle}
                        isActive={showSale}
                        bgColor="bg-rose-500/90"
                        activeRingColor="ring-white/50"
                        title="On sale"
                    >
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clipRule="evenodd" />
                        </svg>
                    </OverlayBadge>
                )}
                {game.is_paid && (
                    <OverlayBadge
                        onClick={onPaidToggle}
                        isActive={showPaid}
                        bgColor="bg-amber-500/90"
                        activeRingColor="ring-white/50"
                        title="Paid game"
                    >
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd" />
                        </svg>
                    </OverlayBadge>
                )}
                {game.has_demo && (
                    <OverlayBadge
                        onClick={onDemoToggle}
                        isActive={showDemo}
                        bgColor="bg-sky-500/90"
                        activeRingColor="ring-white/50"
                        title="Has demo"
                    >
                        <svg className="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" />
                        </svg>
                    </OverlayBadge>
                )}
            </div>
        );
    }

    // Default variant - full badges with text
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
                    NSFW
                </button>
            )}
            {isOnSale && (
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
                    Sale
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
                    Paid
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
                    Demo
                </button>
            )}
            {game.is_delisted && (
                <button
                    type="button"
                    onClick={onDelistedToggle}
                    className={`cursor-pointer rounded-full border border-yellow-300 bg-yellow-200 px-3 py-1.5 text-xs font-bold text-yellow-800 dark:border-yellow-700/60 dark:bg-yellow-900/40 dark:text-yellow-300 ${
                        showDelisted
                            ? 'border-2 ring-1 ring-yellow-300 dark:ring-yellow-300'
                            : ''
                    }`}
                    aria-label="Filter by delisted games"
                    title="Filter by delisted games"
                >
                    Delisted
                </button>
            )}
        </>
    );
}
