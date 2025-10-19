import React, {useEffect, useRef} from 'react';
import {router} from '@inertiajs/react';
import MultiSelect from '@/components/multi-select';
import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';
import type {CurrentFilters, FilterOptions} from '@/types';

interface FilterModalProps {
    isOpen: boolean;
    onClose: () => void;
    filters: FilterOptions;
    currentFilters: CurrentFilters;
    onGamesPage?: boolean; // If true, updates current page; if false, navigates to games page
}

export function FilterModal({
                                isOpen,
                                onClose,
                                filters,
                                currentFilters,
                                onGamesPage = false
                            }: FilterModalProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const filterCloseBtnRef = useRef<HTMLButtonElement>(null);
    const filterOpenerRef = useRef<HTMLElement | null>(null);

    // Handle dialog open/close
    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (isOpen) {
            // Remember the current focused element (expected to be the Filters button)
            filterOpenerRef.current =
                (document.activeElement as HTMLElement) || null;
            dialog.showModal();
            // Focus the dialog's close button on the next frame for a stable target
            requestAnimationFrame(() => {
                filterCloseBtnRef.current?.focus();
            });
        } else if (dialog.open) {
            dialog.close();
        }
    }, [isOpen]);

    // Handle click outside to close
    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        const handleClick = (e: MouseEvent) => {
            if (e.target === dialog) {
                // Clicked on backdrop
                onClose();
            }
        };

        if (isOpen) {
            dialog.addEventListener('click', handleClick);
            return () => dialog.removeEventListener('click', handleClick);
        }
    }, [isOpen, onClose]);

    const updateFilters = (newFilters: Partial<CurrentFilters>) => {
        const params = {...currentFilters, ...newFilters};

        // Remove empty arrays and false values
        Object.keys(params).forEach((key) => {
            const value = params[key as keyof CurrentFilters];
            if (Array.isArray(value) && value.length === 0) {
                delete params[key as keyof CurrentFilters];
            } else if (
                value === false ||
                value === '' ||
                value === null ||
                value === undefined
            ) {
                delete params[key as keyof CurrentFilters];
            }
        });

        if (onGamesPage) {
            // Update current games page
            router.get(route('games.index'), params, {
                preserveState: true,
                preserveScroll: true,
            });
        } else {
            // Navigate to games page with filters
            router.get(route('games.index'), params, {
                preserveState: false,
            });
            onClose();
        }
    };

    const toggleFilter = (type: string, value: string) => {
        // Map filter types to their correct property names
        const propertyMap: Record<string, keyof CurrentFilters> = {
            status: 'selectedStatuses',
            engine: 'selectedEngines',
            platform: 'selectedPlatforms',
            storePlatform: 'selectedStorePlatforms',
            language: 'selectedLanguages',
            gameJam: 'selectedGameJams',
            tag: 'selectedTags',
        };

        const propertyName = propertyMap[type];
        if (!propertyName) return;

        const currentArray = (currentFilters[propertyName] as string[]) || [];
        const newArray = currentArray.includes(value)
            ? currentArray.filter((item) => item !== value)
            : [...currentArray, value];

        updateFilters({[propertyName]: newArray});
    };

    const clearFilters = () => {
        updateFilters({
            selectedStatuses: [],
            selectedEngines: [],
            selectedPlatforms: [],
            selectedLanguages: [],
            selectedGameJams: [],
            selectedTags: [],
            nsfw: false,
            sfw: false,
            showPaid: false,
            showFree: false,
            showDemo: false,
        });
    };

    const hasActiveFilters = () => {
        const {
            selectedStatuses,
            selectedEngines,
            selectedPlatforms,
            selectedLanguages,
            selectedGameJams,
            selectedTags,
            nsfw,
            sfw,
            showPaid,
            showFree,
            showDemo,
        } = currentFilters;

        return Boolean(
            (selectedStatuses && selectedStatuses.length > 0) ||
            (selectedEngines && selectedEngines.length > 0) ||
            (selectedPlatforms && selectedPlatforms.length > 0) ||
            (selectedLanguages && selectedLanguages.length > 0) ||
            (selectedGameJams && selectedGameJams.length > 0) ||
            (selectedTags && selectedTags.length > 0) ||
            nsfw ||
            sfw ||
            showPaid ||
            showFree ||
            showDemo,
        );
    };

    return (
        <dialog
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-labelledby="games-filter-title"
            aria-describedby="games-filter-desc"
            className="h-full max-h-none w-full max-w-none border-0 bg-transparent p-0 backdrop:bg-black/50 backdrop:backdrop-blur-sm"
        >
            {/* Accessible name/description for the dialog */}
            <h1 id="games-filter-title" className="sr-only">
                Filter Games
            </h1>
            <p id="games-filter-desc" className="sr-only">
                Use the options to filter games by content, platforms,
                languages, engine, tags, jams, and visibility.
            </p>

            {/* Right Sidebar */}
            <div className="ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900">
                {/* Header */}
                <div
                    className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Filter Games
                    </h2>
                    <div className="flex items-center space-x-2">
                        {hasActiveFilters() && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                            >
                                Clear All
                            </button>
                        )}
                        <button
                            ref={filterCloseBtnRef}
                            type="button"
                            onClick={onClose}
                            className="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            aria-label="Close filter dialog"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto">
                    <div className="space-y-8 p-6">
                        {/* Content & Pricing */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                                Content & Pricing
                            </h3>
                            <div className="space-y-3">
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.sfw || false}
                                        onChange={(e) => updateFilters({sfw: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        Safe for Work
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.nsfw || false}
                                        onChange={(e) => updateFilters({nsfw: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        NSFW Content
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.showFree || false}
                                        onChange={(e) => updateFilters({showFree: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-green-600 focus:ring-green-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        Free Games
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.showPaid || false}
                                        onChange={(e) => updateFilters({showPaid: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        Paid Games
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.showDemo || false}
                                        onChange={(e) => updateFilters({showDemo: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        Has Demo
                                    </span>
                                </label>
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={currentFilters.showSale || false}
                                        onChange={(e) => updateFilters({showSale: e.target.checked})}
                                        className="mr-3 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        On Sale
                                    </span>
                                </label>
                            </div>
                        </div>

                        {/* Status */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-green-500"></span>
                                Status
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Status"
                                    items={filters.statuses}
                                    selectedItems={currentFilters.selectedStatuses || []}
                                    onToggle={(value) => toggleFilter('status', value)}
                                    placeholder="Select status..."
                                />
                                {currentFilters.selectedStatuses && currentFilters.selectedStatuses.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedStatuses: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all statuses
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Platforms */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                                Platforms
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Platforms"
                                    items={filters.platforms}
                                    selectedItems={currentFilters.selectedPlatforms || []}
                                    onToggle={(value) => toggleFilter('platform', value)}
                                    placeholder="Select platforms..."
                                    renderItem={(value, label) => {
                                        const platformIcons: Record<
                                            string,
                                            {
                                                icon: string;
                                                color: string;
                                            }
                                        > = {
                                            windows: {
                                                icon: 'icon-windows',
                                                color: 'text-platform-windows',
                                            },
                                            linux: {
                                                icon: 'icon-linux',
                                                color: 'text-platform-linux',
                                            },
                                            mac: {
                                                icon: 'icon-apple',
                                                color: 'text-platform-mac',
                                            },
                                            android: {
                                                icon: 'icon-android',
                                                color: 'text-platform-android',
                                            },
                                            web: {
                                                icon: 'icon-web',
                                                color: 'text-platform-web',
                                            },
                                        };
                                        const iconMeta = platformIcons[value];
                                        return (
                                            <div className="flex items-center">
                                                {iconMeta && (
                                                    <i
                                                        className={`${iconMeta.icon} ${iconMeta.color} mr-2 text-sm`}
                                                    />
                                                )}
                                                <span>{typeof label === 'string' ? label : label.name || label.ref_name || value}</span>
                                            </div>
                                        );
                                    }}
                                />
                                {currentFilters.selectedPlatforms && currentFilters.selectedPlatforms.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedPlatforms: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all platforms
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Store Platforms */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-orange-500"></span>
                                Store Platforms
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Store Platforms"
                                    items={filters.storePlatforms}
                                    selectedItems={currentFilters.selectedStorePlatforms || []}
                                    onToggle={(value) => toggleFilter('storePlatform', value)}
                                    placeholder="Select store platforms..."
                                    renderItem={(value, label) => {
                                        const renderIcon = () => {
                                            switch (value) {
                                                case 'itch_io':
                                                    return <ItchioIcon className="h-4 w-4 text-orange-600 dark:text-orange-400 mr-2" />;
                                                case 'steam':
                                                    return <SteamIcon className="h-4 w-4 text-blue-600 dark:text-blue-400 mr-2" />;
                                                case 'other':
                                                    return (
                                                        <svg
                                                            className="h-4 w-4 text-gray-600 dark:text-gray-400 mr-2"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            aria-hidden="true"
                                                        >
                                                            <circle cx="12" cy="12" r="10" />
                                                            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                                        </svg>
                                                    );
                                                default:
                                                    return null;
                                            }
                                        };
                                        return (
                                            <div className="flex items-center">
                                                {renderIcon()}
                                                <span>{typeof label === 'string' ? label : label.name || label.ref_name || value}</span>
                                            </div>
                                        );
                                    }}
                                />
                                {currentFilters.selectedStorePlatforms && currentFilters.selectedStorePlatforms.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedStorePlatforms: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all store platforms
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Languages */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-indigo-500"></span>
                                Languages
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Languages"
                                    items={filters.languages}
                                    selectedItems={currentFilters.selectedLanguages || []}
                                    onToggle={(value) => toggleFilter('language', value)}
                                    placeholder="Select languages..."
                                    renderItem={(value, item) => {
                                        const language = typeof item === 'string' ? {
                                            ref_name: item,
                                            flag_code: value
                                        } : item;
                                        return (
                                            <span className="flex items-center">
                                                {language.flag_code && (
                                                    <span
                                                        className={`fi fi-${language.flag_code} mr-2 rounded-xs`}
                                                    ></span>
                                                )}
                                                <span>{language.ref_name || language.name || value}</span>
                                            </span>
                                        );
                                    }}
                                />
                                {currentFilters.selectedLanguages && currentFilters.selectedLanguages.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedLanguages: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all languages
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Game Engine */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-cyan-500"></span>
                                Game Engine
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Game Engines"
                                    items={filters.gameEngines}
                                    selectedItems={currentFilters.selectedEngines || []}
                                    onToggle={(value) => toggleFilter('engine', value)}
                                    placeholder="Select engines..."
                                />
                                {currentFilters.selectedEngines && currentFilters.selectedEngines.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedEngines: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all engines
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Tags */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-teal-500"></span>
                                Tags
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Tags"
                                    items={filters.tags}
                                    selectedItems={currentFilters.selectedTags || []}
                                    onToggle={(value) => toggleFilter('tag', value)}
                                />
                                {currentFilters.selectedTags && currentFilters.selectedTags.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedTags: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all tags
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Game Jams */}
                        <div>
                            <h3 className="mb-4 flex items-center text-sm font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2 h-2 w-2 rounded-full bg-orange-500"></span>
                                Game Jams
                            </h3>
                            <div className="space-y-2">
                                <MultiSelect
                                    title="Game Jams"
                                    items={filters.gameJams}
                                    selectedItems={currentFilters.selectedGameJams || []}
                                    onToggle={(value) => toggleFilter('gameJam', value)}
                                />
                                {currentFilters.selectedGameJams && currentFilters.selectedGameJams.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => updateFilters({selectedGameJams: []})}
                                        className="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200"
                                    >
                                        Clear all game jams
                                    </button>
                                )}
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </dialog>
    );
}
