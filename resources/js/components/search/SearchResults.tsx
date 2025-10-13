import React from 'react';
import { Link } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { SearchResult, SearchPagination } from '@/hooks/useEnhancedSearch';

interface SearchResultsProps {
    type: 'games' | 'dialogue' | 'global';
    results?: SearchResult[];
    globalResults?: {
        games: SearchResult[];
        dialogue: SearchResult[];
        total_games: number;
        total_dialogue: number;
    };
    pagination?: SearchPagination;
    loading?: boolean;
    query?: string;
    onPageChange?: (page: number) => void;
    className?: string;
}

export default function SearchResults({
    type,
    results = [],
    globalResults,
    pagination,
    loading = false,
    query = '',
    onPageChange,
    className = '',
}: SearchResultsProps) {
    if (loading) {
        return (
            <div className={`space-y-4 ${className}`}>
                {[...Array(3)].map((_, i) => (
                    <div key={i} className="animate-pulse">
                        <div className="h-4 bg-gray-200 rounded w-3/4 mb-2 dark:bg-gray-700"></div>
                        <div className="h-3 bg-gray-200 rounded w-1/2 dark:bg-gray-700"></div>
                    </div>
                ))}
            </div>
        );
    }

    if (type === 'global' && globalResults) {
        return (
            <div className={`space-y-6 ${className}`}>
                {/* Games Section */}
                {globalResults.games.length > 0 && (
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                            Games ({globalResults.total_games})
                        </h3>
                        <div className="space-y-3">
                            {globalResults.games.map((game) => (
                                <GameResultItem key={game.id} game={game} query={query} />
                            ))}
                        </div>
                        {globalResults.total_games > globalResults.games.length && (
                            <Link
                                href={route('games.index', { search: query })}
                                className="inline-block mt-3 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                View all {globalResults.total_games} games →
                            </Link>
                        )}
                    </div>
                )}

                {/* Dialogue Section */}
                {globalResults.dialogue.length > 0 && (
                    <div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                            Dialogue ({globalResults.total_dialogue})
                        </h3>
                        <div className="space-y-3">
                            {globalResults.dialogue.map((dialogue) => (
                                <DialogueResultItem key={dialogue.id} dialogue={dialogue} query={query} />
                            ))}
                        </div>
                        {globalResults.total_dialogue > globalResults.dialogue.length && (
                            <Link
                                href={route('dialogue.browser', { q: query })}
                                className="inline-block mt-3 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                View all {globalResults.total_dialogue} dialogue texts →
                            </Link>
                        )}
                    </div>
                )}

                {/* No Results */}
                {globalResults.games.length === 0 && globalResults.dialogue.length === 0 && (
                    <div className="text-center py-8 text-gray-500 dark:text-gray-400">
                        No results found for "{query}"
                    </div>
                )}
            </div>
        );
    }

    if (results.length === 0 && query) {
        return (
            <div className={`text-center py-8 text-gray-500 dark:text-gray-400 ${className}`}>
                No results found for "{query}"
            </div>
        );
    }

    return (
        <div className={`space-y-4 ${className}`}>
            {/* Results */}
            <div className="space-y-3">
                {results.map((result) => (
                    <div key={result.id}>
                        {type === 'games' ? (
                            <GameResultItem game={result} query={query} />
                        ) : (
                            <DialogueResultItem dialogue={result} query={query} />
                        )}
                    </div>
                ))}
            </div>

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && onPageChange && (
                <div className="flex justify-center space-x-2 mt-6">
                    <button
                        onClick={() => onPageChange(pagination.current_page - 1)}
                        disabled={pagination.current_page <= 1}
                        className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        Previous
                    </button>
                    
                    <span className="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                        Page {pagination.current_page} of {pagination.last_page}
                    </span>
                    
                    <button
                        onClick={() => onPageChange(pagination.current_page + 1)}
                        disabled={pagination.current_page >= pagination.last_page}
                        className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
}

function GameResultItem({ game, query }: { game: SearchResult; query: string }) {
    const highlightText = (text: string): string => {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    };

    // Type cast the game properties to ensure proper typing
    const gameName = game.name as string;
    const gameAuthors = game.authors as string | undefined;
    const gameDescription = game.description as string | undefined;
    const gameStatus = game.status as string;
    const gameEngine = game.game_engine as string | undefined;
    const gameIsNsfw = game.is_nsfw as boolean;
    const gameIsPaid = game.is_paid as boolean;
    const gameSlug = game.slug as string;

    return (
        <div className="p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow dark:bg-gray-800 dark:border-gray-700">
            <Link
                href={route('games.show', gameSlug)}
                className="block"
            >
                <h4 
                    className="text-lg font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                    dangerouslySetInnerHTML={{ __html: highlightText(gameName) }}
                />
                {gameAuthors && (
                    <p 
                        className="text-sm text-gray-600 dark:text-gray-400 mt-1"
                        dangerouslySetInnerHTML={{ __html: `by ${highlightText(gameAuthors)}` }}
                    />
                )}
                {gameDescription && (
                    <p 
                        className="text-sm text-gray-700 dark:text-gray-300 mt-2 line-clamp-2"
                        dangerouslySetInnerHTML={{ __html: highlightText(gameDescription.substring(0, 150) + '...') }}
                    />
                )}
                <div className="flex items-center space-x-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <span className="capitalize">{gameStatus}</span>
                    {gameEngine && <span>{gameEngine}</span>}
                    {gameIsNsfw && <span className="text-red-500">NSFW</span>}
                    {gameIsPaid && <span className="text-green-500">Paid</span>}
                </div>
            </Link>
        </div>
    );
}

function DialogueResultItem({ dialogue, query }: { dialogue: SearchResult; query: string }) {
    const highlightText = (text: string): string => {
        if (!query) return text;
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    };

    // Type cast the dialogue properties to ensure proper typing
    const textContent = dialogue.text_content as string | undefined;
    const characterNames = dialogue.character_names as string[] | undefined;
    const gameNames = dialogue.game_names as string[] | undefined;

    return (
        <div className="p-4 bg-white border border-gray-200 rounded-lg dark:bg-gray-800 dark:border-gray-700">
            <div 
                className="text-gray-900 dark:text-white"
                dangerouslySetInnerHTML={{ 
                    __html: highlightText(textContent?.substring(0, 200) + '...' || '')
                }}
            />
            {(characterNames && characterNames.length > 0) || (gameNames && gameNames.length > 0) && (
                <div className="flex items-center space-x-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {characterNames && characterNames.length > 0 && (
                        <span>Characters: {characterNames.join(', ')}</span>
                    )}
                    {gameNames && gameNames.length > 0 && (
                        <span>Games: {gameNames.join(', ')}</span>
                    )}
                </div>
            )}
        </div>
    );
}