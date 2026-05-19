<script lang="ts">
    import AdvancedPagination from '@/components/AdvancedPagination.svelte';
    import CharacterStatsModal from '@/components/CharacterStatsModal.svelte';
    import FileStatsModal from '@/components/FileStatsModal.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Button, Card } from '@/components/ui';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { getLanguageFlag, getVersionWordCount } from '@/utils/game-show';
    import type { GameVersion, PaginationMeta, SupportedLanguage } from '@/types/game-show';

    let {
        gameSlug,
        latestVersion,
        currentVersions,
        pagination,
        canBrowseLatestDialogue,
        latestVersionHasRouteMap,
        versionCharacterCounts,
        versionHasFileStats,
        versionHasRouteData,
        compareFromVersionId,
        compareToVersionId,
        characterStatsLoading,
        fileStatsLoading,
        showCharacterStats,
        showFileStats,
        characterStatsData,
        fileStatsData,
        versionsLoading,
        onCompareFromChange,
        onCompareToChange,
        onCompare,
        onLoadCharacterStats,
        onLoadFileStats,
        onCloseCharacterStats,
        onCloseFileStats,
        onPageChange,
        onPerPageChange,
    }: {
        gameSlug: string;
        latestVersion?: GameVersion;
        currentVersions: GameVersion[];
        pagination: PaginationMeta;
        canBrowseLatestDialogue: boolean;
        latestVersionHasRouteMap: boolean;
        versionCharacterCounts: Record<number, number>;
        versionHasFileStats: Record<number, boolean>;
        versionHasRouteData: Record<number, boolean>;
        compareFromVersionId: number | null;
        compareToVersionId: number | null;
        characterStatsLoading: number | null;
        fileStatsLoading: number | null;
        showCharacterStats: number | null;
        showFileStats: number | null;
        characterStatsData: any;
        fileStatsData: any;
        versionsLoading: boolean;
        onCompareFromChange: (versionId: number | null) => void;
        onCompareToChange: (versionId: number | null) => void;
        onCompare: () => void;
        onLoadCharacterStats: (versionId: number) => void;
        onLoadFileStats: (versionId: number) => void;
        onCloseCharacterStats: (versionId: number) => void;
        onCloseFileStats: (versionId: number) => void;
        onPageChange: (page: number) => void;
        onPerPageChange: (perPage: number) => void;
    } = $props();

    function parseVersionId(value: string): number | null {
        return value === '' ? null : Number(value);
    }

    function versionOptionLabel(version: GameVersion): string {
        return `${version.version} (${new Date(version.published_at).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        })})`;
    }
</script>

{#if currentVersions.length > 0}
    <Card id="versions" padding="lg" class="mb-6 scroll-mt-28">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Version History</h2>

        {#if latestVersion && (canBrowseLatestDialogue || latestVersionHasRouteMap)}
            <div class="mb-4 flex gap-3">
                {#if canBrowseLatestDialogue}
                    <a
                        href={route('dialogue.browser', { game: gameSlug, versionId: latestVersion.id })}
                        class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition hover:bg-blue-500 focus:border-blue-700 focus:ring focus:ring-blue-300 focus:outline-none active:bg-blue-700 disabled:opacity-25"
                    >
                        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                            />
                        </svg>
                        Browse Dialogue
                    </a>
                {/if}
                {#if latestVersionHasRouteMap}
                    <a
                        href={route('games.route-map', { game: gameSlug })}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition hover:bg-gray-50 focus:border-gray-500 focus:ring focus:ring-gray-300 focus:outline-none active:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        <svg class="mr-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Route Map
                    </a>
                {/if}
            </div>
        {/if}

        <Card variant="outline" padding="sm" class="my-3">
            <h3 class="mb-3 text-base font-medium text-gray-900 dark:text-gray-100">Compare Versions</h3>
            <div class="flex flex-col items-end gap-4 sm:flex-row">
                <div>
                    <label for="compareFromVersionId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">From Version</label>
                    <select
                        id="compareFromVersionId"
                        value={compareFromVersionId ?? ''}
                        onchange={(event) => onCompareFromChange(parseVersionId((event.currentTarget as HTMLSelectElement).value))}
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">Select version...</option>
                        {#each currentVersions as version (version.id)}
                            {#if versionCharacterCounts[version.id] > 0}
                                <option value={version.id}>{versionOptionLabel(version)}</option>
                            {/if}
                        {/each}
                    </select>
                </div>
                <div>
                    <label for="compareToVersionId" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">To Version</label>
                    <select
                        id="compareToVersionId"
                        value={compareToVersionId ?? ''}
                        onchange={(event) => onCompareToChange(parseVersionId((event.currentTarget as HTMLSelectElement).value))}
                        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2 text-gray-900 sm:w-auto dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="">Select version...</option>
                        {#each currentVersions as version (version.id)}
                            {#if versionCharacterCounts[version.id] > 0}
                                <option value={version.id}>{versionOptionLabel(version)}</option>
                            {/if}
                        {/each}
                    </select>
                </div>
                <div>
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={onCompare}
                        disabled={!compareFromVersionId || !compareToVersionId || compareFromVersionId === compareToVersionId}
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        COMPARE
                    </Button>
                </div>
            </div>
        </Card>

        <div class="space-y-4">
            {#each currentVersions as version (version.id)}
                <Card variant="outline" padding="sm">
                    <div class="flex flex-col gap-4 sm:flex-row">
                        <div class="flex flex-1 flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <div class="flex w-full items-center">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{formatLocalDate(version.published_at)}</div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="font-medium text-gray-900 dark:text-gray-100">Version {version.version}</div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="flex flex-wrap gap-1">
                                    {#each (version.supportedLanguages || [])
                                        .filter((language: SupportedLanguage) => language.is_available)
                                        .sort( (a: SupportedLanguage, b: SupportedLanguage) => a.language.ref_name.localeCompare(b.language.ref_name), ) as supportedLanguage (supportedLanguage.iso_code)}
                                        <img
                                            src={getLanguageFlag(supportedLanguage.language.flag_code)}
                                            alt={supportedLanguage.language.ref_name}
                                            title={supportedLanguage.language.ref_name}
                                            class="h-4 w-4 rounded-sm"
                                        />
                                    {/each}
                                </div>
                            </div>
                            <div class="flex w-full items-center">
                                <div class="flex gap-2 text-lg">
                                    {#if version.is_windows}<i class="icon-windows text-platform-windows" title="Windows"></i>{/if}
                                    {#if version.is_linux}<i class="icon-linux text-platform-linux" title="Linux"></i>{/if}
                                    {#if version.is_mac}<i class="icon-apple text-platform-mac" title="Mac"></i>{/if}
                                    {#if version.is_android}<i class="icon-android text-platform-android" title="Android"></i>{/if}
                                    {#if version.is_web}<i class="icon-web text-platform-web" title="Web"></i>{/if}
                                </div>
                            </div>
                            <div class="flex w-full items-center text-sm whitespace-nowrap sm:w-auto">
                                <span class="text-gray-700 dark:text-gray-300">Words:</span>
                                <span class="ml-1 text-gray-900 dark:text-gray-100">{getVersionWordCount(version)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2">
                        {#if versionCharacterCounts[version.id] > 0}
                            <Button
                                type="button"
                                variant="link"
                                tone="primary"
                                onclick={() => onLoadCharacterStats(version.id)}
                                disabled={characterStatsLoading === version.id || fileStatsLoading === version.id}
                                loading={characterStatsLoading === version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                            >
                                {#if characterStatsLoading === version.id}
                                    <LoadingSpinner size="sm" />
                                    Loading...
                                {:else}
                                    View {versionCharacterCounts[version.id]} Characters
                                {/if}
                            </Button>
                        {/if}
                        {#if versionHasRouteData[version.id] === true || version.has_route_data === true}
                            <a
                                href={route('games.route-map', { game: gameSlug }) + '?version_id=' + version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline dark:text-blue-400"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Route Map
                            </a>
                        {/if}
                        {#if versionHasFileStats[version.id]}
                            <Button
                                type="button"
                                variant="link"
                                tone="primary"
                                onclick={() => onLoadFileStats(version.id)}
                                disabled={characterStatsLoading === version.id || fileStatsLoading === version.id}
                                loading={fileStatsLoading === version.id}
                                class="inline-flex items-center gap-2 text-sm text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400"
                            >
                                {#if fileStatsLoading === version.id}
                                    <LoadingSpinner size="sm" />
                                    Loading...
                                {:else}
                                    View File Stats
                                {/if}
                            </Button>
                        {/if}
                    </div>

                    <CharacterStatsModal
                        versionId={version.id}
                        {showCharacterStats}
                        {characterStatsData}
                        statsLoading={characterStatsLoading === version.id}
                        closeCharacterStatsDialog={onCloseCharacterStats}
                        {getLanguageFlag}
                    />

                    <FileStatsModal
                        versionId={version.id}
                        {showFileStats}
                        {fileStatsData}
                        statsLoading={fileStatsLoading === version.id}
                        closeFileStatsDialog={onCloseFileStats}
                    />
                </Card>
            {/each}
        </div>

        <div class="mt-4">
            <AdvancedPagination meta={pagination} {onPageChange} {onPerPageChange} isLoading={versionsLoading} label="versions" />
        </div>
    </Card>
{/if}
