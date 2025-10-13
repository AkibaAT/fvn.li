import LoadingSpinner from '@/components/loading-spinner';
import React from 'react';

interface CharacterStatsModalProps {
    versionId: number;
    showCharacterStats: number | null;
    characterStatsData: {
        characters?: string[];
        languages?: Array<{ id: string; flag: string; name: string }>;
        wordCounts?: Record<string, Record<string, number>>;
        languageTotals?: Record<string, number>;
    } | null;
    statsLoading: boolean;
    closeCharacterStatsDialog: (versionId: number) => void;
    getLanguageFlag: (flag: string) => string;
}

export default function CharacterStatsModal({
                                                versionId,
                                                showCharacterStats,
                                                characterStatsData,
                                                statsLoading,
                                                closeCharacterStatsDialog,
                                                getLanguageFlag,
                                            }: CharacterStatsModalProps) {
    const closeBtnRef = React.useRef<HTMLButtonElement>(null);
    const openerRef = React.useRef<HTMLElement | null>(null);

    // Manage initial focus and restore focus to invoker
    React.useEffect(() => {
        const dialogEl = document.getElementById(
            `character-stats-${versionId}`,
        ) as HTMLDialogElement | null;
        if (!dialogEl) return;

        const handleOpen = () => {
            openerRef.current = (document.activeElement as HTMLElement) || null;
            // Defer to next frame to ensure dialog is fully in the top layer
            requestAnimationFrame(() => {
                closeBtnRef.current?.focus();
            });
        };

        const handleClose = () => {
            // Restore focus to the original trigger if still in the DOM
            openerRef.current?.focus?.();
            openerRef.current = null;
        };

        dialogEl.addEventListener('close', handleClose);
        // When this component's content becomes visible (showCharacterStats matches), set focus
        if (showCharacterStats === versionId && dialogEl.open) {
            handleOpen();
        }

        return () => {
            dialogEl.removeEventListener('close', handleClose);
        };
    }, [showCharacterStats, versionId]);

    return (
        <dialog
            id={`character-stats-${versionId}`}
            role="dialog"
            aria-modal="true"
            aria-labelledby={`character-stats-title-${versionId}`}
            aria-describedby={`character-stats-desc-${versionId}`}
            className="m-auto max-w-6xl min-w-80 rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
        >
            {/* Accessible name/description (fallback for screen readers) */}
            <h1 id={`character-stats-title-${versionId}`} className="sr-only">
                Character Statistics
            </h1>
            <p id={`character-stats-desc-${versionId}`} className="sr-only">
                Per-character word counts by language with totals.
            </p>

            <div className="mb-4 flex items-center justify-between border-b border-gray-200 pb-4 dark:border-gray-700">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Character Statistics
                </h2>
                <button
                    ref={closeBtnRef}
                    onClick={() => closeCharacterStatsDialog(versionId)}
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
                {showCharacterStats === versionId &&
                characterStatsData?.characters &&
                characterStatsData.characters.length > 0 ? (
                    <div className="overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                            <thead>
                            <tr>
                                <th className="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                    Character
                                </th>
                                {characterStatsData.languages?.map(
                                    (lang) => (
                                        <th
                                            key={lang.id}
                                            className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            <div className="flex items-center justify-end gap-2">
                                                <img
                                                    src={getLanguageFlag(
                                                        lang.flag,
                                                    )}
                                                    alt={lang.name}
                                                    className="h-4 w-4 rounded-sm"
                                                />
                                                <span>{lang.name}</span>
                                            </div>
                                        </th>
                                    ),
                                )}
                            </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-600">
                            {characterStatsData.characters?.map(
                                (character: string) => (
                                    <tr key={character}>
                                        <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            {character}
                                        </td>
                                        {characterStatsData.languages?.map(
                                            (lang) => (
                                                <td
                                                    key={lang.id}
                                                    className="px-4 py-2 text-right text-sm text-gray-900 dark:text-gray-100"
                                                >
                                                    {characterStatsData
                                                        .wordCounts?.[
                                                        character
                                                        ]?.[lang.id]
                                                        ? characterStatsData.wordCounts[
                                                            character
                                                            ][
                                                            lang.id
                                                            ].toLocaleString()
                                                        : '-'}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                ),
                            )}
                            </tbody>
                            <tfoot>
                            <tr className="bg-gray-50 dark:bg-gray-700/50">
                                <td className="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Total
                                </td>
                                {characterStatsData.languages?.map(
                                    (lang) => (
                                        <td
                                            key={lang.id}
                                            className="px-4 py-2 text-right text-sm font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {characterStatsData.languageTotals?.[
                                                lang.id
                                                ]?.toLocaleString() || '0'}
                                        </td>
                                    ),
                                )}
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                ) : (
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        {statsLoading ? (
                            <div className="flex flex-col items-center gap-3">
                                <LoadingSpinner size="lg"/>
                                <span>Loading character statistics...</span>
                            </div>
                        ) : (
                            'No character statistics available for this version.'
                        )}
                    </div>
                )}
            </div>
            <div className="mt-6 flex justify-end">
                <button
                    onClick={() => closeCharacterStatsDialog(versionId)}
                    type="button"
                    className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600 dark:hover:bg-gray-700"
                >
                    Close
                </button>
            </div>
        </dialog>
    );
}
