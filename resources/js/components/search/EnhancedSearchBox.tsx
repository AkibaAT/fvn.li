import React, { useState, useEffect } from 'react';
import { MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';
import { useEnhancedSearch, SearchFilters, GlobalSearchResponse, SearchResponse } from '@/hooks/useEnhancedSearch';

interface EnhancedSearchBoxProps {
    type: 'games' | 'dialogue' | 'global';
    placeholder?: string;
    filters?: SearchFilters;
    onResults?: (results: SearchResponse | GlobalSearchResponse) => void;
    onError?: (error: string) => void;
    className?: string;
    showClearButton?: boolean;
    autoFocus?: boolean;
    debounceMs?: number;
}

export default function EnhancedSearchBox({
    type,
    placeholder = 'Search...',
    filters = {},
    onResults,
    onError,
    className = '',
    showClearButton = true,
    autoFocus = false,
    debounceMs = 300,
}: EnhancedSearchBoxProps) {
    const [query, setQuery] = useState('');
    const [isFocused, setIsFocused] = useState(false);

    const {
        results,
        globalResults,
        pagination,
        loading,
        error,
        search,
        clear,
    } = useEnhancedSearch({
        type,
        debounceMs,
    });

    // Handle results callback
    useEffect(() => {
        if (onResults) {
            if (type === 'global' && globalResults) {
                onResults({ success: true, data: globalResults, search_engine: 'meilisearch' });
            } else if (results.length > 0 || query.trim() === '') {
                onResults({ success: true, data: results, pagination: pagination!, search_engine: 'meilisearch' });
            }
        }
    }, [results, globalResults, pagination, onResults, type, query]);

    // Handle error callback
    useEffect(() => {
        if (error && onError) {
            onError(error);
        }
    }, [error, onError]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setQuery(value);
        
        if (value.trim()) {
            search(value, filters);
        } else {
            clear();
        }
    };

    const handleClear = () => {
        setQuery('');
        clear();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (query.trim()) {
            search(query, filters);
        }
    };

    return (
        <form onSubmit={handleSubmit} className={`relative ${className}`}>
            <div className="relative">
                {/* Search Icon */}
                <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <MagnifyingGlassIcon 
                        className={`h-5 w-5 transition-colors duration-200 ${
                            isFocused || query 
                                ? 'text-blue-500' 
                                : 'text-gray-400'
                        }`} 
                    />
                </div>

                {/* Input Field */}
                <input
                    type="text"
                    value={query}
                    onChange={handleInputChange}
                    onFocus={() => setIsFocused(true)}
                    onBlur={() => setIsFocused(false)}
                    placeholder={placeholder}
                    autoFocus={autoFocus}
                    className={`
                        w-full pl-10 pr-12 py-2.5 
                        border border-gray-300 rounded-lg
                        bg-white text-gray-900 placeholder-gray-500
                        focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                        dark:bg-gray-800 dark:border-gray-600 dark:text-white dark:placeholder-gray-400
                        dark:focus:ring-blue-500 dark:focus:border-blue-500
                        transition-all duration-200
                        ${loading ? 'pr-16' : ''}
                    `}
                />

                {/* Loading Spinner */}
                {loading && (
                    <div className="absolute inset-y-0 right-10 flex items-center">
                        <div className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                    </div>
                )}

                {/* Clear Button */}
                {showClearButton && query && !loading && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                    >
                        <XMarkIcon className="h-5 w-5" />
                    </button>
                )}
            </div>

            {/* Error Message */}
            {error && (
                <div className="absolute top-full left-0 right-0 mt-1 p-2 bg-red-50 border border-red-200 rounded-md text-sm text-red-600 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
                    {error}
                </div>
            )}

            {/* Search Engine Badge */}
            {(results.length > 0 || globalResults) && (
                <div className="absolute top-full right-0 mt-1">
                    <span className="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-md dark:bg-blue-900/20 dark:text-blue-400">
                        ⚡ Meilisearch
                    </span>
                </div>
            )}
        </form>
    );
}

// Export types for use in other components
export type { SearchFilters } from '@/hooks/useEnhancedSearch';
