import Pagination from '@/components/pagination';
import {WordCloud} from '@/components/word-cloud';
import {Link, usePage} from '@inertiajs/react';
import type {AxiosInstance} from 'axios';
import {useEffect, useMemo, useState} from 'react';

declare global {
    interface Window {
        axios: AxiosInstance;
    }
}

type InitialProps = {
    initial: {
        gameId: number;
        gameName: string;
        gameSlug: string;
        versionId?: number | null;
    };
};

type ApiLanguage = {
    id: string; // iso code e.g., 'eng'
    name: string; // ref_name
    flag?: string | null;
};

type Pagination = {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
};

type VersionOption = {
    id: number;
    version: string;
    published_at?: string | null;
};

type CharacterOption = { id: number; character_id: string; name: string };

type SearchResult = {
    id: number;
    text_content: string;
    highlighted_text: string;
    context: string | null;
    file_path: string | null;
    line_number: number | null;
    character_id: string | null;
    character_name: string | null;
    iso_code: string | null;
    game_version_id: number;
    game: {
        id: number;
        name: string;
    } | null;
    version: {
        id: number;
        version: string;
    } | null;
};

type DuplicateExample = {
    game_name: string;
    version: string;
    character_id: string | null;
    character_display_name?: string | null;
    iso_code: string;
    context?: string | null;
    file_path?: string | null;
    line_number?: number | null;
};

type DuplicateItem = {
    text_id: number;
    text_content: string;
    usage_count: number;
    examples?: DuplicateExample[];
};

type WordFrequencyItem = {
    text: string;
    value: number;
};

export default function DialogueBrowser({initial}: InitialProps) {
    const inertiaPage = usePage();

    // gameId is now required and fixed - comes from route parameter
    const gameId = initial.gameId;
    const gameName = initial.gameName;
    const gameSlug = initial.gameSlug;
    const preselectedVersionId = initial?.versionId ?? null;

    // Parse initial state from URL if present
    // Always use window.location.href on client side to get current URL with query params
    const initialLocation =
        typeof window !== 'undefined'
            ? window.location.href
            : (
                inertiaPage?.props as {
                    ziggy?: { location?: string };
                }
            )?.ziggy?.location || 'http://localhost/';
    const url = useMemo(
        () =>
            new URL(
                initialLocation,
                typeof window === 'undefined' ? 'http://localhost/' : undefined,
            ),
        [initialLocation],
    );
    const qp = url.searchParams;

    const qpVersionId = qp.get('versionId');
    const qpQ = qp.get('q') ?? '';
    const qpPage = parseInt(qp.get('page') || '1', 10);
    const qpPerPage = parseInt(qp.get('perPage') || '25', 10);
    const qpSelectedLangs = (qp.get('selectedLangs') || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

    const [versionId, setVersionId] = useState<number | null>(
        qpVersionId ? Number(qpVersionId) : preselectedVersionId,
    );
    const [q, setQ] = useState(qpQ);
    const [currentPage, setCurrentPage] = useState(
        Number.isFinite(qpPage) && qpPage > 0 ? qpPage : 1,
    );
    const [perPage, setPerPage] = useState(
        [25, 50, 100].includes(qpPerPage) ? qpPerPage : 25,
    );

    const [versions, setVersions] = useState<VersionOption[]>([]);
    const [languages, setLanguages] = useState<ApiLanguage[]>([]);
    const [characters, setCharacters] = useState<CharacterOption[]>([]);
    const [contexts, setContexts] = useState<string[]>([]);
    const [summary, setSummary] = useState<{
        totalLines: number;
        totalWords: number;
        uniqueCharacters: number;
        avgWordsPerLine: number;
        languages: ApiLanguage[];
    }>({
        totalLines: 0,
        totalWords: 0,
        uniqueCharacters: 0,
        avgWordsPerLine: 0,
        languages: [],
    });
    const [pagination, setPagination] = useState<Pagination>({
        current_page: 1,
        per_page: 25,
        total: 0,
        last_page: 0,
    });
    const [loading, setLoading] = useState(false);

    // Selected languages now sent to backend for correct totals/pagination
    const [selectedLangs, setSelectedLangs] =
        useState<string[]>(qpSelectedLangs);
    const [language, setLanguage] = useState<string>(
        qpSelectedLangs[0] || 'eng',
    );
    const [selectedCharacterId, setSelectedCharacterId] = useState<string>('');
    const [selectedContext, setSelectedContext] = useState<string>('');
    const [exactMatch, setExactMatch] = useState<boolean>(false);

    // Search vs duplicates
    const [showDuplicates, setShowDuplicates] = useState(false);
    const [minLineLength, setMinLineLength] = useState<number>(10);
    const [minDuplicateCount, setMinDuplicateCount] = useState<number>(3);
    const [duplicatesLimit, setDuplicatesLimit] = useState<number>(10);

    // Results
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [duplicates, setDuplicates] = useState<DuplicateItem[]>([]);
    const [wordFrequency, setWordFrequency] = useState<WordFrequencyItem[]>([]);

    const fetchData = async (opts?: { page?: number; perPage?: number }) => {
        setLoading(true);
        try {
            const params = {
                gameId: gameId ?? undefined,
                versionId: versionId ?? undefined,
                q: q || undefined,
                page: opts?.page ?? currentPage,
                perPage: opts?.perPage ?? perPage,
                selectedLanguages:
                    selectedLangs.length > 0
                        ? selectedLangs.join(',')
                        : undefined,
            };
            const resp = await window.axios.get(
                route('react-api.dialogue.index'),
                {params},
            );
            if (resp.data && resp.data.success) {
                // Options
                if (Array.isArray(resp.data.versions))
                    setVersions(resp.data.versions);
                // Items are stored but not currently displayed in UI
                // setItems(resp.data.items || []);
                const serverSummary = resp.data.summary;
                if (serverSummary && Array.isArray(serverSummary.languages)) {
                    setSummary((prev) => ({
                        ...prev,
                        languages: serverSummary.languages,
                    }));
                }
                const p = resp.data.pagination || {
                    current_page: 1,
                    per_page: perPage,
                    total: 0,
                    last_page: 0,
                };
                setPagination({
                    current_page: p.current_page ?? 1,
                    per_page: p.per_page ?? perPage,
                    total: p.total ?? 0,
                    last_page: p.last_page ?? 0,
                });
            }
        } catch (e) {
            console.error('Failed to load dialogue data', e);
        } finally {
            setLoading(false);
        }
    };

    const fetchOptions = async () => {
        // gameId is now always available (required)
        try {
            const resp = await window.axios.get(
                route('react-api.dialogue.options'),
                {
                    params: {
                        gameId,
                        versionId: versionId ?? undefined,
                        language,
                    },
                },
            );
            if (resp.data?.success) {
                if (Array.isArray(resp.data.versions))
                    setVersions(resp.data.versions);
                if (Array.isArray(resp.data.languages))
                    setLanguages(resp.data.languages);
                if (Array.isArray(resp.data.characters))
                    setCharacters(resp.data.characters);
                if (Array.isArray(resp.data.contexts))
                    setContexts(resp.data.contexts);
            }
        } catch (e) {
            console.error('Failed to load options', e);
        }
    };

    const fetchVersionStats = async () => {
        if (!versionId) return;
        try {
            const resp = await window.axios.get(
                route('react-api.dialogue.version-stats'),
                {params: {versionId}},
            );
            if (resp.data?.success && resp.data.data) {
                const s = resp.data.data;
                setSummary({
                    totalLines: s.total_lines ?? 0,
                    totalWords: s.total_words ?? 0,
                    uniqueCharacters: s.unique_characters ?? 0,
                    avgWordsPerLine: Number(s.avg_words_per_line ?? 0),
                    languages: languages.length ? languages : summary.languages,
                });
            }
        } catch {
            // ignore
        }
    };

    const runSearch = async (opts?: { page?: number }) => {
        if (!canSearch || !q.trim()) {
            setSearchResults([]);
            return;
        }
        setLoading(true);
        try {
            const resp = await window.axios.get(
                route('react-api.dialogue.search'),
                {
                    params: {
                        q,
                        language,
                        gameId: gameId ?? undefined,
                        versionId: versionId ?? undefined,
                        characterId: selectedCharacterId || undefined,
                        context: selectedContext || undefined,
                        perPage,
                        page: opts?.page ?? currentPage,
                        exactMatch: exactMatch || undefined,
                    },
                },
            );
            if (resp.data?.success) {
                // Avoid re-render churn if data is identical
                const next: SearchResult[] = resp.data.data || [];
                const same =
                    next.length === searchResults.length &&
                    next.every((n, i) => n.id === searchResults[i]?.id);
                if (!same) setSearchResults(next);
                const p = resp.data.pagination || {};
                setPagination({
                    current_page: p.current_page ?? 1,
                    per_page: p.per_page ?? perPage,
                    total: p.total ?? 0,
                    last_page: p.last_page ?? 0,
                });
            }
        } catch (e) {
            console.error('Failed to search dialogue', e);
        } finally {
            setLoading(false);
        }
    };

    const runDuplicates = async () => {
        if (!canSearch) {
            setDuplicates([]);
            return;
        }
        setLoading(true);
        try {
            const resp = await window.axios.get(
                route('react-api.dialogue.duplicates'),
                {
                    params: {
                        language,
                        gameId: gameId ?? undefined,
                        versionId: versionId ?? undefined,
                        characterId: selectedCharacterId || undefined,
                        minLineLength,
                        minDuplicateCount,
                        limit: duplicatesLimit,
                    },
                },
            );
            if (resp.data?.success) {
                setDuplicates(resp.data.data || []);
            }
        } catch (e) {
            console.error('Failed to load duplicates', e);
        } finally {
            setLoading(false);
        }
    };

    const fetchWordFrequency = async () => {
        if (!versionId) {
            setWordFrequency([]);
            return;
        }
        try {
            const resp = await window.axios.get(
                route('react-api.dialogue.word-frequency'),
                {
                    params: {
                        versionId,
                        language,
                        limit: 100,
                        includePhrases: true,
                        minWordLength: 3,
                    },
                },
            );
            if (resp.data?.success) {
                setWordFrequency(resp.data.data || []);
            }
        } catch (e) {
            console.error('Failed to load word frequency', e);
            setWordFrequency([]);
        }
    };

    useEffect(() => {
        // Initial load: fetch options once (gameId is always available)
        fetchOptions();
        if (versionId) {
            fetchVersionStats();
            fetchWordFrequency();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const canSearch = useMemo(
        () => !!versionId,
        [versionId],
    );

    // When version or language changes, refresh characters/contexts and reset their selections
    useEffect(() => {
        if (!versionId) return;
        setSelectedCharacterId('');
        setSelectedContext('');
        fetchOptions();
        fetchWordFrequency();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [versionId, language]);

    // Auto-run search with debounce when typing, similar to production 'updatedSearchQuery'
    useEffect(() => {
        if (!canSearch || showDuplicates) return;
        const trimmed = q.trim();
        if (!trimmed) return; // no search
        const controller = new AbortController();
        const handle = setTimeout(() => {
            // runSearch respects canSearch; we could pass abort controller to axios if needed
            runSearch({page: 1});
            setCurrentPage(1);
        }, 300);
        return () => {
            clearTimeout(handle);
            // Note: axios cancel token not wired; ensure only latest timeout fires
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        q,
        gameId,
        versionId,
        language,
        selectedCharacterId,
        selectedContext,
        showDuplicates,
        canSearch,
        exactMatch,
    ]);

    // When toggling duplicates on, clear search and fetch duplicates automatically
    useEffect(() => {
        if (showDuplicates) {
            if (q) setQ('');
            setSearchResults([]);
            runDuplicates();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        showDuplicates,
        gameId,
        versionId,
        language,
        selectedCharacterId,
        minLineLength,
        minDuplicateCount,
        duplicatesLimit,
    ]);

    // Sync state to URL for shareable links
    useEffect(() => {
        if (typeof window === 'undefined') return;
        const next = new URL(window.location.href);
        const sp = next.searchParams;
        // Clear first to avoid duplicates
        sp.delete('gameId');
        sp.delete('versionId');
        sp.delete('q');
        sp.delete('page');
        sp.delete('perPage');
        sp.delete('selectedLangs');

        // gameId is in the route path, not query params
        if (versionId) sp.set('versionId', String(versionId));
        if (q) sp.set('q', q);
        if (currentPage && currentPage !== 1)
            sp.set('page', String(currentPage));
        if (perPage && perPage !== 25) sp.set('perPage', String(perPage));
        if (selectedLangs.length > 0)
            sp.set('selectedLangs', selectedLangs.join(','));

        const newUrl = `${next.pathname}?${sp.toString()}`;
        // Only push if changed
        if (newUrl !== window.location.pathname + window.location.search) {
            window.history.replaceState({}, '', newUrl);
        }
    }, [versionId, q, currentPage, perPage, selectedLangs]);

    const onApplyFilters = async () => {
        setCurrentPage(1);
        await fetchOptions();
        await fetchVersionStats();
        if (showDuplicates) {
            await runDuplicates();
        } else if (q.trim()) {
            await runSearch({page: 1});
        } else {
            await fetchData({page: 1});
        }
    };

    const onChangePage = async (newPage: number) => {
        if (
            newPage < 1 ||
            (pagination.last_page && newPage > pagination.last_page)
        )
            return;

        setCurrentPage(newPage);
        // Use the appropriate fetch method based on current mode
        if (showDuplicates) {
            await runDuplicates();
        } else if (q.trim()) {
            await runSearch({page: newPage});
        } else {
            await fetchData({page: newPage});
        }
    };

    const onChangePerPage = async (newPerPage: number) => {
        setPerPage(newPerPage);
        setCurrentPage(1);
        if (showDuplicates) {
            await runDuplicates();
        } else if (q.trim()) {
            await runSearch({page: 1});
        } else {
            await fetchData({page: 1, perPage: newPerPage});
        }
    };

    return (
        <>
            <div className="bg-gray-100 dark:bg-gray-900">
                <div className="mx-auto max-w-7xl">
                    <div
                        className="sticky top-0 z-10 mb-4 flex items-center justify-between bg-gray-100 py-4 dark:bg-gray-900">
                        <Link
                            href={route('games.show', gameSlug)}
                            className="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            <svg
                                className="mr-1 h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            Back to {gameName}
                        </Link>
                    </div>

                    <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div className="mb-4 flex items-center justify-between">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                Dialogue Browser - {gameName}
                            </h1>
                        </div>
                        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Version
                                </label>
                                <select
                                    value={versionId ?? ''}
                                    onChange={(e) =>
                                        setVersionId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        )
                                    }
                                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">Select Version</option>
                                    {versions.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.version}
                                            {v.published_at
                                                ? ` (${new Date(v.published_at).toISOString().slice(0, 10)})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Character
                                </label>
                                <select
                                    value={selectedCharacterId}
                                    onChange={(e) =>
                                        setSelectedCharacterId(e.target.value)
                                    }
                                    disabled={!versionId}
                                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">All Characters</option>
                                    <option value="narrator">Narrator</option>
                                    <option value="menu_choice">
                                        Menu Choices
                                    </option>
                                    {characters.map((c) => (
                                        <option
                                            key={c.id}
                                            value={c.character_id}
                                        >
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Language
                                </label>
                                <select
                                    value={language}
                                    onChange={async (e) => {
                                        setLanguage(e.target.value);
                                        await fetchOptions();
                                    }}
                                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    {languages.length === 0 && (
                                        <option value={language}>
                                            {language.toUpperCase()}
                                        </option>
                                    )}
                                    {languages.map((l) => (
                                        <option
                                            key={l.id}
                                            value={l.id}
                                        >{`${l.name}${l.flag ? ` (${l.flag})` : ''}`}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Context
                                </label>
                                <select
                                    value={selectedContext}
                                    onChange={(e) =>
                                        setSelectedContext(e.target.value)
                                    }
                                    disabled={!versionId}
                                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <option value="">All Contexts</option>
                                    {contexts.map((c) => (
                                        <option key={c} value={c}>
                                            {c}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Search
                                </label>
                                <div className="relative mt-1 flex rounded-md shadow-sm">
                                    <input
                                        type="text"
                                        value={q}
                                        onChange={(e) => {
                                            setQ(e.target.value);
                                            if (e.target.value)
                                                setShowDuplicates(false);
                                            setExactMatch(false);
                                        }}
                                        onKeyDown={async (e) => {
                                            if (e.key === 'Enter') {
                                                setCurrentPage(1);
                                                await onApplyFilters();
                                            }
                                        }}
                                        placeholder="Search dialogue..."
                                        disabled={showDuplicates}
                                        className="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Controls */}
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center space-x-4">

                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowDuplicates((prev) => !prev)
                                    }
                                    className={`flex items-center rounded-lg px-3 py-1 text-sm ${
                                        showDuplicates
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                    }`}
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="mr-1 h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                    {showDuplicates
                                        ? 'Hide Duplicates'
                                        : 'Show Duplicates'}
                                </button>

                                <div className="flex items-center space-x-2">
                                    <select
                                        value={perPage}
                                        onChange={(e) =>
                                            onChangePerPage(
                                                Number(e.target.value),
                                            )
                                        }
                                        className="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value={25}>25 per page</option>
                                        <option value={50}>50 per page</option>
                                        <option value={100}>
                                            100 per page
                                        </option>
                                    </select>
                                </div>
                            </div>
                            {!canSearch && (
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    Select a game and version to search
                                </span>
                            )}
                        </div>

                        {/* Duplicates Options */}
                        {showDuplicates && (
                            <div className="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/30">
                                <h3 className="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Duplicate Line Settings
                                </h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            Minimum Line Length
                                        </label>
                                        <input
                                            type="number"
                                            value={minLineLength}
                                            onChange={(e) =>
                                                setMinLineLength(
                                                    parseInt(e.target.value) ||
                                                    10,
                                                )
                                            }
                                            min="3"
                                            max="50"
                                            className="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            Minimum Duplicates
                                        </label>
                                        <input
                                            type="number"
                                            value={minDuplicateCount}
                                            onChange={(e) =>
                                                setMinDuplicateCount(
                                                    parseInt(e.target.value) ||
                                                    3,
                                                )
                                            }
                                            min="2"
                                            max="20"
                                            className="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            Maximum Results
                                        </label>
                                        <input
                                            type="number"
                                            value={duplicatesLimit}
                                            onChange={(e) =>
                                                setDuplicatesLimit(
                                                    parseInt(e.target.value) ||
                                                    10,
                                                )
                                            }
                                            min="5"
                                            max="50"
                                            className="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        />
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Statistics card */}
                    <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                            Version Statistics
                        </h3>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    Total Lines
                                </div>
                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {summary.totalLines.toLocaleString()}
                                </div>
                            </div>

                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    Total Words
                                </div>
                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {summary.totalWords.toLocaleString()}
                                </div>
                            </div>

                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    Characters
                                </div>
                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {summary.uniqueCharacters.toLocaleString()}
                                </div>
                            </div>

                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                    Avg Words/Line
                                </div>
                                <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {summary.avgWordsPerLine.toFixed(1)}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Word Cloud Section */}
                    {versionId && wordFrequency.length > 0 && (
                        <div className="mb-6 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                            <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                Common Words & Phrases
                            </h3>
                            <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                                The most frequently used words and phrases in the
                                dialogue. Larger bubbles indicate higher frequency.
                            </p>
                            <div className="flex justify-center">
                                <WordCloud
                                    data={wordFrequency}
                                    width={900}
                                    height={450}
                                    onWordClick={(word) => {
                                        setQ(word);
                                        setShowDuplicates(false);
                                        setCurrentPage(1);
                                        setExactMatch(true);
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    {/* Results Panel */}
                    <div className="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
                        {/* Top Duplicates View */}
                        {showDuplicates && (
                            <div className="mb-4">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Top Duplicated Lines{' '}
                                    {gameId
                                        ? 'in Selected Game'
                                        : 'Across All Games'}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Showing lines that appear at least{' '}
                                    {minDuplicateCount} times, with a minimum
                                    length of {minLineLength} characters.
                                </p>
                            </div>
                        )}

                        {loading ? (
                            <div className="p-6 text-gray-600 dark:text-gray-300">
                                Loading…
                            </div>
                        ) : (
                            <>
                                {showDuplicates && (
                                    <div className="space-y-6">
                                        {duplicates.length === 0 ? (
                                            <div
                                                className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                                <p className="text-yellow-700 dark:text-yellow-500">
                                                    No duplicate lines found
                                                    matching your criteria. Try
                                                    adjusting the minimum line
                                                    length or duplicate count.
                                                </p>
                                            </div>
                                        ) : (
                                            duplicates.map((dupe) => (
                                                <div
                                                    key={dupe.text_id}
                                                    className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                                >
                                                    <div className="mb-3 flex items-center justify-between">
                                                        <div className="font-medium text-gray-900 dark:text-gray-100">
                                                            Appears{' '}
                                                            {dupe.usage_count}{' '}
                                                            times
                                                        </div>
                                                        <div
                                                            className="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                                            {dupe.text_content
                                                                    ?.length ||
                                                                0}{' '}
                                                            characters
                                                        </div>
                                                    </div>

                                                    <div
                                                        className="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                                                        {dupe.text_content}
                                                    </div>

                                                    <div className="mt-3">
                                                        <div
                                                            className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Examples:
                                                        </div>
                                                        <div className="space-y-2">
                                                            {dupe.examples?.map(
                                                                (ex, idx) => (
                                                                    <div
                                                                        key={
                                                                            idx
                                                                        }
                                                                        className="rounded-lg border border-gray-200 bg-white p-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                                                    >
                                                                        <div className="flex justify-between">
                                                                            <span className="font-medium">
                                                                                {
                                                                                    ex.game_name
                                                                                }{' '}
                                                                                (
                                                                                {
                                                                                    ex.version
                                                                                }

                                                                                )
                                                                            </span>
                                                                            <span>
                                                                                {ex.character_id ===
                                                                                'menu_choice'
                                                                                    ? 'Choice'
                                                                                    : ex.character_display_name ||
                                                                                    ex.character_id}
                                                                            </span>
                                                                        </div>
                                                                        {ex.context && (
                                                                            <div
                                                                                className="mt-1 text-gray-500 dark:text-gray-400">
                                                                                Context:{' '}
                                                                                {
                                                                                    ex.context
                                                                                }
                                                                            </div>
                                                                        )}
                                                                        <div
                                                                            className="mt-1 text-gray-500 dark:text-gray-400">
                                                                            {
                                                                                ex.file_path
                                                                            }
                                                                            :
                                                                            {
                                                                                ex.line_number
                                                                            }
                                                                        </div>
                                                                    </div>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                )}

                                {/* Search Results */}
                                {!showDuplicates && q.trim() && (
                                    <>
                                        <div className="mb-4">
                                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                Search Results:{' '}
                                                {pagination.total} matches for "
                                                {q}"
                                            </h3>
                                        </div>

                                        {searchResults.length === 0 ? (
                                            <div
                                                className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                                <p className="text-yellow-700 dark:text-yellow-500">
                                                    No results found for "{q}"
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="space-y-3">
                                                {searchResults.map((line) => (
                                                    <div
                                                        key={line.id}
                                                        className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                                    >
                                                        <div
                                                            className="mb-3 text-gray-900 dark:text-gray-100"
                                                            dangerouslySetInnerHTML={{ __html: line.highlighted_text }}
                                                        />

                                                        <div className="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                            {line.character_name && (
                                                                <span className="rounded-full bg-green-100 px-2 py-1 text-green-800 dark:bg-green-900/50 dark:text-green-200">
                                                                    {line.character_name}
                                                                </span>
                                                            )}

                                                            {line.context && (
                                                                <span className="rounded-full bg-blue-100 px-2 py-1 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                                                    {line.context}
                                                                </span>
                                                            )}
                                                        </div>

                                                        {line.file_path && (
                                                            <div className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                                <span className="font-medium">
                                                                    {line.game?.name}({line.version?.version}) -{line.file_path}
                                                                    {line.line_number && `:${line.line_number}`}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {/* Pagination - show if there are total results, even if current page is empty */}
                                        {pagination.total > 0 && (
                                            <div className="mt-4">
                                                <Pagination
                                                    meta={{
                                                        current_page:
                                                        currentPage,
                                                        last_page:
                                                            pagination.last_page ||
                                                            Math.ceil(
                                                                pagination.total /
                                                                perPage,
                                                            ),
                                                        total: pagination.total,
                                                        from:
                                                            (currentPage -
                                                                1) *
                                                            perPage +
                                                            1,
                                                        to: Math.min(
                                                            currentPage *
                                                            perPage,
                                                            pagination.total,
                                                        ),
                                                    }}
                                                    loading={loading}
                                                    label="results"
                                                    onChange={(page) =>
                                                        onChangePage(page)
                                                    }
                                                />
                                            </div>
                                        )}
                                    </>
                                )}

                                {!q.trim() && !showDuplicates && (
                                    <div
                                        className="rounded-lg border border-gray-200 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-700/30">
                                        <p className="text-gray-500 dark:text-gray-400">
                                            {showDuplicates
                                                ? 'Adjust the settings above to find duplicated dialogue lines'
                                                : 'Enter a search term to find dialogue or use the "Show Duplicates" button to see repeated lines'}
                                        </p>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
