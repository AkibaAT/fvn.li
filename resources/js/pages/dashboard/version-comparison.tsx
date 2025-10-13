import {Head, Link} from '@inertiajs/react';
import axios from 'axios';
import React, {useEffect, useState} from 'react';
import {route} from 'ziggy-js';

interface Game {
    id: number;
    title: string;
    slug: string;
    versions: Array<{
        id: number;
        version: string;
        published_at: string;
    }>;
}

interface VersionComparisonData {
    fromVersion: {
        id: number;
        version: string;
        published_at: string;
    };
    toVersion: {
        id: number;
        version: string;
        published_at: string;
    };
    characters: string[];
    languages: Array<{
        id: string;
        name: string;
        flag: string;
    }>;
    characterDiffs: Record<
        string,
        Record<string, { from: number; to: number; diff: number }>
    >;
    languageTotals: {
        from: Record<string, number>;
        to: Record<string, number>;
        diff: Record<string, number>;
    };
    fileCategories: Array<{
        category: string;
        from: { count: number; size: number };
        to: { count: number; size: number };
        diff: { count: number; size: number };
        fileTypes: Record<
            string,
            {
                from: { count: number; size: number };
                to: { count: number; size: number };
                diff: { count: number; size: number };
            }
        >;
    }>;
}

export default function VersionComparison() {
    const [games, setGames] = useState<Game[]>([]);
    const [selectedGame, setSelectedGame] = useState<number | null>(null);
    const [fromVersionId, setFromVersionId] = useState<number | null>(null);
    const [toVersionId, setToVersionId] = useState<number | null>(null);
    const [comparisonData, setComparisonData] =
        useState<VersionComparisonData | null>(null);
    const [loading, setLoading] = useState(false);
    const [loadingGames, setLoadingGames] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<'character' | 'file'>(
        'character',
    );

    // Fetch games with versions
    useEffect(() => {
        const fetchGames = async () => {
            setLoadingGames(true);
            try {
                const response = await axios.get(
                    route('dashboard.version-comparison'),
                );
                setGames(response.data.games);
            } catch (err) {
                console.error('Error fetching games:', err);
                setError('Failed to load games. Please try again.');
            } finally {
                setLoadingGames(false);
            }
        };

        fetchGames();
    }, []);

    // Fetch version comparison data
    const fetchComparisonData = async () => {
        if (!selectedGame || !fromVersionId || !toVersionId) return;

        setLoading(true);
        setError(null);

        try {
            const response = await axios.post(
                route('dashboard.version-comparison'),
                {
                    game_id: selectedGame,
                    from_version_id: fromVersionId,
                    to_version_id: toVersionId,
                },
            );

            setComparisonData(response.data);
        } catch (err) {
            console.error('Error fetching comparison data:', err);
            setError('Failed to load comparison data. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    // Handle game selection
    const handleGameChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const gameId = parseInt(e.target.value);
        setSelectedGame(gameId);
        setFromVersionId(null);
        setToVersionId(null);
        setComparisonData(null);
    };

    // Handle form submission
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        fetchComparisonData();
    };

    // Utility functions
    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const formatNumber = (num: number): string => {
        return num === 0 ? '-' : num.toLocaleString();
    };

    const getDiffColor = (diff: number) => {
        if (diff > 0) return 'text-green-400';
        if (diff < 0) return 'text-red-400';
        return 'text-gray-400';
    };

    const formatDiff = (diff: number) => {
        if (diff === 0) return '-';
        return (diff > 0 ? '+' : '') + formatNumber(diff);
    };

    const selectedGameData = games.find((game) => game.id === selectedGame);

    return (
        <>
            <Head title="Version Comparison"/>

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6">
                            <div className="mb-6 flex items-center justify-between">
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Version Comparison Tool
                                </h1>
                                <Link
                                    href={route('dashboard')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Back to Dashboard
                                </Link>
                            </div>

                            <div className="mb-8">
                                <form
                                    onSubmit={handleSubmit}
                                    className="space-y-4"
                                >
                                    <div>
                                        <label
                                            htmlFor="game"
                                            className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Select Game
                                        </label>
                                        <select
                                            id="game"
                                            value={selectedGame || ''}
                                            onChange={handleGameChange}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            disabled={loadingGames}
                                        >
                                            <option value="">
                                                Choose a game...
                                            </option>
                                            {games.map((game) => (
                                                <option
                                                    key={game.id}
                                                    value={game.id}
                                                >
                                                    {game.title}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {selectedGameData && (
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label
                                                    htmlFor="fromVersion"
                                                    className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >
                                                    From Version
                                                </label>
                                                <select
                                                    id="fromVersion"
                                                    value={fromVersionId || ''}
                                                    onChange={(e) =>
                                                        setFromVersionId(
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                >
                                                    <option value="">
                                                        Select version...
                                                    </option>
                                                    {selectedGameData.versions.map(
                                                        (version) => (
                                                            <option
                                                                key={version.id}
                                                                value={
                                                                    version.id
                                                                }
                                                            >
                                                                {
                                                                    version.version
                                                                }{' '}
                                                                (
                                                                {new Date(
                                                                    version.published_at,
                                                                ).toLocaleDateString()}
                                                                )
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="toVersion"
                                                    className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                                >
                                                    To Version
                                                </label>
                                                <select
                                                    id="toVersion"
                                                    value={toVersionId || ''}
                                                    onChange={(e) =>
                                                        setToVersionId(
                                                            parseInt(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                >
                                                    <option value="">
                                                        Select version...
                                                    </option>
                                                    {selectedGameData.versions.map(
                                                        (version) => (
                                                            <option
                                                                key={version.id}
                                                                value={
                                                                    version.id
                                                                }
                                                            >
                                                                {
                                                                    version.version
                                                                }{' '}
                                                                (
                                                                {new Date(
                                                                    version.published_at,
                                                                ).toLocaleDateString()}
                                                                )
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex justify-end">
                                        <button
                                            type="submit"
                                            disabled={
                                                !selectedGame ||
                                                !fromVersionId ||
                                                !toVersionId ||
                                                loading
                                            }
                                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {loading
                                                ? 'Comparing...'
                                                : 'Compare Versions'}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {error && (
                                <div className="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                                    <div className="flex">
                                        <div className="ml-3">
                                            <h3 className="text-sm font-medium text-red-800 dark:text-red-200">
                                                {error}
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {comparisonData && (
                                <div className="mt-8">
                                    {/* Version Info */}
                                    <div className="mb-6">
                                        <div
                                            className="flex flex-col items-center justify-between gap-4 rounded-lg bg-gray-100 p-4 md:flex-row dark:bg-gray-700/50">
                                            <div>
                                                <h3 className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    Comparing
                                                </h3>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <div className="font-medium text-gray-900 dark:text-white">
                                                        Version{' '}
                                                        {
                                                            comparisonData
                                                                .fromVersion
                                                                .version
                                                        }
                                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                                            (
                                                            {new Date(
                                                                comparisonData.fromVersion.published_at,
                                                            ).toLocaleDateString()}
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
                                                    <div className="font-medium text-gray-900 dark:text-white">
                                                        Version{' '}
                                                        {
                                                            comparisonData
                                                                .toVersion
                                                                .version
                                                        }
                                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                                            (
                                                            {new Date(
                                                                comparisonData.toVersion.published_at,
                                                            ).toLocaleDateString()}
                                                            )
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Tabs */}
                                    <div className="mb-8">
                                        <ul
                                            className="flex border-b border-gray-200 text-sm dark:border-gray-700"
                                            role="tablist"
                                        >
                                            <li className="mr-1">
                                                <button
                                                    className={`border-b-2 px-4 py-2 focus:outline-none ${
                                                        activeTab ===
                                                        'character'
                                                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'
                                                    }`}
                                                    onClick={() =>
                                                        setActiveTab(
                                                            'character',
                                                        )
                                                    }
                                                >
                                                    Character Stats
                                                </button>
                                            </li>
                                            <li className="mr-1">
                                                <button
                                                    className={`border-b-2 px-4 py-2 focus:outline-none ${
                                                        activeTab === 'file'
                                                            ? 'border-indigo-500 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300'
                                                    }`}
                                                    onClick={() =>
                                                        setActiveTab('file')
                                                    }
                                                >
                                                    File Stats
                                                </button>
                                            </li>
                                        </ul>

                                        {/* Character Stats Tab */}
                                        {activeTab === 'character' && (
                                            <div className="pt-4">
                                                <div className="overflow-x-auto">
                                                    <table
                                                        className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                        <thead>
                                                        <tr className="border-b border-gray-200 dark:border-gray-700">
                                                            <th className="px-2 py-2 text-left font-medium text-gray-900 dark:text-white">
                                                                Character
                                                            </th>
                                                            {comparisonData.languages.map(
                                                                (
                                                                    lang,
                                                                    index,
                                                                ) => (
                                                                    <React.Fragment
                                                                        key={
                                                                            lang.id
                                                                        }
                                                                    >
                                                                        {index >
                                                                            0 && (
                                                                                <th className="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600">
                                                                                    <div className="h-full w-px">
                                                                                        &nbsp;
                                                                                    </div>
                                                                                </th>
                                                                            )}
                                                                        <th
                                                                            className="px-2 py-2 text-right font-medium text-gray-900 dark:text-white"
                                                                            colSpan={
                                                                                3
                                                                            }
                                                                        >
                                                                            <div
                                                                                className="flex items-center justify-end gap-2">
                                                                                    <span
                                                                                        className={`fi fi-${lang.flag} rounded-xs`}
                                                                                    ></span>
                                                                                <span>
                                                                                        {
                                                                                            lang.name
                                                                                        }
                                                                                    </span>
                                                                            </div>
                                                                        </th>
                                                                    </React.Fragment>
                                                                ),
                                                            )}
                                                        </tr>
                                                        <tr className="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                            <th className="px-2 py-2 text-left"></th>
                                                            {comparisonData.languages.map(
                                                                (
                                                                    lang,
                                                                    index,
                                                                ) => (
                                                                    <React.Fragment
                                                                        key={
                                                                            lang.id
                                                                        }
                                                                    >
                                                                        {index >
                                                                            0 && (
                                                                                <th className="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600">
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
                                                        <tbody
                                                            className="divide-y divide-gray-200 dark:divide-gray-700">
                                                        {comparisonData.characters.map(
                                                            (character) => (
                                                                <tr
                                                                    key={
                                                                        character
                                                                    }
                                                                    className="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                                                >
                                                                    <td className="px-2 py-2 text-gray-900 dark:text-white">
                                                                        {
                                                                            character
                                                                        }
                                                                    </td>
                                                                    {comparisonData.languages.map(
                                                                        (
                                                                            lang,
                                                                            index,
                                                                        ) => {
                                                                            const stats =
                                                                                comparisonData
                                                                                    .characterDiffs[
                                                                                    character
                                                                                    ]?.[
                                                                                    lang
                                                                                        .id
                                                                                    ];
                                                                            const fromCount =
                                                                                stats?.from ||
                                                                                0;
                                                                            const toCount =
                                                                                stats?.to ||
                                                                                0;
                                                                            const diff =
                                                                                stats?.diff ||
                                                                                0;

                                                                            return (
                                                                                <React.Fragment
                                                                                    key={
                                                                                        lang.id
                                                                                    }
                                                                                >
                                                                                    {index >
                                                                                        0 && (
                                                                                            <td className="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600">
                                                                                                <div
                                                                                                    className="h-full w-px">
                                                                                                    &nbsp;
                                                                                                </div>
                                                                                            </td>
                                                                                        )}
                                                                                    <td className="px-2 py-2 text-right text-gray-500 tabular-nums dark:text-gray-400">
                                                                                        {formatNumber(
                                                                                            fromCount,
                                                                                        )}
                                                                                    </td>
                                                                                    <td className="px-2 py-2 text-right text-gray-900 tabular-nums dark:text-white">
                                                                                        {formatNumber(
                                                                                            toCount,
                                                                                        )}
                                                                                    </td>
                                                                                    <td
                                                                                        className={`px-2 py-2 text-right tabular-nums ${getDiffColor(diff)}`}
                                                                                    >
                                                                                        {formatDiff(
                                                                                            diff,
                                                                                        )}
                                                                                    </td>
                                                                                </React.Fragment>
                                                                            );
                                                                        },
                                                                    )}
                                                                </tr>
                                                            ),
                                                        )}
                                                        </tbody>
                                                        <tfoot
                                                            className="border-t border-gray-200 font-medium dark:border-gray-700">
                                                        <tr>
                                                            <td className="px-2 py-2 text-gray-900 dark:text-white">
                                                                Total
                                                            </td>
                                                            {comparisonData.languages.map(
                                                                (
                                                                    lang,
                                                                    index,
                                                                ) => {
                                                                    const fromTotal =
                                                                        comparisonData
                                                                            .languageTotals
                                                                            .from[
                                                                            lang
                                                                                .id
                                                                            ] ||
                                                                        0;
                                                                    const toTotal =
                                                                        comparisonData
                                                                            .languageTotals
                                                                            .to[
                                                                            lang
                                                                                .id
                                                                            ] ||
                                                                        0;
                                                                    const diffTotal =
                                                                        comparisonData
                                                                            .languageTotals
                                                                            .diff[
                                                                            lang
                                                                                .id
                                                                            ] ||
                                                                        0;

                                                                    return (
                                                                        <React.Fragment
                                                                            key={
                                                                                lang.id
                                                                            }
                                                                        >
                                                                            {index >
                                                                                0 && (
                                                                                    <td className="m-0 w-px bg-gray-200 p-0 dark:bg-gray-600">
                                                                                        <div className="h-full w-px">
                                                                                            &nbsp;
                                                                                        </div>
                                                                                    </td>
                                                                                )}
                                                                            <td className="px-2 py-2 text-right text-gray-500 tabular-nums dark:text-gray-400">
                                                                                {formatNumber(
                                                                                    fromTotal,
                                                                                )}
                                                                            </td>
                                                                            <td className="px-2 py-2 text-right text-gray-900 tabular-nums dark:text-white">
                                                                                {formatNumber(
                                                                                    toTotal,
                                                                                )}
                                                                            </td>
                                                                            <td
                                                                                className={`px-2 py-2 text-right tabular-nums ${getDiffColor(diffTotal)}`}
                                                                            >
                                                                                {formatDiff(
                                                                                    diffTotal,
                                                                                )}
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
                                        {activeTab === 'file' && (
                                            <div className="space-y-6 pt-4">
                                                {/* Summary */}
                                                <div>
                                                    <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                                                        File Summary
                                                    </h3>
                                                    <div
                                                        className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                                                        {comparisonData.fileCategories.map(
                                                            (category) => (
                                                                <div
                                                                    key={
                                                                        category.category
                                                                    }
                                                                    className="rounded-lg bg-gray-100 p-4 dark:bg-gray-700/50"
                                                                >
                                                                    <div
                                                                        className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                                        {category.category
                                                                                .charAt(
                                                                                    0,
                                                                                )
                                                                                .toUpperCase() +
                                                                            category.category.slice(
                                                                                1,
                                                                            )}
                                                                    </div>
                                                                    <div className="mt-1 flex items-baseline">
                                                                        <div
                                                                            className="text-sm text-gray-500 dark:text-gray-400">
                                                                            {formatNumber(
                                                                                category
                                                                                    .from
                                                                                    .count,
                                                                            )}
                                                                        </div>
                                                                        <div
                                                                            className="mx-1 text-gray-400 dark:text-gray-500">
                                                                            →
                                                                        </div>
                                                                        <div
                                                                            className="text-base font-semibold text-gray-900 dark:text-white">
                                                                            {formatNumber(
                                                                                category
                                                                                    .to
                                                                                    .count,
                                                                            )}
                                                                        </div>
                                                                        {category
                                                                                .diff
                                                                                .count !==
                                                                            0 && (
                                                                                <div
                                                                                    className={`ml-2 text-sm ${getDiffColor(category.diff.count)}`}
                                                                                >
                                                                                    {formatDiff(
                                                                                        category
                                                                                            .diff
                                                                                            .count,
                                                                                    )}
                                                                                </div>
                                                                            )}
                                                                    </div>
                                                                    <div className="mt-1 flex items-baseline text-sm">
                                                                        <div
                                                                            className="text-gray-500 dark:text-gray-400">
                                                                            {formatBytes(
                                                                                category
                                                                                    .from
                                                                                    .size,
                                                                            )}
                                                                        </div>
                                                                        <div
                                                                            className="mx-1 text-gray-400 dark:text-gray-500">
                                                                            →
                                                                        </div>
                                                                        <div className="text-gray-900 dark:text-white">
                                                                            {formatBytes(
                                                                                category
                                                                                    .to
                                                                                    .size,
                                                                            )}
                                                                        </div>
                                                                        {category
                                                                                .diff
                                                                                .size !==
                                                                            0 && (
                                                                                <div
                                                                                    className={`ml-2 ${getDiffColor(category.diff.size)}`}
                                                                                >
                                                                                    {category
                                                                                        .diff
                                                                                        .size >
                                                                                    0
                                                                                        ? '+'
                                                                                        : ''}
                                                                                    {formatBytes(
                                                                                        Math.abs(
                                                                                            category
                                                                                                .diff
                                                                                                .size,
                                                                                        ),
                                                                                    )}
                                                                                </div>
                                                                            )}
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Detailed Breakdown */}
                                                <div className="space-y-6">
                                                    {comparisonData.fileCategories.map(
                                                        (category) =>
                                                            Object.keys(
                                                                category.fileTypes,
                                                            ).length > 0 && (
                                                                <div
                                                                    key={
                                                                        category.category
                                                                    }
                                                                >
                                                                    <h4 className="mb-2 text-base font-medium text-gray-900 dark:text-white">
                                                                        {category.category
                                                                                .charAt(
                                                                                    0,
                                                                                )
                                                                                .toUpperCase() +
                                                                            category.category.slice(
                                                                                1,
                                                                            )}{' '}
                                                                        Files
                                                                    </h4>
                                                                    <div
                                                                        className="overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700/50">
                                                                        <table
                                                                            className="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                                                            <thead>
                                                                            <tr>
                                                                                <th className="px-4 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                                                    Type
                                                                                </th>
                                                                                <th
                                                                                    className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                                                                    colSpan={
                                                                                        3
                                                                                    }
                                                                                >
                                                                                    Count
                                                                                </th>
                                                                                <th
                                                                                    className="px-4 py-2 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                                                                    colSpan={
                                                                                        3
                                                                                    }
                                                                                >
                                                                                    Size
                                                                                </th>
                                                                            </tr>
                                                                            <tr className="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
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
                                                                            <tbody
                                                                                className="divide-y divide-gray-200 dark:divide-gray-600">
                                                                            {Object.entries(
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
                                                                                        <td className="px-4 py-2 text-sm text-gray-900 dark:text-white">
                                                                                            {
                                                                                                extension
                                                                                            }
                                                                                        </td>
                                                                                        {/* Count */}
                                                                                        <td className="px-2 py-2 text-right text-sm text-gray-500 dark:text-gray-400">
                                                                                            {formatNumber(
                                                                                                typeStats
                                                                                                    .from
                                                                                                    .count,
                                                                                            )}
                                                                                        </td>
                                                                                        <td className="px-2 py-2 text-right text-sm text-gray-900 dark:text-white">
                                                                                            {formatNumber(
                                                                                                typeStats
                                                                                                    .to
                                                                                                    .count,
                                                                                            )}
                                                                                        </td>
                                                                                        <td
                                                                                            className={`px-2 py-2 text-right text-sm ${getDiffColor(typeStats.diff.count)}`}
                                                                                        >
                                                                                            {formatDiff(
                                                                                                typeStats
                                                                                                    .diff
                                                                                                    .count,
                                                                                            )}
                                                                                        </td>
                                                                                        {/* Size */}
                                                                                        <td className="px-2 py-2 text-right text-sm text-gray-500 dark:text-gray-400">
                                                                                            {formatBytes(
                                                                                                typeStats
                                                                                                    .from
                                                                                                    .size,
                                                                                            )}
                                                                                        </td>
                                                                                        <td className="px-2 py-2 text-right text-sm text-gray-900 dark:text-white">
                                                                                            {formatBytes(
                                                                                                typeStats
                                                                                                    .to
                                                                                                    .size,
                                                                                            )}
                                                                                        </td>
                                                                                        <td
                                                                                            className={`px-2 py-2 text-right text-sm ${getDiffColor(typeStats.diff.size)}`}
                                                                                        >
                                                                                            {typeStats
                                                                                                .diff
                                                                                                .size !==
                                                                                            0
                                                                                                ? (typeStats
                                                                                                    .diff
                                                                                                    .size >
                                                                                                0
                                                                                                    ? '+'
                                                                                                    : '') +
                                                                                                formatBytes(
                                                                                                    Math.abs(
                                                                                                        typeStats
                                                                                                            .diff
                                                                                                            .size,
                                                                                                    ),
                                                                                                )
                                                                                                : '-'}
                                                                                        </td>
                                                                                    </tr>
                                                                                ),
                                                                            )}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
