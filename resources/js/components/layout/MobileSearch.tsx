import React, {useEffect, useRef, useCallback} from 'react';
import {useSearch} from '@/hooks/useSearch';

interface MobileSearchProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function MobileSearch({isOpen, onClose}: MobileSearchProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const wasFocusedRef = useRef(false);
    
    // Detect current page internally to avoid prop changes causing re-renders
    const currentUrl = typeof window !== 'undefined' ? window.location?.href ?? '' : '';
    const detectedIsGamesPage = (currentUrl.endsWith('/games') && !currentUrl.includes('/my/games')) || currentUrl.includes('/games?');
    
    const {
        searchTerm,
        isSearching,
        handleSearchSubmit,
        handleSearchChange,
        handleSearchClear,
        initializeSearchFromUrl,
    } = useSearch({isGamesPage: detectedIsGamesPage});

    // Track when input is focused
    const handleFocus = useCallback(() => {
        wasFocusedRef.current = true;
    }, []);

    // Track when input is blurred
    const handleBlur = useCallback(() => {
        wasFocusedRef.current = false;
    }, []);

    // Initialize search from URL when component mounts
    useEffect(() => {
        initializeSearchFromUrl();
    }, [initializeSearchFromUrl]);

    // Focus input when mobile search opens
    useEffect(() => {
        if (isOpen && inputRef.current) {
            // Small delay to ensure the element is fully rendered
            setTimeout(() => {
                inputRef.current?.focus();
            }, 100);
        }
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div
            className="nav-glass p-4 lg:hidden"
        >
            <div className="relative">
                <form
                    onSubmit={(e) => {
                        handleSearchSubmit(e);
                        onClose();
                    }}
                    className="w-full"
                >
                    <div className="relative">
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            {isSearching ? (
                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-[var(--color-brand-primary)] border-t-transparent"></div>
                            ) : (
                                <svg className="h-4 w-4 text-[var(--color-ui-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            )}
                        </div>
                        <input
                            ref={inputRef}
                            type="text"
                            value={searchTerm}
                            onChange={handleSearchChange}
                            onFocus={handleFocus}
                            onBlur={handleBlur}
                            placeholder="Search games, authors, tags..."
                            className="w-full rounded-lg border border-[var(--color-ui-border)] bg-[var(--color-ui-surface)] py-3 pr-32 pl-10 text-[var(--color-ui-text)] placeholder-[var(--color-ui-text-muted)] transition-all duration-200 focus:border-[var(--color-brand-primary)] focus:ring-2 focus:ring-[var(--color-brand-primary)]"
                            autoComplete="off"
                        />
                        {searchTerm && (
                            <button
                                type="button"
                                onClick={handleSearchClear}
                                className="absolute top-1/2 right-20 -translate-y-1/2 transform rounded-full p-1.5 text-[var(--color-ui-text-muted)] hover:text-[var(--color-ui-text)]"
                                aria-label="Clear search"
                            >
                                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                        <button
                            type="submit"
                            className="absolute top-1/2 right-2 -translate-y-1/2 transform rounded-md bg-[var(--color-brand-primary)] px-4 py-1.5 text-sm font-medium text-white transition-all duration-200 hover:bg-[var(--color-brand-primary-dark)]"
                        >
                            Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
