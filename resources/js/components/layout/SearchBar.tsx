import React, {memo, useCallback, useRef, useEffect} from 'react';
import { useSearch } from '@/hooks/useSearch';

interface SearchBarProps {
    className?: string;
}

const SearchIcon = memo(({ isSearching }: { isSearching: boolean }) => (
    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        {isSearching ? (
            <div className="h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"></div>
        ) : (
            <span className="text-sm text-gray-400">🔍</span>
        )}
    </div>
));

const ClearButton = memo(({ onClick }: { onClick: () => void }) => (
    <button
        type="button"
        onClick={onClick}
        className="absolute top-1/2 right-20 -translate-y-1/2 transform rounded-full p-1 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
        aria-label="Clear search"
    >
        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
));

const SearchButton = memo(() => (
    <button
        type="submit"
        className="cursor-pointer absolute top-1/2 right-1 -translate-y-1/2 transform rounded-md bg-blue-600 px-3 py-1 text-sm font-medium text-white transition-all duration-200 hover:bg-blue-700"
    >
        Search
    </button>
));

export default function SearchBar({className = ''}: SearchBarProps) {
    // Detect current page internally so the header can be persistent
    const currentUrl = typeof window !== 'undefined' ? window.location?.href ?? '' : '';
    const detectedIsGamesPage = (currentUrl.endsWith('/games') && !currentUrl.includes('/my/games')) || currentUrl.includes('/games?');

    const {
        searchTerm,
        isSearching,
        handleSearchSubmit,
        handleSearchClear,
        handleSearchChange,
        initializeSearchFromUrl,
    } = useSearch({isGamesPage: detectedIsGamesPage});

    const searchInputRef = useRef<HTMLInputElement>(null);
    const wasFocusedRef = useRef(false);

    // Initialize search term from URL on mount
    useEffect(() => {
        initializeSearchFromUrl();
    }, [initializeSearchFromUrl]);

    // Track when input is focused
    const handleFocus = useCallback(() => {
        wasFocusedRef.current = true;
    }, []);

    // Track when input is blurred
    const handleBlur = useCallback(() => {
        wasFocusedRef.current = false;
    }, []);

    // Clear search term
    const clearSearch = useCallback(() => {
        handleSearchClear();
        if (searchInputRef.current) {
            searchInputRef.current.focus();
        }
    }, [handleSearchClear]);

    return (
        <form
            onSubmit={handleSearchSubmit}
            className={`w-full ${className}`}
        >
            <div className="group relative">
                <SearchIcon isSearching={isSearching} />
                <input
                    id="global-search-input"
                    ref={searchInputRef}
                    type="text"
                    value={searchTerm}
                    onChange={handleSearchChange}
                    onFocus={handleFocus}
                    onBlur={handleBlur}
                    name="search"
                    placeholder="Search games, authors, tags..."
                    className="w-full rounded-lg border border-gray-200 bg-white/80 py-2 pr-32 pl-10 text-sm text-gray-900 placeholder-gray-500 transition-all duration-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700/80 dark:text-white dark:placeholder-gray-400 focus:outline-none"
                    autoComplete="off"
                    aria-label="Search games, authors, and tags"
                />
                {searchTerm && <ClearButton onClick={clearSearch} />}
                <SearchButton />
            </div>
        </form>
    );
}
