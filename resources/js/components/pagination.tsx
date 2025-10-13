import type React from 'react';
import {useEffect, useRef} from 'react';

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page?: number;
    total: number;
    from?: number | null;
    to?: number | null;
};

type Props = {
    meta: PaginationMeta;
    loading?: boolean;
    label?: string;
    onChange: (page: number) => void;
    noDivider?: boolean;
    className?: string;
    variant?: 'full' | 'info' | 'controls';
    focusOnUpdate?: boolean;
    alwaysShow?: boolean;
    pageSelectLimit?: number; // cap number of options to avoid rendering thousands
    // SSR-friendly: provide a function to build URLs for each page
    buildPageUrl?: (page: number) => string;
};

export default function Pagination({
                                       meta,
                                       loading = false,
                                       label = 'items',
                                       onChange,
                                       noDivider = false,
                                       className = '',
                                       variant = 'full',
                                       focusOnUpdate = false,
                                       alwaysShow = false,
                                       pageSelectLimit = 200,
                                       buildPageUrl,
                                   }: Props) {
    const prevButtonRef = useRef<HTMLButtonElement>(null);
    const nextButtonRef = useRef<HTMLButtonElement>(null);
    const selectRef = useRef<HTMLSelectElement>(null);
    const lastActionRef = useRef<'prev' | 'next' | 'select' | null>(null);

    // Focus management after page change
    useEffect(() => {
        if (focusOnUpdate && lastActionRef.current) {
            const focusTarget = {
                prev: prevButtonRef.current,
                next: nextButtonRef.current,
                select: selectRef.current,
            }[lastActionRef.current];

            if (focusTarget) {
                // Small delay to ensure DOM is updated
                setTimeout(() => {
                    focusTarget.focus();
                }, 50);
            }
            lastActionRef.current = null;
        }
    }, [meta.current_page, focusOnUpdate]);

    if (!alwaysShow && (!meta || meta.last_page <= 1)) return null;

    const canPrev = meta.current_page > 1 && !loading;
    const canNext = meta.current_page < meta.last_page && !loading;
    const containerBase = 'flex items-center justify-between';
    const framed = 'mt-6 border-t border-gray-200 dark:border-gray-700 pt-4';
    const unframed = 'pt-0';
    const containerClass =
        `${containerBase} ${noDivider ? unframed : framed} ${className}`.trim();

    const Info = (
        <div className="flex items-center text-sm text-gray-500 dark:text-gray-400">
            {typeof meta.from === 'number' && typeof meta.to === 'number' ? (
                <span>
                    Showing {meta.from} to {meta.to} of {meta.total} {label}
                </span>
            ) : (
                <span>
                    Page {meta.current_page} of {meta.last_page}
                </span>
            )}
        </div>
    );

    const handlePrevious = () => {
        lastActionRef.current = 'prev';
        onChange(meta.current_page - 1);
    };

    const handleNext = () => {
        lastActionRef.current = 'next';
        onChange(meta.current_page + 1);
    };

    const handleSelectChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        lastActionRef.current = 'select';
        onChange(parseInt(e.target.value));
    };

    const Controls = (
        <div
            className={`flex items-center space-x-3 ${variant === 'controls' ? className : ''}`.trim()}
        >
            {buildPageUrl && canPrev ? (
                <a
                    ref={prevButtonRef as any}
                    href={buildPageUrl(meta.current_page - 1)}
                    onClick={(e) => {
                        e.preventDefault();
                        handlePrevious();
                    }}
                    className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label={`Go to page ${meta.current_page - 1}`}
                >
                    Previous
                </a>
            ) : (
                <button
                    ref={prevButtonRef}
                    onClick={handlePrevious}
                    disabled={!canPrev}
                    className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label={`Go to page ${meta.current_page - 1}`}
                >
                    Previous
                </button>
            )}
            <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-500 dark:text-gray-400">
                    Page
                </span>
                <select
                    ref={selectRef}
                    value={meta.current_page}
                    onChange={handleSelectChange}
                    disabled={loading}
                    className="cursor-pointer rounded border border-gray-200 bg-white px-3 py-1 text-sm text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    aria-label="Select page number"
                >
                    {(() => {
                        const totalPages = meta.last_page;
                        if (totalPages <= 0) return null;

                        const limit = Math.max(10, pageSelectLimit);
                        if (totalPages <= limit) {
                            return Array.from({length: totalPages}, (_, i) => (
                                <option key={i + 1} value={i + 1}>
                                    {i + 1}
                                </option>
                            ));
                        }

                        const half = Math.floor(limit / 2);
                        let start = Math.max(1, meta.current_page - half);
                        const end = Math.min(totalPages, start + limit - 1);
                        start = Math.max(1, end - limit + 1);

                        const out: React.ReactElement[] = [];
                        if (start > 1) {
                            out.push(
                                <option key={1} value={1}>
                                    1
                                </option>,
                            );
                            if (start > 2) {
                                out.push(
                                    <option key={-1} value={start - 1}>…</option>,
                                );
                            }
                        }

                        for (let p = start; p <= end; p++) {
                            out.push(
                                <option key={p} value={p}>
                                    {p}
                                </option>,
                            );
                        }

                        if (end < totalPages) {
                            if (end < totalPages - 1) {
                                out.push(
                                    <option key={-2} value={end + 1}>…</option>,
                                );
                            }
                            out.push(
                                <option key={totalPages} value={totalPages}>
                                    {totalPages}
                                </option>,
                            );
                        }

                        return out;
                    })()}
                </select>
                <span className="text-sm text-gray-500 dark:text-gray-400">
                    of {meta.last_page}
                </span>
            </div>
            {buildPageUrl && canNext ? (
                <a
                    ref={nextButtonRef as any}
                    href={buildPageUrl(meta.current_page + 1)}
                    onClick={(e) => {
                        e.preventDefault();
                        handleNext();
                    }}
                    className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label={`Go to page ${meta.current_page + 1}`}
                >
                    Next
                </a>
            ) : (
                <button
                    ref={nextButtonRef}
                    onClick={handleNext}
                    disabled={!canNext}
                    className="cursor-pointer rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                    aria-label={`Go to page ${meta.current_page + 1}`}
                >
                    Next
                </button>
            )}
        </div>
    );

    if (variant === 'info') {
        return Info;
    }
    if (variant === 'controls') {
        return Controls;
    }

    return (
        <div className={containerClass}>
            {Info}
            {Controls}
        </div>
    );
}
