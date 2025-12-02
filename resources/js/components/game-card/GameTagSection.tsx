import type {GameCardGame} from '@/hooks/useGameCard';

interface GameTagSectionProps {
    orderedTags: GameCardGame['tags'];
    selectedTags?: string[];
    hiddenTagCount: number;
    tagsExpanded: boolean;
    setTagsExpanded: (expanded: boolean) => void;
    tagContainerRef: React.RefObject<HTMLDivElement | null>;
    setTagRef: (index: number) => (element: HTMLButtonElement | null) => void;
    handleTag: (tagId: number) => void;
}

export default function GameTagSection({
    orderedTags,
    selectedTags = [],
    hiddenTagCount,
    tagsExpanded,
    setTagsExpanded,
    tagContainerRef,
    setTagRef,
    handleTag
}: GameTagSectionProps) {
    if (!orderedTags || orderedTags.length === 0) return null;

    return (
        <div className="border-t border-gray-100 pt-2 dark:border-gray-700/50">
            <div className="flex items-center gap-1.5">
                <div
                    ref={tagContainerRef}
                    className={`relative flex flex-wrap items-start gap-1.5 transition-all duration-300 flex-1 ${
                        tagsExpanded ? 'max-h-none' : 'h-15 overflow-hidden'
                    }`}
                >
                    {orderedTags.map((tag, index) => (
                        <button
                            key={tag.id}
                            ref={setTagRef(index)}
                            data-tag-id={tag.id}
                            onClick={() => handleTag(tag.id)}
                            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors duration-200 ${
                                selectedTags.includes(String(tag.id))
                                    ? 'cursor-pointer border-2 border-blue-700 bg-blue-600 text-white shadow-md dark:border-blue-500 dark:bg-blue-700'
                                    : 'cursor-pointer border border-gray-200 bg-white text-gray-600 hover:bg-blue-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-blue-900/20'
                            }`}
                            title={
                                selectedTags.includes(String(tag.id))
                                    ? 'Click to remove this filter'
                                    : 'Click to filter by this tag'
                            }
                        >
                            {tag.name}
                        </button>
                    ))}
                </div>
                {hiddenTagCount > 0 && !tagsExpanded && (
                    <button
                        onClick={() => setTagsExpanded(!tagsExpanded)}
                        className="group flex items-center justify-center w-6 h-6 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200 cursor-pointer flex-shrink-0"
                        title={`Show ${hiddenTagCount} more tags`}
                        aria-label={`Show ${hiddenTagCount} more tags`}
                    >
                        <svg
                            className="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300 transition-all duration-200 rotate-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                  d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                )}
            </div>
        </div>
    );
}