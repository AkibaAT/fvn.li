import LoadingSpinner from '@/components/loading-spinner';
import React from 'react';

interface FileStatsModalProps {
    versionId: number;
    showFileStats: number | null;
    fileStatsData: {
        version?: { version?: string };
        file_categories?: Array<{
            category: string;
            total_count: number;
            total_size: number;
            file_types: Array<{
                extension: string;
                count: number;
                size: number;
            }>;
        }>;
    } | null;
    statsLoading: boolean;
    closeFileStatsDialog: (versionId: number) => void;
}

/**
 * Format bytes to human readable format
 * Matches the PHP HelperService::formatBytes() implementation
 */
function formatBytes(bytes: number, precision: number = 2): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];

    bytes = Math.max(bytes, 0);
    const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    const powClamped = Math.min(pow, units.length - 1);

    const value = bytes / Math.pow(1024, powClamped);

    return `${value.toFixed(precision)} ${units[powClamped]}`;
}

export default function FileStatsModal({
                                           versionId,
                                           showFileStats,
                                           fileStatsData,
                                           statsLoading,
                                           closeFileStatsDialog,
                                       }: FileStatsModalProps) {
    const closeBtnRef = React.useRef<HTMLButtonElement>(null);
    const openerRef = React.useRef<HTMLElement | null>(null);

    // Manage initial focus and restore focus to invoker
    React.useEffect(() => {
        const dialogEl = document.getElementById(
            `file-stats-${versionId}`,
        ) as HTMLDialogElement | null;
        if (!dialogEl) return;

        const handleOpen = () => {
            openerRef.current = (document.activeElement as HTMLElement) || null;
            requestAnimationFrame(() => {
                closeBtnRef.current?.focus();
            });
        };

        const handleClose = () => {
            openerRef.current?.focus?.();
            openerRef.current = null;
        };

        dialogEl.addEventListener('close', handleClose);
        if (showFileStats === versionId && dialogEl.open) {
            handleOpen();
        }

        return () => {
            dialogEl.removeEventListener('close', handleClose);
        };
    }, [showFileStats, versionId]);

    return (
        <dialog
            id={`file-stats-${versionId}`}
            role="dialog"
            aria-modal="true"
            aria-labelledby={`file-stats-title-${versionId}`}
            aria-describedby={`file-stats-desc-${versionId}`}
            className="m-auto w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
        >
            {/* Accessible name/description (fallback for screen readers) */}
            <h1 id={`file-stats-title-${versionId}`} className="sr-only">
                File Statistics
            </h1>
            <p id={`file-stats-desc-${versionId}`} className="sr-only">
                Per-category and per-type file counts and sizes for this
                version.
            </p>

            <div className="mb-4 flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    File Statistics
                </h2>
                <button
                    onClick={() => closeFileStatsDialog(versionId)}
                    className="text-gray-400 hover:text-gray-500"
                    aria-label="Close dialog"
                >
                    <svg
                        className="h-6 w-6"
                        fill="none"
                        viewBox="0 0 24 24"
                        strokeWidth="1.5"
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
            <div className="max-h-[60vh] overflow-y-auto">
                {showFileStats === versionId &&
                fileStatsData?.file_categories &&
                fileStatsData.file_categories.length > 0 ? (
                    <div className="space-y-6">
                        {/* Summary */}
                        <div>
                            <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                Version {fileStatsData.version?.version}
                            </h3>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                {fileStatsData.file_categories.map(
                                    (category, index) => (
                                        <div
                                            key={index}
                                            className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50"
                                        >
                                            <div className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                {category.category
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                    category.category.slice(1)}
                                            </div>
                                            <div
                                                className="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                                {category.total_count.toLocaleString()}
                                            </div>
                                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                                {formatBytes(category.total_size)}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        {/* Detailed Breakdown */}
                        <div className="space-y-6">
                            {fileStatsData.file_categories
                                .filter((category) => category.total_count > 0)
                                .map((category, index: number) => (
                                    <div key={index}>
                                        <h4 className="mb-2 text-base font-medium text-gray-900 dark:text-gray-100">
                                            {category.category
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                category.category.slice(1)}{' '}
                                            Files
                                        </h4>
                                        <div className="overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                                <thead>
                                                <tr>
                                                    <th className="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                        Count
                                                    </th>
                                                    <th className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                        Size
                                                    </th>
                                                </tr>
                                                </thead>
                                                <tbody className="divide-y divide-gray-200 dark:divide-gray-600">
                                                {category.file_types.map(
                                                    (
                                                        fileType,
                                                        typeIndex: number,
                                                    ) => (
                                                        <tr key={typeIndex}>
                                                            <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                {
                                                                    fileType.extension
                                                                }
                                                            </td>
                                                            <td className="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                                                                {fileType.count.toLocaleString()}
                                                            </td>
                                                            <td className="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100">
                                                                {formatBytes(fileType.size)}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ))}
                        </div>
                    </div>
                ) : (
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        {statsLoading ? (
                            <div className="flex flex-col items-center gap-3">
                                <LoadingSpinner size="lg"/>
                                <span>Loading file statistics...</span>
                            </div>
                        ) : (
                            'No file statistics available for this version.'
                        )}
                    </div>
                )}
            </div>
            <div className="mt-6 flex justify-end">
                <button
                    onClick={() => closeFileStatsDialog(versionId)}
                    type="button"
                    className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
                >
                    Close
                </button>
            </div>
        </dialog>
    );
}
