import {Link} from '@inertiajs/react';
import type {GameCardGame} from '@/hooks/useGameCard';

interface GameImageProps {
    game: GameCardGame;
    thumbnailUrl: string | null;
}

export default function GameImage({game, thumbnailUrl}: GameImageProps) {
    const gameName = game.effective_name;

    return (
        <Link
            href={route('games.show', game.slug)}
            className="relative block overflow-hidden rounded-t-2xl bg-gray-100 dark:bg-gray-700"
        >
            <div className="relative aspect-[315/250]">
                {thumbnailUrl ? (
                    <img
                        src={thumbnailUrl}
                        alt={gameName}
                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-gray-400">
                        <div className="text-center">
                            <div className="mb-3 text-5xl opacity-50">
                                🎮
                            </div>
                            <div className="text-sm font-medium">
                                No Image Available
                            </div>
                        </div>
                    </div>
                )}

                {/* Gradient overlay */}
                <div
                    className="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300 group-hover:opacity-100"/>

                {/* Hover CTA */}
                <div
                    className="absolute inset-0 flex items-center justify-center opacity-0 transition-all duration-300 group-hover:opacity-100">
                    <div
                        className="translate-y-4 transform rounded-xl bg-white/90 px-6 py-3 font-bold text-gray-900 shadow-xl backdrop-blur-sm transition-all duration-300 group-hover:translate-y-0 hover:bg-white">
                        View Details →
                    </div>
                </div>
            </div>
        </Link>
    );
}