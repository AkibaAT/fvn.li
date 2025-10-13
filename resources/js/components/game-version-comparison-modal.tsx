import LoadingSpinner from '@/components/loading-spinner';
import React from 'react';

interface GameVersionComparisonModalProps {
    showVersionComparison: boolean;
    versionComparisonData: {
        fromVersion: { version: string; published_at: string };
        toVersion: { version: string; published_at: string };
        languages: Array<{ id: string; flag: string; name: string }>;
        characters: string[];
        characterDiffs: Record<
            string,
            Record<string, { from: number; to: number; diff: number }>
        >;
        languageTotals: {
            from: Record<string, number>;
            to: Record<string, number>;
            diff: Record<string, number>;
        };
        fileCategories?: Array<{
            category: string;
            from: { count: number; size: number };
            to: { count: number; size: number };
            diff: { count: number; size: number };
            fileTypes?: Record<
                string,
                {
                    from: { count: number; size: number };
                    to: { count: number; size: number };
                    diff: { count: number; size: number };
                }
            >;
        }>;
    } | null;
    isLoadingComparison: boolean;
    activeComparisonTab: 'character' | 'file';
    setActiveComparisonTab: (tab: 'character' | 'file') => void;
    closeVersionComparisonDialog: () => void;
    formatBytes: (bytes: number) => string;
}

export default function GameVersionComparisonModal({
                                                       showVersionComparison,
                                                       versionComparisonData,
                                                       isLoadingComparison,
                                                       activeComparisonTab,
                                                       setActiveComparisonTab,
                                                       closeVersionComparisonDialog,
                                                       formatBytes,
                                                   }: GameVersionComparisonModalProps) {
    const closeBtnRef = React.useRef<HTMLButtonElement>(null);
    const openerRef = React.useRef<HTMLElement | null>(null);

    // Manage initial focus and restore focus to invoker
    React.useEffect(() => {
        const dialogEl = document.getElementById(
            'version-comparison-dialog',
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
        if (showVersionComparison && dialogEl.open) {
            handleOpen();
        }

        return () => {
            dialogEl.removeEventListener('close', handleClose);
        };
    }, [showVersionComparison]);

    if (!showVersionComparison) {
        return null;
    }

    return (
        <dialog
            id="version-comparison-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="version-comparison-title"
            aria-describedby="version-comparison-desc"
            className="m-auto max-w-6xl min-w-80 rounded-lg bg-gray-800 p-6 text-gray-100 shadow-xl backdrop:backdrop-blur-md"
            onClick={(e) => {
                const rect = (e.target as HTMLElement).getBoundingClientRect();
                if (
                    e.clientX < rect.left ||
                    e.clientX > rect.right ||
                    e.clientY < rect.top ||
                    e.clientY > rect.bottom
                ) {
                    closeVersionComparisonDialog();
                }
            }}
        >
            {/* Accessible name/description */}
            <h1 id="version-comparison-title" className="sr-only">
                Version Comparison
            </h1>
            <p id="version-comparison-desc" className="sr-only">
                Compare character word counts and file statistics across two
                versions.
            </p>

            <div className="mb-4 flex items-center justify-between border-b border-gray-700 pb-4">
                <h2 className="text-2xl font-bold text-gray-100">
                    Version Comparison
                </h2>
                <button
                    ref={closeBtnRef}
                    onClick={closeVersionComparisonDialog}
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

            {isLoadingComparison ? (
                <div className="flex flex-col items-center justify-center gap-4 py-12">
                    <LoadingSpinner size="lg"/>
                    <div className="text-center">
                        <div className="mb-2 text-lg font-medium text-gray-100">
                            Comparing Versions
                        </div>
                        <div className="text-sm text-gray-400">
                            Analyzing character and file differences...
                        </div>
                    </div>
                </div>
            ) : versionComparisonData ? (
                <div>
                    <div className="mb-4">
                        <div
                            className="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-700/50 p-4 md:flex-row">
                            <div>
                                <h3 className="text-sm font-medium text-gray-400">
                                    Comparing
                                </h3>
                                <div className="mt-1 flex items-center gap-2">
                                    <div className="font-medium text-gray-100">
                                        Version{' '}
                                        {
                                            versionComparisonData.fromVersion
                                                .version
                                        }
                                        <span className="text-sm text-gray-400">
                                            (
                                            {new Date(
                                                versionComparisonData.fromVersion.published_at,
                                            ).toLocaleDateString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                            })}
                                            )
                                        </span>
                                    </div>
                                    <svg
                                        className="h-4 w-4 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3"
                                        />
                                    </svg>
                                    <div className="font-medium text-gray-100">
                                        Version{' '}
                                        {
                                            versionComparisonData.toVersion
                                                .version
                                        }
                                        <span className="text-sm text-gray-400">
                                            (
                                            {new Date(
                                                versionComparisonData.toVersion.published_at,
                                            ).toLocaleDateString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                            })}
                                            )
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tab Navigation */}
                    <div className="mb-8">
                        <ul
                            className="flex border-b border-gray-700 text-sm"
                            role="tablist"
                        >
                            <li className="mr-1">
                                <button
                                    className={`border-b-2 px-4 py-2 focus:outline-none ${
                                        activeComparisonTab === 'character'
                                            ? 'border-blue-400 text-blue-400'
                                            : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'
                                    }`}
                                    role="tab"
                                    onClick={() =>
                                        setActiveComparisonTab('character')
                                    }
                                >
                                    Character Stats
                                </button>
                            </li>
                            <li className="mr-1">
                                <button
                                    className={`border-b-2 px-4 py-2 focus:outline-none ${
                                        activeComparisonTab === 'file'
                                            ? 'border-blue-400 text-blue-400'
                                            : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'
                                    }`}
                                    role="tab"
                                    onClick={() =>
                                        setActiveComparisonTab('file')
                                    }
                                >
                                    File Stats
                                </button>
                            </li>
                        </ul>
                    </div>

                    {/* Character Stats Tab */}
                    {activeComparisonTab === 'character' && (
                        <div className="pt-4">
                            <div className="-mx-6 max-w-[calc(100vw-3rem)] overflow-x-auto px-6">
                                <table className="w-full text-sm">
                                    <thead>
                                    <tr className="border-b border-gray-700">
                                        <th className="px-2 py-2 text-left font-medium">
                                            Character
                                        </th>
                                        {versionComparisonData?.languages?.map(
                                            (lang, index: number) => (
                                                <React.Fragment
                                                    key={lang.id}
                                                >
                                                    {index > 0 && (
                                                        <th className="m-0 w-px bg-gray-600 p-0">
                                                            <div className="h-full w-px">
                                                                &nbsp;
                                                            </div>
                                                        </th>
                                                    )}
                                                    <th
                                                        className="px-2 py-2 text-right font-medium"
                                                        colSpan={3}
                                                    >
                                                        <div className="flex items-center justify-end gap-2">
                                                                <span
                                                                    className={`fi fi-${lang.flag} rounded-xs`}
                                                                ></span>
                                                            <span>
                                                                    {lang.name}
                                                                </span>
                                                        </div>
                                                    </th>
                                                </React.Fragment>
                                            ),
                                        )}
                                    </tr>
                                    <tr className="border-b border-gray-700 text-xs text-gray-400">
                                        <th className="px-2 py-2 text-left"></th>
                                        {versionComparisonData?.languages?.map(
                                            (lang, index: number) => (
                                                <React.Fragment
                                                    key={`header-${lang.id}`}
                                                >
                                                    {index > 0 && (
                                                        <th className="m-0 w-px bg-gray-600 p-0">
                                                            <div className="h-full w-px">
                                                                &nbsp;
                                                            </div>
                                                        </th>
                                                    )}
                                                    <th className="px-2 py-2 text-right">
                                                        Old
                                                    </th>
                                                    <th className="px-2 py-2 text-right">
                                                        New
                                                    </th>
                                                    <th className="px-2 py-2 text-right">
                                                        Diff
                                                    </th>
                                                </React.Fragment>
                                            ),
                                        )}
                                    </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-700">
                                    {versionComparisonData?.characters?.map(
                                        (character: string) => (
                                            <tr
                                                key={character}
                                                className="hover:bg-gray-700/50"
                                            >
                                                <td className="px-2 py-2">
                                                    {character}
                                                </td>
                                                {versionComparisonData?.languages?.map(
                                                    (
                                                        lang,
                                                        index: number,
                                                    ) => {
                                                        const stats =
                                                            versionComparisonData
                                                                ?.characterDiffs?.[
                                                                character
                                                                ]?.[lang.id] ||
                                                            null;
                                                        const fromCount =
                                                            stats
                                                                ? stats.from
                                                                : 0;
                                                        const toCount =
                                                            stats
                                                                ? stats.to
                                                                : 0;
                                                        const diff = stats
                                                            ? stats.diff
                                                            : 0;

                                                        return (
                                                            <React.Fragment
                                                                key={`${character}-${lang.id}`}
                                                            >
                                                                {index >
                                                                    0 && (
                                                                        <td className="m-0 w-px bg-gray-600 p-0">
                                                                            <div className="h-full w-px">
                                                                                &nbsp;
                                                                            </div>
                                                                        </td>
                                                                    )}
                                                                <td className="px-2 py-2 text-right text-gray-400 tabular-nums">
                                                                    {fromCount
                                                                        ? fromCount.toLocaleString()
                                                                        : '-'}
                                                                </td>
                                                                <td className="px-2 py-2 text-right tabular-nums">
                                                                    {toCount
                                                                        ? toCount.toLocaleString()
                                                                        : '-'}
                                                                </td>
                                                                <td
                                                                    className={`px-2 py-2 text-right tabular-nums ${diff > 0 ? 'text-green-400' : diff < 0 ? 'text-red-400' : 'text-gray-400'}`}
                                                                >
                                                                    {diff !==
                                                                    0
                                                                        ? `${diff > 0 ? '+' : ''}${diff.toLocaleString()}`
                                                                        : '-'}
                                                                </td>
                                                            </React.Fragment>
                                                        );
                                                    },
                                                )}
                                            </tr>
                                        ),
                                    )}
                                    </tbody>
                                    <tfoot className="border-t border-gray-700 font-medium">
                                    <tr>
                                        <td className="px-2 py-2">Total</td>
                                        {versionComparisonData?.languages?.map(
                                            (lang, index: number) => {
                                                const fromTotal =
                                                    versionComparisonData
                                                        ?.languageTotals
                                                        ?.from?.[lang.id] || 0;
                                                const toTotal =
                                                    versionComparisonData
                                                        ?.languageTotals?.to?.[
                                                        lang.id
                                                        ] || 0;
                                                const diffTotal =
                                                    versionComparisonData
                                                        ?.languageTotals
                                                        ?.diff?.[lang.id] || 0;

                                                return (
                                                    <React.Fragment
                                                        key={`total-${lang.id}`}
                                                    >
                                                        {index > 0 && (
                                                            <td className="m-0 w-px bg-gray-600 p-0">
                                                                <div className="h-full w-px">
                                                                    &nbsp;
                                                                </div>
                                                            </td>
                                                        )}
                                                        <td className="px-2 py-2 text-right text-gray-400 tabular-nums">
                                                            {fromTotal
                                                                ? fromTotal.toLocaleString()
                                                                : '-'}
                                                        </td>
                                                        <td className="px-2 py-2 text-right tabular-nums">
                                                            {toTotal
                                                                ? toTotal.toLocaleString()
                                                                : '-'}
                                                        </td>
                                                        <td
                                                            className={`px-2 py-2 text-right tabular-nums ${diffTotal > 0 ? 'text-green-400' : diffTotal < 0 ? 'text-red-400' : 'text-gray-400'}`}
                                                        >
                                                            {diffTotal !== 0
                                                                ? `${diffTotal > 0 ? '+' : ''}${diffTotal.toLocaleString()}`
                                                                : '-'}
                                                        </td>
                                                    </React.Fragment>
                                                );
                                            },
                                        )}
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* File Stats Tab */}
                    {activeComparisonTab === 'file' && (
                        <div className="space-y-6 pt-4">
                            {/* Summary */}
                            <div>
                                <h3 className="mb-4 text-lg font-medium text-gray-100">
                                    File Summary
                                </h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    {versionComparisonData.fileCategories?.map(
                                        (category) => (
                                            <div
                                                key={category.category}
                                                className="rounded-lg bg-gray-700/50 p-4"
                                            >
                                                <div className="text-sm font-medium text-gray-400">
                                                    {category.category
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                        category.category.slice(
                                                            1,
                                                        )}
                                                </div>
                                                <div className="mt-1 flex items-baseline">
                                                    <div className="text-sm text-gray-400">
                                                        {(category.from?.count ?? 0)
                                                            ? (category.from?.count ?? 0).toLocaleString()
                                                            : '-'}
                                                    </div>
                                                    <div className="mx-1 text-gray-500">
                                                        →
                                                    </div>
                                                    <div className="text-base font-semibold text-gray-100">
                                                        {(category.to?.count ?? 0)
                                                            ? (category.to?.count ?? 0).toLocaleString()
                                                            : '-'}
                                                    </div>
                                                    <div
                                                        className={`ml-2 text-sm ${(category.diff?.count ?? 0) > 0 ? 'text-green-400' : (category.diff?.count ?? 0) < 0 ? 'text-red-400' : 'text-gray-400'}`}
                                                    >
                                                        {(category.diff?.count ?? 0) !== 0
                                                            ? `${(category.diff?.count ?? 0) > 0 ? '+' : ''}${(category.diff?.count ?? 0).toLocaleString()}`
                                                            : ''}
                                                    </div>
                                                </div>
                                                <div className="mt-1 text-xs text-gray-500">
                                                    {formatBytes(
                                                        category.from?.size ?? 0,
                                                    )}{' '}
                                                    →{' '}
                                                    {formatBytes(
                                                        category.to?.size ?? 0,
                                                    )}
                                                    {(category.diff?.size ?? 0) !==
                                                        0 && (
                                                            <span
                                                                className={`ml-1 ${(category.diff?.size ?? 0) > 0 ? 'text-green-400' : 'text-red-400'}`}
                                                            >
                                                            (
                                                                {(category.diff?.size ?? 0) > 0
                                                                    ? '+'
                                                                    : ''}
                                                                {formatBytes(
                                                                    category.diff?.size ?? 0,
                                                                )}
                                                                )
                                                        </span>
                                                        )}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>

                            {/* Detailed Breakdown */}
                            <div className="space-y-6">
                                {versionComparisonData.fileCategories?.map(
                                    (category) => {
                                        const hasFileTypes =
                                            category.fileTypes &&
                                            Object.keys(category.fileTypes)
                                                .length > 0;
                                        if (!hasFileTypes) return null;

                                        return (
                                            <div key={category.category}>
                                                <h4 className="mb-2 text-base font-medium text-gray-100">
                                                    {category.category
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                        category.category.slice(
                                                            1,
                                                        )}{' '}
                                                    Files
                                                </h4>
                                                <div className="overflow-hidden rounded-lg bg-gray-700/50">
                                                    <table className="min-w-full divide-y divide-gray-600">
                                                        <thead>
                                                        <tr>
                                                            <th className="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-400 uppercase">
                                                                Type
                                                            </th>
                                                            <th
                                                                className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase"
                                                                colSpan={3}
                                                            >
                                                                Count
                                                            </th>
                                                            <th
                                                                className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase"
                                                                colSpan={3}
                                                            >
                                                                Size
                                                            </th>
                                                        </tr>
                                                        <tr className="border-b border-gray-700 text-xs text-gray-400">
                                                            <th className="px-4 py-1 text-left"></th>
                                                            <th className="px-2 py-1 text-right">
                                                                Old
                                                            </th>
                                                            <th className="px-2 py-1 text-right">
                                                                New
                                                            </th>
                                                            <th className="px-2 py-1 text-right">
                                                                Diff
                                                            </th>
                                                            <th className="px-2 py-1 text-right">
                                                                Old
                                                            </th>
                                                            <th className="px-2 py-1 text-right">
                                                                New
                                                            </th>
                                                            <th className="px-2 py-1 text-right">
                                                                Diff
                                                            </th>
                                                        </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-gray-600">
                                                        {category.fileTypes &&
                                                            Object.entries(
                                                                category.fileTypes,
                                                            ).map(
                                                                ([
                                                                     extension,
                                                                     typeStats,
                                                                 ]) => (
                                                                    <tr
                                                                        key={
                                                                            extension
                                                                        }
                                                                    >
                                                                        <td className="px-4 py-2 text-sm text-gray-100">
                                                                            {
                                                                                extension
                                                                            }
                                                                        </td>
                                                                        {/* Count */}
                                                                        <td className="px-2 py-2 text-right text-sm text-gray-400">
                                                                            {(typeStats?.from?.count ?? 0).toLocaleString()}
                                                                        </td>
                                                                        <td className="px-2 py-2 text-right text-sm text-gray-100">
                                                                            {(typeStats?.to?.count ?? 0).toLocaleString()}
                                                                        </td>
                                                                        <td
                                                                            className={`px-2 py-2 text-right text-sm ${(typeStats?.diff?.count ?? 0) > 0 ? 'text-green-400' : (typeStats?.diff?.count ?? 0) < 0 ? 'text-red-400' : 'text-gray-400'}`}
                                                                        >
                                                                            {(typeStats?.diff?.count ?? 0) !==
                                                                            0
                                                                                ? `${(typeStats?.diff?.count ?? 0) > 0 ? '+' : ''}${(typeStats?.diff?.count ?? 0).toLocaleString()}`
                                                                                : '-'}
                                                                        </td>
                                                                        {/* Size */}
                                                                        <td className="px-2 py-2 text-right text-sm text-gray-400">
                                                                            {formatBytes(
                                                                                typeStats?.from?.size ?? 0,
                                                                            )}
                                                                        </td>
                                                                        <td className="px-2 py-2 text-right text-sm text-gray-100">
                                                                            {formatBytes(
                                                                                typeStats?.to?.size ?? 0,
                                                                            )}
                                                                        </td>
                                                                        <td
                                                                            className={`px-2 py-2 text-right text-sm ${(typeStats?.diff?.size ?? 0) > 0 ? 'text-green-400' : (typeStats?.diff?.size ?? 0) < 0 ? 'text-red-400' : 'text-gray-400'}`}
                                                                        >
                                                                            {(typeStats?.diff?.size ?? 0) !==
                                                                            0
                                                                                ? `${(typeStats?.diff?.size ?? 0) > 0 ? '+' : ''}${formatBytes(typeStats?.diff?.size ?? 0)}`
                                                                                : '-'}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        );
                                    },
                                )}
                            </div>
                        </div>
                    )}

                    <div className="mt-6 flex justify-end">
                        <button
                            onClick={closeVersionComparisonDialog}
                            type="button"
                            className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
                        >
                            Close
                        </button>
                    </div>
                </div>
            ) : (
                <div className="py-6 text-center text-gray-500 dark:text-gray-400">
                    No comparison data available.
                </div>
            )}
        </dialog>
    );
}
