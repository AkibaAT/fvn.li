import {Link} from '@inertiajs/react';
import type {GameCardGame} from '@/hooks/useGameCard';

interface GameTitleProps {
    game: GameCardGame;
    authorsInlineHtml: string;
}

export default function GameTitle({game, authorsInlineHtml}: GameTitleProps) {
    const gameName = game.effective_name;

    return (
        <div className="space-y-1 overflow-hidden">
            <h2 className="line-clamp-2 min-h-[3.5rem] break-words text-lg font-semibold text-gray-900 dark:text-white">
                <Link
                    href={route('games.show', game.slug)}
                    className="transition-colors hover:text-blue-600 dark:hover:text-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    aria-label={`View details for ${gameName}`}
                >
                    {gameName}
                </Link>
            </h2>

            {game.authors && (
                <div
                    className="-mt-1 line-clamp-1 min-h-5 text-sm text-gray-600 dark:text-gray-400"
                    dangerouslySetInnerHTML={{
                        __html: authorsInlineHtml,
                    }}
                    aria-label={`Authors: ${game.authors}`}
                />
            )}
        </div>
    );
}
