import React from 'react';

interface SortControlsProps {
    currentSort: string;
    currentDirection: 'asc' | 'desc';
    sortOptions: Record<string, string>;
    onSortChange: (sort: string) => void;
    onDirectionChange: (direction: 'asc' | 'desc') => void;
    hasSearch: boolean;
}

export default function SortControls({
    currentSort,
    currentDirection,
    sortOptions,
    onSortChange,
    onDirectionChange,
    hasSearch,
}: SortControlsProps) {
    const defaultSort = hasSearch ? 'relevance' : 'first_visible_at';

    return (
        <div className="flex items-center gap-3">
            <label htmlFor="sort-select" className="text-sm text-gray-700 dark:text-gray-300">
                Sort by
            </label>
            <select
                id="sort-select"
                value={currentSort || defaultSort}
                onChange={(e) => onSortChange(e.target.value)}
                className="rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
                {Object.entries(sortOptions).map(([value, label]) => (
                    <option key={value} value={value}>
                        {label}
                    </option>
                ))}
            </select>
            <div
                className="inline-flex rounded-md shadow-sm"
                role="group"
                aria-label="Sort direction"
            >
                <button
                    type="button"
                    onClick={() => onDirectionChange('desc')}
                    className={`cursor-pointer rounded-l-md border border-gray-300 px-3 py-1 text-sm dark:border-gray-600 ${
                        currentDirection === 'desc'
                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                    }`}
                >
                    Desc
                </button>
                <button
                    type="button"
                    onClick={() => onDirectionChange('asc')}
                    className={`cursor-pointer -ml-px rounded-r-md border-t border-r border-b border-gray-300 px-3 py-1 text-sm dark:border-gray-600 ${
                        currentDirection === 'asc'
                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-600 dark:text-white'
                            : 'bg-white text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                    }`}
                >
                    Asc
                </button>
            </div>
        </div>
    );
}