<script lang="ts">
    import { untrack } from 'svelte';
    import Pagination from '@/components/Pagination.svelte';
    import WordCloud from '@/components/WordCloud.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import {
        fetchDialogueOptions,
        fetchDialogueVersionStats,
        fetchDialogueSearch,
        fetchDialogueDuplicates,
        fetchWordFrequency,
        type DialogueSearchResult,
        type DuplicateItem,
    } from '@/api';
    import { renderTrustedMarksOnly } from '@/utils/safe-highlight';
    import { page } from '@inertiajs/svelte';
    import { Button, Card } from '@/components/ui';

    type InitialProps = {
        initial: {
            gameId: number;
            gameName: string;
            gameSlug: string;
            versionId?: number | null;
        };
    };

    let { initial }: InitialProps = $props();

    const gameId = $derived(initial.gameId);
    const gameName = $derived(initial.gameName);
    const gameSlug = $derived(initial.gameSlug);
    const preselectedVersionId = $derived(initial?.versionId ?? null);

    const initialLocation = $derived(
        typeof window !== 'undefined' ? window.location.href : (page.props as any)?.ziggy?.location || 'http://localhost/',
    );

    const url = untrack(() => new URL(initialLocation, typeof window === 'undefined' ? 'http://localhost/' : undefined));
    const qp = url.searchParams;

    const qpVersionId = qp.get('versionId');
    const qpQ = qp.get('q') ?? '';
    const qpPage = parseInt(qp.get('page') || '1', 10);
    const qpPerPage = parseInt(qp.get('perPage') || '25', 10);
    const qpSelectedLangs = (qp.get('selectedLangs') || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

    // State
    let versionId = $state<number | null>(untrack(() => (qpVersionId ? Number(qpVersionId) : preselectedVersionId)));
    let q = $state(untrack(() => qpQ));
    let debouncedQ = $state(untrack(() => qpQ));
    let currentPage = $state(Number.isFinite(qpPage) && qpPage > 0 ? qpPage : 1);
    let perPage = $state([25, 50, 100].includes(qpPerPage) ? qpPerPage : 25);
    let selectedLangs = $state<string[]>(qpSelectedLangs);
    let language = $state<string>(qpSelectedLangs[0] || 'eng');
    let selectedCharacterId = $state<string>('');
    let selectedContext = $state<string>('');
    let exactMatch = $state<boolean>(false);
    let showDuplicates = $state(false);
    let minLineLength = $state<number>(10);
    let minDuplicateCount = $state<number>(3);
    let duplicatesLimit = $state<number>(10);

    // Debounce search query
    let debounceTimer: ReturnType<typeof setTimeout>;
    $effect(() => {
        // Track q dependency
        const currentQ = q;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (currentQ !== debouncedQ) {
                currentPage = 1;
            }
            debouncedQ = currentQ;
        }, 300);
        return () => clearTimeout(debounceTimer);
    });

    // Async data state
    let options = $state<any>(null);
    let versionStats = $state<any>(null);
    let searchData = $state<any>(null);
    let duplicates = $state<DuplicateItem[]>([]);
    let wordFrequency = $state<any[]>([]);
    let optionsLoading = $state(false);
    let searchLoading = $state(false);
    let duplicatesLoading = $state(false);

    $effect(() => {
        optionsLoading = true;
        fetchDialogueOptions({ gameId, versionId: versionId ?? undefined, language })
            .then((data) => {
                options = data;
            })
            .catch(() => {
                options = null;
            })
            .finally(() => {
                optionsLoading = false;
            });
    });

    $effect(() => {
        if (!versionId) {
            versionStats = null;
            return;
        }
        fetchDialogueVersionStats(versionId)
            .then((data) => {
                versionStats = data;
            })
            .catch(() => {
                versionStats = null;
            });
    });

    $effect(() => {
        if (showDuplicates || !versionId || !debouncedQ.trim()) {
            searchData = null;
            return;
        }
        searchLoading = true;
        fetchDialogueSearch({
            q: debouncedQ,
            language,
            gameId,
            versionId: versionId ?? undefined,
            characterId: selectedCharacterId,
            context: selectedContext,
            perPage,
            page: currentPage,
            exactMatch,
        })
            .then((data) => {
                searchData = data;
            })
            .catch(() => {
                searchData = null;
            })
            .finally(() => {
                searchLoading = false;
            });
    });

    $effect(() => {
        if (!showDuplicates || !versionId) {
            duplicates = [];
            return;
        }
        duplicatesLoading = true;
        fetchDialogueDuplicates({
            language,
            gameId,
            versionId,
            characterId: selectedCharacterId,
            minLineLength,
            minDuplicateCount,
            limit: duplicatesLimit,
        })
            .then((data) => {
                duplicates = data;
            })
            .catch(() => {
                duplicates = [];
            })
            .finally(() => {
                duplicatesLoading = false;
            });
    });

    $effect(() => {
        if (!versionId) {
            wordFrequency = [];
            return;
        }
        fetchWordFrequency({ versionId, language })
            .then((data) => {
                wordFrequency = data;
            })
            .catch(() => {
                wordFrequency = [];
            });
    });

    const versions = $derived(options?.versions || []);
    const languages = $derived(options?.languages || []);
    const characters = $derived(options?.characters || []);
    const contexts = $derived(options?.contexts || []);

    const summary = $derived({
        totalLines: versionStats?.totalLines || 0,
        totalWords: versionStats?.totalWords || 0,
        uniqueCharacters: versionStats?.uniqueCharacters || 0,
        avgWordsPerLine: versionStats?.avgWordsPerLine || 0,
    });

    const searchResults: DialogueSearchResult[] = $derived(searchData?.results || []);
    const pagination = $derived(
        searchData?.pagination || {
            current_page: 1,
            per_page: perPage,
            total: 0,
            last_page: 0,
        },
    );

    const loading = $derived(optionsLoading || searchLoading || duplicatesLoading);
    const canSearch = $derived(!!versionId);

    // Reset character/context when version or language changes
    $effect(() => {
        if (!versionId) return;
        // Track dependencies
        void language;
        selectedCharacterId = '';
        selectedContext = '';
    });

    // Sync state to URL
    $effect(() => {
        if (typeof window === 'undefined') return;
        const next = new URL(window.location.href);
        const sp = next.searchParams;

        sp.delete('gameId');
        sp.delete('versionId');
        sp.delete('q');
        sp.delete('page');
        sp.delete('perPage');
        sp.delete('selectedLangs');

        if (versionId) sp.set('versionId', String(versionId));
        if (q) sp.set('q', q);
        if (currentPage && currentPage !== 1) sp.set('page', String(currentPage));
        if (perPage && perPage !== 25) sp.set('perPage', String(perPage));
        if (selectedLangs.length > 0) sp.set('selectedLangs', selectedLangs.join(','));

        const newUrl = `${next.pathname}?${sp.toString()}`;
        if (newUrl !== window.location.pathname + window.location.search) {
            window.history.replaceState({}, '', newUrl);
        }
    });

    const onChangePage = (newPage: number) => {
        if (newPage < 1 || (pagination.last_page && newPage > pagination.last_page)) return;
        currentPage = newPage;
    };

    const onChangePerPage = (newPerPage: number) => {
        perPage = newPerPage;
        currentPage = 1;
    };
</script>

<div class="bg-gray-100 dark:bg-gray-900">
    <div class="mx-auto max-w-7xl">
        <PageHeader
            title={`Dialogue Browser - ${gameName}`}
            backHref={route('games.show', gameSlug)}
            backLabel={`Back to ${gameName}`}
            class="mb-6"
        />

        <Card padding="lg" class="mb-6">
            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="version-select" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Version </label>
                    <select
                        id="version-select"
                        bind:value={versionId}
                        onchange={(e) => {
                            versionId = (e.target as HTMLSelectElement).value ? Number((e.target as HTMLSelectElement).value) : null;
                        }}
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">Select Version</option>
                        {#each versions as v (v.id)}
                            <option value={v.id}>
                                {v.version}
                                {v.published_at ? ` (${new Date(v.published_at).toISOString().slice(0, 10)})` : ''}
                            </option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label for="character-select" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Character </label>
                    <select
                        id="character-select"
                        bind:value={selectedCharacterId}
                        disabled={!versionId}
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">All Characters</option>
                        <option value="narrator">Narrator</option>
                        <option value="menu_choice">Menu Choices</option>
                        {#each characters as c (c.id)}
                            <option value={c.character_id}>{c.name}</option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label for="language-select" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Language </label>
                    <select
                        id="language-select"
                        bind:value={language}
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    >
                        {#if languages.length === 0}
                            <option value={language}>{language.toUpperCase()}</option>
                        {/if}
                        {#each languages as l (l.id)}
                            <option value={l.id}>
                                {`${l.name}${l.flag ? ` (${l.flag})` : ''}`}
                            </option>
                        {/each}
                    </select>
                </div>
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="context-select" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Context </label>
                    <select
                        id="context-select"
                        bind:value={selectedContext}
                        disabled={!versionId}
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">All Contexts</option>
                        {#each contexts as c (c)}
                            <option value={c}>{c}</option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label for="search-input" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Search </label>
                    <div class="relative mt-1 flex rounded-md shadow-sm">
                        <input
                            id="search-input"
                            type="text"
                            bind:value={q}
                            oninput={(e) => {
                                if ((e.target as HTMLInputElement).value) showDuplicates = false;
                                exactMatch = false;
                            }}
                            placeholder="Search dialogue..."
                            disabled={showDuplicates}
                            class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        />
                    </div>
                </div>
            </div>

            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center space-x-4">
                    <Button
                        type="button"
                        variant={showDuplicates ? 'soft' : 'soft'}
                        tone={showDuplicates ? 'primary' : 'neutral'}
                        onclick={() => {
                            showDuplicates = !showDuplicates;
                        }}
                        class="flex items-center rounded-lg px-3 py-1 text-sm {showDuplicates
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                        {showDuplicates ? 'Hide Duplicates' : 'Show Duplicates'}
                    </Button>

                    <div class="flex items-center space-x-2">
                        <select
                            value={perPage}
                            onchange={(e) => onChangePerPage(Number((e.target as HTMLSelectElement).value))}
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        >
                            <option value={25}>25 per page</option>
                            <option value={50}>50 per page</option>
                            <option value={100}>100 per page</option>
                        </select>
                    </div>
                </div>
                {#if !canSearch}
                    <span class="text-sm text-gray-500 dark:text-gray-400"> Select a game and version to search </span>
                {/if}
            </div>

            {#if showDuplicates}
                <div class="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/30">
                    <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Duplicate Line Settings</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label for="min-line-length" class="mb-1 block text-xs text-gray-500 dark:text-gray-400"> Minimum Line Length </label>
                            <input
                                id="min-line-length"
                                type="number"
                                bind:value={minLineLength}
                                min="3"
                                max="50"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>
                        <div>
                            <label for="min-duplicates" class="mb-1 block text-xs text-gray-500 dark:text-gray-400"> Minimum Duplicates </label>
                            <input
                                id="min-duplicates"
                                type="number"
                                bind:value={minDuplicateCount}
                                min="2"
                                max="20"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>
                        <div>
                            <label for="max-results" class="mb-1 block text-xs text-gray-500 dark:text-gray-400"> Maximum Results </label>
                            <input
                                id="max-results"
                                type="number"
                                bind:value={duplicatesLimit}
                                min="5"
                                max="50"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            />
                        </div>
                    </div>
                </div>
            {/if}
        </Card>

        <Card padding="lg" class="mb-6">
            <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Version Statistics</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                <Card variant="soft" padding="sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Lines</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.totalLines.toLocaleString()}
                    </div>
                </Card>
                <Card variant="soft" padding="sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Words</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.totalWords.toLocaleString()}
                    </div>
                </Card>
                <Card variant="soft" padding="sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Characters</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.uniqueCharacters.toLocaleString()}
                    </div>
                </Card>
                <Card variant="soft" padding="sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Avg Words/Line</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {summary.avgWordsPerLine.toFixed(1)}
                    </div>
                </Card>
            </div>
        </Card>

        {#if versionId && wordFrequency.length > 0}
            <Card padding="lg" class="mb-6">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Common Words & Phrases</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                    The most frequently used words and phrases in the dialogue. Larger bubbles indicate higher frequency.
                </p>
                <div class="flex justify-center">
                    <WordCloud
                        data={wordFrequency}
                        width={900}
                        height={450}
                        onWordClick={(word) => {
                            q = word;
                            showDuplicates = false;
                            currentPage = 1;
                            exactMatch = true;
                        }}
                    />
                </div>
            </Card>
        {/if}

        <Card padding="lg">
            {#if showDuplicates}
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Top Duplicated Lines {gameId ? 'in Selected Game' : 'Across All Games'}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Showing lines that appear at least {minDuplicateCount} times, with a minimum length of {minLineLength} characters.
                    </p>
                </div>
            {/if}

            {#if loading}
                <div class="p-6 text-gray-600 dark:text-gray-300">Loading...</div>
            {:else}
                {#if showDuplicates}
                    <div class="space-y-6">
                        {#if duplicates.length === 0}
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                <p class="text-yellow-700 dark:text-yellow-500">
                                    No duplicate lines found matching your criteria. Try adjusting the minimum line length or duplicate count.
                                </p>
                            </div>
                        {:else}
                            {#each duplicates as dupe (dupe.text_id)}
                                <Card variant="outline" padding="sm">
                                    <div class="mb-3 flex items-center justify-between">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            Appears {dupe.usage_count} times
                                        </div>
                                        <div class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                            {dupe.text_content?.length || 0} characters
                                        </div>
                                    </div>
                                    <div
                                        class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        {dupe.text_content}
                                    </div>
                                    <div class="mt-3">
                                        <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Examples:</div>
                                        <div class="space-y-2">
                                            {#each dupe.examples ?? [] as ex, _idx (ex.game_name)}
                                                <div
                                                    class="rounded-lg border border-gray-200 bg-white p-2 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                                >
                                                    <div class="flex justify-between">
                                                        <span class="font-medium">{ex.game_name} ({ex.version})</span>
                                                        <span
                                                            >{ex.character_id === 'menu_choice'
                                                                ? 'Choice'
                                                                : ex.character_display_name || ex.character_id}</span
                                                        >
                                                    </div>
                                                    {#if ex.context}
                                                        <div class="mt-1 text-gray-500 dark:text-gray-400">Context: {ex.context}</div>
                                                    {/if}
                                                    <div class="mt-1 text-gray-500 dark:text-gray-400">{ex.file_path}:{ex.line_number}</div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                </Card>
                            {/each}
                        {/if}
                    </div>
                {/if}

                {#if !showDuplicates && q.trim()}
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Search Results: {pagination.total} matches for "{q}"
                        </h3>
                    </div>

                    {#if searchResults.length === 0}
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                            <p class="text-yellow-700 dark:text-yellow-500">No results found for "{q}"</p>
                        </div>
                    {:else}
                        <div class="space-y-3">
                            {#each searchResults as line (line.id)}
                                <Card variant="outline" padding="sm">
                                    <div class="mb-3 text-gray-900 dark:text-gray-100">
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html renderTrustedMarksOnly(line.highlighted_text)}
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        {#if line.character_name}
                                            <span class="rounded-full bg-green-100 px-2 py-1 text-green-800 dark:bg-green-900/50 dark:text-green-200">
                                                {line.character_name}
                                            </span>
                                        {/if}
                                        {#if line.context}
                                            <span class="rounded-full bg-blue-100 px-2 py-1 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                                {line.context}
                                            </span>
                                        {/if}
                                    </div>
                                    {#if line.file_path}
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">
                                                {line.game?.name}({line.version?.version}) -{line.file_path}
                                                {line.line_number ? `:${line.line_number}` : ''}
                                            </span>
                                        </div>
                                    {/if}
                                    {#if line.first_seen_version}
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            First seen in version {line.first_seen_version.version}{line.first_seen_version.published_at
                                                ? ` (${line.first_seen_version.published_at})`
                                                : ''}
                                        </div>
                                    {/if}
                                </Card>
                            {/each}
                        </div>
                    {/if}

                    {#if pagination.total > 0}
                        <div class="mt-4">
                            <Pagination
                                meta={{
                                    current_page: currentPage,
                                    last_page: pagination.last_page || Math.ceil(pagination.total / perPage),
                                    total: pagination.total,
                                    from: (currentPage - 1) * perPage + 1,
                                    to: Math.min(currentPage * perPage, pagination.total),
                                }}
                                {loading}
                                label="results"
                                onChange={(p) => onChangePage(p)}
                            />
                        </div>
                    {/if}
                {/if}

                {#if !q.trim() && !showDuplicates}
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-700/30">
                        <p class="text-gray-500 dark:text-gray-400">
                            Enter a search term to find dialogue or use the "Show Duplicates" button to see repeated lines
                        </p>
                    </div>
                {/if}
            {/if}
        </Card>
    </div>
</div>
