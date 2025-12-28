import React from 'react';
import Pagination from '@/components/pagination';
import type {CurrentFilters} from '@/types';

interface PaginationMeta {
    current_page: number;
    from?: number;
    last_page: number;
    path?: string;
    per_page: number;
    to?: number;
    total: number;
}

interface PaginationControlsProps {
    meta: PaginationMeta;
    currentFilters: CurrentFilters;
    updateFilters: (filters: Partial<CurrentFilters>) => void;
}

export default function PaginationControls({
    meta,
    currentFilters,
    updateFilters,
}: PaginationControlsProps) {
    const handlePageChange = (page: number) => {
        updateFilters({ page });
    };

    const handlePerPageChange = (perPage: number) => {
        updateFilters({ perPage });
    };

    // Build SSR-friendly URLs for pagination
    const buildPageUrl = (page: number): string => {
        const params = new URLSearchParams();

        // Add all current filters to the URL
        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.selectedPlatforms?.length) {
            currentFilters.selectedPlatforms.forEach(p => params.append('platform[]', p));
        }
        if (currentFilters.selectedLanguages?.length) {
            currentFilters.selectedLanguages.forEach(l => params.append('language[]', l));
        }
        if (currentFilters.selectedTags?.length) {
            currentFilters.selectedTags.forEach(t => params.append('tag[]', t));
        }
        if (currentFilters.selectedStatuses?.length) {
            currentFilters.selectedStatuses.forEach(s => params.append('status[]', s));
        }
        if (currentFilters.selectedEngines?.length) {
            currentFilters.selectedEngines.forEach(e => params.append('engine[]', e));
        }
        if (currentFilters.selectedGameJams?.length) {
            currentFilters.selectedGameJams.forEach(j => params.append('gameJam[]', j));
        }
        if (currentFilters.nsfw) params.set('nsfw', '1');
        if (currentFilters.showPaid) params.set('showPaid', '1');
        if (currentFilters.showDemo) params.set('showDemo', '1');
        if (currentFilters.sort) params.set('sort', currentFilters.sort);
        if (currentFilters.direction) params.set('direction', currentFilters.direction);
        if (currentFilters.perPage) params.set('perPage', currentFilters.perPage.toString());

        // Add the page parameter
        params.set('page', page.toString());

        return `/games?${params.toString()}`;
    };

    return (
        <div className="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div className="grid grid-cols-1 items-center gap-4 sm:grid-cols-3">
                {/* Left: info */}
                <div className="justify-self-start">
                    <Pagination
                        meta={meta}
                        label="results"
                        noDivider
                        variant="info"
                        onChange={handlePageChange}
                        buildPageUrl={buildPageUrl}
                    />
                </div>

                {/* Center: controls */}
                <div className="justify-self-center">
                    <Pagination
                        meta={meta}
                        noDivider
                        variant="controls"
                        onChange={handlePageChange}
                        buildPageUrl={buildPageUrl}
                    />
                </div>

                {/* Right: per-page selector */}
                <div className="justify-self-end">
                    <div className="flex items-center space-x-2">
                        <label htmlFor="per-page-select" className="text-sm text-gray-700 dark:text-gray-300">
                            Show:
                        </label>
                        <select
                            id="per-page-select"
                            value={currentFilters.perPage || 8}
                            onChange={(e) =>
                                handlePerPageChange(parseInt(e.target.value))
                            }
                            className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">8 per page</option>
                            <option value="16">16 per page</option>
                            <option value="24">24 per page</option>
                            <option value="32">32 per page</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    );
}