import Pagination, {type PaginationMeta} from '@/components/pagination';

interface AdvancedPaginationProps {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    isLoading?: boolean;
    label?: string;
    perPageOptions?: number[];
    className?: string;
    // SSR-friendly: provide a function to build URLs for each page
    buildPageUrl?: (page: number) => string;
}

export default function AdvancedPagination({
                                               meta,
                                               onPageChange,
                                               onPerPageChange,
                                               isLoading = false,
                                               label = 'results',
                                               perPageOptions = [10, 25, 50, 100],
                                               className = '',
                                               buildPageUrl,
                                           }: AdvancedPaginationProps) {
    return (
        <div className={`mt-6 border-t border-gray-200 pt-4 dark:border-gray-700 ${className}`}>
            <div className="grid grid-cols-1 items-center gap-4 sm:grid-cols-3">
                {/* Left: info */}
                <div className="justify-self-start">
                    <Pagination
                        meta={meta}
                        label={label}
                        noDivider
                        variant="info"
                        alwaysShow
                        onChange={onPageChange}
                        loading={isLoading}
                        focusOnUpdate={false}
                        buildPageUrl={buildPageUrl}
                    />
                </div>

                {/* Center: controls */}
                <div className="justify-self-center">
                    <Pagination
                        meta={meta}
                        noDivider
                        variant="controls"
                        alwaysShow
                        onChange={onPageChange}
                        loading={isLoading}
                        focusOnUpdate={true}
                        buildPageUrl={buildPageUrl}
                    />
                </div>

                {/* Right: per-page selector */}
                <div className="justify-self-end">
                    <div className="flex items-center space-x-2">
                        <span className="text-sm text-gray-700 dark:text-gray-300">Show:</span>
                        <select
                            value={meta.per_page || perPageOptions[0]}
                            onChange={(e) => onPerPageChange(parseInt(e.target.value))}
                            disabled={isLoading}
                            className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            {perPageOptions.map((option) => (
                                <option key={option} value={option}>
                                    {option} per page
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>
        </div>
    );
}
