import React, { useEffect, useRef, useState } from 'react';
import { useVersionComparison, VersionComparisonData } from '@/hooks/api';

// Utility function to format bytes
const formatBytes = (bytes: number): string => {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

// Utility function to format numbers
const formatNumber = (num: number): string => {
  return num === 0 ? '-' : num.toLocaleString();
};

interface VersionComparisonModalProps {
  isOpen: boolean;
  onClose: () => void;
  gameId: number;
  fromVersionId?: number;
  toVersionId?: number;
}

export function VersionComparisonModal({
  isOpen,
  onClose,
  gameId,
  fromVersionId,
  toVersionId,
}: VersionComparisonModalProps) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const closeBtnRef = useRef<HTMLButtonElement>(null);
  const openerRef = useRef<HTMLElement | null>(null);
  const [activeTab, setActiveTab] = useState<'character' | 'file'>('character');

  const {
    data: comparisonData,
    isLoading: loading,
    error,
  } = useVersionComparison(gameId, fromVersionId, toVersionId, {
    enabled: isOpen && !!fromVersionId && !!toVersionId && !!gameId,
  });

  // Manage native dialog open/close + focus
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;

    if (isOpen) {
      openerRef.current = (document.activeElement as HTMLElement) || null;
      if (!dialog.open) dialog.showModal();
      requestAnimationFrame(() => closeBtnRef.current?.focus());
    } else if (dialog.open) {
      dialog.close();
    }
  }, [isOpen]);

  // Restore focus to opener on native close (e.g., Esc)
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    const handleClose = () => {
      openerRef.current?.focus?.();
      openerRef.current = null;
      if (isOpen) onClose();
    };
    dialog.addEventListener('close', handleClose);
    return () => dialog.removeEventListener('close', handleClose);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen]);

  const getDiffColor = (diff: number) => {
    if (diff > 0) return 'text-green-400';
    if (diff < 0) return 'text-red-400';
    return 'text-gray-400';
  };

  const formatDiff = (diff: number) => {
    if (diff === 0) return '-';
    return (diff > 0 ? '+' : '') + formatNumber(diff);
  };

  const formatBytesDiff = (diff: number): string => {
    if (diff === 0) return '-';
    return (diff > 0 ? '+' : '') + formatBytes(Math.abs(diff));
  };

  if (!isOpen) return null;

  return (
    <dialog
      ref={dialogRef}
      role="dialog"
      aria-modal="true"
      aria-labelledby="version-comparison-title"
      aria-describedby="version-comparison-desc"
      className="m-auto max-h-[90vh] w-full max-w-6xl overflow-hidden rounded-lg bg-gray-800 p-0 text-gray-100 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-md"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <h1 id="version-comparison-title" className="sr-only">
        Version Comparison
      </h1>
      <p id="version-comparison-desc" className="sr-only">
        Compare character word counts and file statistics across two versions.
      </p>
      <div className="max-h-[90vh] w-full overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-700 p-6">
          <h2 className="text-xl font-semibold">Version Comparison</h2>
          <button
            ref={closeBtnRef}
            onClick={onClose}
            className="text-gray-400 hover:text-gray-100 focus:outline-none"
            aria-label="Close dialog"
          >
            <svg
              className="h-6 w-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>

        {/* Content */}
        <div className="max-h-[calc(90vh-8rem)] overflow-y-auto p-6">
          {loading && (
            <div className="flex items-center justify-center p-8">
              <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-gray-100"></div>
            </div>
          )}

          {error && (
            <div className="p-4 text-center text-red-400">
              Failed to load comparison data. Please try again.
            </div>
          )}

          {comparisonData && (
            <>
              {/* Version Info */}
              <div className="mb-6">
                <div className="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-700/50 p-4 md:flex-row">
                  <div>
                    <h3 className="text-sm font-medium text-gray-400">Comparing</h3>
                    <div className="mt-1 flex items-center gap-2">
                      <div className="font-medium text-gray-100">
                        Version {comparisonData.fromVersion.version}
                        <span className="text-sm text-gray-400">
                          ({new Date(comparisonData.fromVersion.published_at).toLocaleDateString()})
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
                        Version {comparisonData.toVersion.version}
                        <span className="text-sm text-gray-400">
                          ({new Date(comparisonData.toVersion.published_at).toLocaleDateString()})
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Tabs */}
              <div className="mb-8">
                <ul className="flex border-b border-gray-700 text-sm" role="tablist">
                  <li className="mr-1">
                    <button
                      className={`border-b-2 px-4 py-2 focus:outline-none ${
                        activeTab === 'character'
                          ? 'border-blue-400 text-blue-400'
                          : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'
                      }`}
                      onClick={() => setActiveTab('character')}
                    >
                      Character Stats
                    </button>
                  </li>
                  <li className="mr-1">
                    <button
                      className={`border-b-2 px-4 py-2 focus:outline-none ${
                        activeTab === 'file'
                          ? 'border-blue-400 text-blue-400'
                          : 'border-transparent text-gray-400 hover:border-gray-600 hover:text-gray-100'
                      }`}
                      onClick={() => setActiveTab('file')}
                    >
                      File Stats
                    </button>
                  </li>
                </ul>

                {/* Character Stats Tab */}
                {activeTab === 'character' && (
                  <CharacterStatsTab
                    comparisonData={comparisonData}
                    formatNumber={formatNumber}
                    formatDiff={formatDiff}
                    getDiffColor={getDiffColor}
                  />
                )}

                {/* File Stats Tab */}
                {activeTab === 'file' && (
                  <FileStatsTab
                    comparisonData={comparisonData}
                    formatNumber={formatNumber}
                    formatBytes={formatBytes}
                    formatDiff={formatDiff}
                    formatBytesDiff={formatBytesDiff}
                    getDiffColor={getDiffColor}
                  />
                )}
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="flex justify-end border-t border-gray-700 p-6">
          <button
            onClick={onClose}
            className="rounded bg-gray-600 px-4 py-2 text-gray-100 hover:bg-gray-500 focus:ring-2 focus:ring-gray-500 focus:outline-none"
          >
            Close
          </button>
        </div>
      </div>
    </dialog>
  );
}

// Extracted Character Stats Tab component
function CharacterStatsTab({
  comparisonData,
  formatNumber,
  formatDiff,
  getDiffColor,
}: {
  comparisonData: VersionComparisonData;
  formatNumber: (num: number) => string;
  formatDiff: (diff: number) => string;
  getDiffColor: (diff: number) => string;
}) {
  return (
    <div className="pt-4">
      <div className="overflow-hidden rounded-lg bg-gray-700/50">
        <table className="min-w-full divide-y divide-gray-600 text-sm">
          <thead>
            <tr>
              <th className="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-400 uppercase">
                Character
              </th>
              {comparisonData.languages.map((lang, index) => (
                <React.Fragment key={lang.id}>
                  {index > 0 && (
                    <th className="m-0 w-px bg-gray-600 p-0">
                      <div className="h-full w-px">&nbsp;</div>
                    </th>
                  )}
                  <th
                    className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-400 uppercase"
                    colSpan={3}
                  >
                    <div className="flex items-center justify-end gap-2">
                      <span className={`fi fi-${lang.flag} rounded-xs`}></span>
                      <span>{lang.name}</span>
                    </div>
                  </th>
                </React.Fragment>
              ))}
            </tr>
            <tr className="border-b border-gray-600 text-xs text-gray-400">
              <th className="px-4 py-1 text-left"></th>
              {comparisonData.languages.map((lang, index) => (
                <React.Fragment key={lang.id}>
                  {index > 0 && (
                    <th className="m-0 w-px bg-gray-600 p-0">
                      <div className="h-full w-px">&nbsp;</div>
                    </th>
                  )}
                  <th className="px-2 py-1 text-right">Old</th>
                  <th className="px-2 py-1 text-right">New</th>
                  <th className="px-2 py-1 text-right">Diff</th>
                </React.Fragment>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-600">
            {comparisonData.characters.map((character) => (
              <tr key={character} className="hover:bg-gray-700/50">
                <td className="px-4 py-2 text-sm text-gray-100">{character}</td>
                {comparisonData.languages.map((lang, index) => {
                  const stats = comparisonData.characterDiffs[character]?.[lang.id];
                  const fromCount = stats?.from || 0;
                  const toCount = stats?.to || 0;
                  const diff = stats?.diff || 0;

                  return (
                    <React.Fragment key={lang.id}>
                      {index > 0 && (
                        <td className="m-0 w-px bg-gray-600 p-0">
                          <div className="h-full w-px">&nbsp;</div>
                        </td>
                      )}
                      <td className="px-2 py-2 text-right text-sm text-gray-400 tabular-nums">
                        {formatNumber(fromCount)}
                      </td>
                      <td className="px-2 py-2 text-right text-sm text-gray-100 tabular-nums">
                        {formatNumber(toCount)}
                      </td>
                      <td className={`px-2 py-2 text-right text-sm tabular-nums ${getDiffColor(diff)}`}>
                        {formatDiff(diff)}
                      </td>
                    </React.Fragment>
                  );
                })}
              </tr>
            ))}
          </tbody>
          <tfoot className="border-t border-gray-600 font-medium">
            <tr>
              <td className="px-4 py-2 text-sm text-gray-100">Total</td>
              {comparisonData.languages.map((lang, index) => {
                const fromTotal = comparisonData.languageTotals.from[lang.id] || 0;
                const toTotal = comparisonData.languageTotals.to[lang.id] || 0;
                const diffTotal = comparisonData.languageTotals.diff[lang.id] || 0;

                return (
                  <React.Fragment key={lang.id}>
                    {index > 0 && (
                      <td className="m-0 w-px bg-gray-600 p-0">
                        <div className="h-full w-px">&nbsp;</div>
                      </td>
                    )}
                    <td className="px-2 py-2 text-right text-sm text-gray-400 tabular-nums">
                      {formatNumber(fromTotal)}
                    </td>
                    <td className="px-2 py-2 text-right text-sm text-gray-100 tabular-nums">
                      {formatNumber(toTotal)}
                    </td>
                    <td className={`px-2 py-2 text-right text-sm tabular-nums ${getDiffColor(diffTotal)}`}>
                      {formatDiff(diffTotal)}
                    </td>
                  </React.Fragment>
                );
              })}
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
}

// Extracted File Stats Tab component
function FileStatsTab({
  comparisonData,
  formatNumber,
  formatBytes,
  formatDiff,
  formatBytesDiff,
  getDiffColor,
}: {
  comparisonData: VersionComparisonData;
  formatNumber: (num: number) => string;
  formatBytes: (bytes: number) => string;
  formatDiff: (diff: number) => string;
  formatBytesDiff: (diff: number) => string;
  getDiffColor: (diff: number) => string;
}) {
  return (
    <div className="space-y-6 pt-4">
      {/* Summary */}
      <div>
        <h3 className="mb-4 text-lg font-medium text-gray-100">File Summary</h3>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          {comparisonData.fileCategories.map((category) => (
            <div key={category.category} className="rounded-lg bg-gray-700/50 p-4">
              <div className="text-sm font-medium text-gray-400">
                {category.category.charAt(0).toUpperCase() + category.category.slice(1)}
              </div>
              <div className="mt-1 flex items-baseline">
                <div className="text-sm text-gray-400">{formatNumber(category.from.count)}</div>
                <div className="mx-1 text-gray-500">→</div>
                <div className="text-base font-semibold text-gray-100">
                  {formatNumber(category.to.count)}
                </div>
                {category.diff.count !== 0 && (
                  <div className={`ml-2 text-sm ${getDiffColor(category.diff.count)}`}>
                    {formatDiff(category.diff.count)}
                  </div>
                )}
              </div>
              <div className="mt-1 flex items-baseline text-sm">
                <div className="text-gray-400">{formatBytes(category.from.size)}</div>
                <div className="mx-1 text-gray-500">→</div>
                <div className="text-gray-100">{formatBytes(category.to.size)}</div>
                {category.diff.size !== 0 && (
                  <div className={`ml-2 ${getDiffColor(category.diff.size)}`}>
                    {formatBytesDiff(category.diff.size)}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Detailed Breakdown */}
      <div className="space-y-6">
        {comparisonData.fileCategories.map(
          (category) =>
            Object.keys(category.fileTypes).length > 0 && (
              <div key={category.category}>
                <h4 className="mb-2 text-base font-medium text-gray-100">
                  {category.category.charAt(0).toUpperCase() + category.category.slice(1)} Files
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
                        <th className="px-2 py-1 text-right">Old</th>
                        <th className="px-2 py-1 text-right">New</th>
                        <th className="px-2 py-1 text-right">Diff</th>
                        <th className="px-2 py-1 text-right">Old</th>
                        <th className="px-2 py-1 text-right">New</th>
                        <th className="px-2 py-1 text-right">Diff</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-600">
                      {Object.entries(category.fileTypes).map(([extension, typeStats]) => (
                        <tr key={extension}>
                          <td className="px-4 py-2 text-sm text-gray-100">{extension}</td>
                          <td className="px-2 py-2 text-right text-sm text-gray-400">
                            {formatNumber(typeStats.from.count)}
                          </td>
                          <td className="px-2 py-2 text-right text-sm text-gray-100">
                            {formatNumber(typeStats.to.count)}
                          </td>
                          <td
                            className={`px-2 py-2 text-right text-sm ${getDiffColor(typeStats.diff.count)}`}
                          >
                            {formatDiff(typeStats.diff.count)}
                          </td>
                          <td className="px-2 py-2 text-right text-sm text-gray-400">
                            {formatBytes(typeStats.from.size)}
                          </td>
                          <td className="px-2 py-2 text-right text-sm text-gray-100">
                            {formatBytes(typeStats.to.size)}
                          </td>
                          <td
                            className={`px-2 py-2 text-right text-sm ${getDiffColor(typeStats.diff.size)}`}
                          >
                            {formatBytesDiff(typeStats.diff.size)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )
        )}
      </div>
    </div>
  );
}
