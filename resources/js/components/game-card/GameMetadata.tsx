import type {GameCardGame} from '@/hooks/useGameCard';

interface GameMetadataProps {
    game: GameCardGame;
}

export default function GameMetadata({game}: GameMetadataProps) {
    return (
        <dl className="min-h-24 space-y-1 overflow-hidden border-t border-gray-100 pt-2 text-sm dark:border-gray-700/50">
            <div className="grid grid-cols-[120px_1fr] gap-2">
                <dt className="text-gray-500 dark:text-gray-400">
                    Words (EN)
                </dt>
                <dd className="text-gray-700 dark:text-gray-200">
                    {game.english_word_count
                        ? game.english_word_count.toLocaleString()
                        : '—'}
                </dd>
            </div>
            <div className="grid grid-cols-[120px_1fr] gap-2">
                <dt className="text-gray-500 dark:text-gray-400">
                    Released
                </dt>
                <dd className="text-gray-700 dark:text-gray-200">
                    {game.initially_published_at
                        ? new Date(
                            game.initially_published_at,
                        ).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        })
                        : '—'}
                </dd>
            </div>
            <div className="grid grid-cols-[120px_1fr] gap-2">
                <dt className="text-gray-500 dark:text-gray-400">
                    Updated
                </dt>
                <dd className="text-gray-700 dark:text-gray-200">
                    {game.latest_version_published_at
                        ? new Date(
                            game.latest_version_published_at,
                        ).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        })
                        : '—'}
                </dd>
            </div>
            <div className="grid grid-cols-[120px_1fr] gap-2">
                <dt className="text-gray-500 dark:text-gray-400">
                    Rating
                </dt>
                <dd className="text-gray-700 dark:text-gray-200">
                    {typeof game.rating_score === 'number'
                        ? game.rating_score.toFixed(1)
                        : '—'}
                    {typeof game.rating_count === 'number' && (
                        <span className="ml-1 text-xs text-gray-600 dark:text-gray-300">
                            ({game.rating_count.toLocaleString()}{' '}
                            reviews)
                        </span>
                    )}
                </dd>
            </div>
        </dl>
    );
}