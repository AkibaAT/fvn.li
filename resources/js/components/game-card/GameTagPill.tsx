import type {GameCardGame} from '@/hooks/useGameCard';

interface GameTagPillProps {
    tag: NonNullable<GameCardGame['tags']>[0];
    isActive?: boolean;
    onClick: (tagId: number) => void;
}

export default function GameTagPill({tag, isActive = false, onClick}: GameTagPillProps) {
    return (
        <button
            key={tag.id}
            onClick={() => onClick(tag.id)}
            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200 ${
                isActive
                    ? 'cursor-pointer border-2 border-teal-700 bg-teal-600 text-white shadow-md dark:border-teal-500 dark:bg-teal-700'
                    : 'cursor-pointer border border-gray-200 bg-white text-gray-600 hover:bg-teal-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-teal-900/20'
            }`}
            title={
                isActive
                    ? 'Click to remove this filter'
                    : 'Click to filter by this tag'
            }
        >
            {tag.name}
        </button>
    );
}