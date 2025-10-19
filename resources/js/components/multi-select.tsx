import React, {useEffect, useRef, useState} from 'react';

interface SelectItem {
    ref_name?: string;
    name?: string;
    flag_code?: string;
}

interface MultiSelectProps {
    title: string;
    items: Record<string, string | SelectItem>;
    selectedItems: string[];
    onToggle: (value: string) => void;
    renderItem?: (value: string, item: string | SelectItem) => React.ReactNode;
    placeholder?: string;
}

export default function MultiSelect({
                                        title,
                                        items,
                                        selectedItems,
                                        onToggle,
                                        renderItem,
                                        placeholder,
                                    }: MultiSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);


    const itemEntries = Object.entries(items);
    const filteredItems = search
        ? itemEntries.filter(([value, item]) => {
            const label = typeof item === 'string' ? item : item.name || item.ref_name || value;
            return label.toLowerCase().includes(search.toLowerCase());
        })
        : itemEntries;

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setSearch('');
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [isOpen]);

    const getDisplayLabel = (value: string, item: string | SelectItem) => {
        if (typeof item === 'string') return item;
        return item.name || item.ref_name || value;
    };

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:outline-none"
                aria-expanded={isOpen}
                aria-haspopup="listbox"
                aria-controls={`${title}-options`}
            >
                <span className="flex-1 min-w-0">
                    {selectedItems.length > 0 ? (
                        <span className="flex flex-wrap gap-1" role="list" aria-label={`Selected ${title.toLowerCase()}`}>
                            {selectedItems.map((value) => {
                                const item = items[value];
                                const label = getDisplayLabel(value, item);
                                return (
                                    <span
                                        key={value}
                                        className="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:border-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                        role="listitem"
                                    >
                                        {renderItem ? renderItem(value, item) : label}
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onToggle(value);
                                            }}
                                            className="ml-1 inline-flex h-3 w-3 items-center justify-center rounded-full hover:opacity-80 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            aria-label={`Remove ${label}`}
                                        >
                                            <span aria-hidden="true">×</span>
                                        </button>
                                    </span>
                                );
                            })}
                        </span>
                    ) : (
                        <span className="text-gray-500 dark:text-gray-400">
                            {placeholder || `Select ${title.toLowerCase()}...`}
                        </span>
                    )}
                </span>
                <svg
                    className={`h-4 w-4 flex-shrink-0 ml-2 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7"/>
                </svg>
            </button>


            {isOpen && (
                <div
                    id={`${title}-options`}
                    className="absolute z-10 mt-1 w-full rounded-lg border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-700"
                    role="listbox"
                    aria-label={`${title} options`}
                    aria-multiselectable="true"
                >
                    {/* Search */}
                    <div className="p-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={`Search ${title.toLowerCase()}...`}
                            className="w-full rounded border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:outline-none"
                            aria-label={`Search ${title.toLowerCase()}`}
                        />
                    </div>

                    {/* Options */}
                    <div className="max-h-48 overflow-y-auto" role="group">
                        {filteredItems.length === 0 ? (
                            <div className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400" role="status">
                                No {title.toLowerCase()} found
                            </div>
                        ) : (
                            filteredItems.map(([value, item]) => (
                                <label
                                    key={value}
                                    className="flex cursor-pointer items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-600"
                                    role="option"
                                    aria-selected={selectedItems.includes(value)}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedItems.includes(value)}
                                        onChange={() => onToggle(value)}
                                        className="mr-2 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                        aria-label={`Select ${getDisplayLabel(value, item)}`}
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        {renderItem ? renderItem(value, item) : getDisplayLabel(value, item)}
                                    </span>
                                </label>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
