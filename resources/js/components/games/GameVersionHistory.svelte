<script lang="ts">
    import ArrowDownTrayIcon from '@/components/icons/ArrowDownTray.svelte';
    import Pagination from '@/components/Pagination.svelte';
    import CharacterStatsModal from '@/components/CharacterStatsModal.svelte';
    import FileStatsModal from '@/components/FileStatsModal.svelte';
    import { Button, Card, Select } from '@/components/ui';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { getVersionWordCount } from '@/utils/game-show';
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
        versionOptimizedArchiveAvailability,
        canDownloadOptimizedArchives,
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
        versionOptimizedArchiveAvailability: Record<number, boolean>;
        canDownloadOptimizedArchives: boolean;
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

    const { getAllPlatforms, getPlatformIcon } = usePlatformIcons();

    const comparableVersions = $derived(currentVersions.filter((version) => versionCharacterCounts[version.id] > 0));
    const canCompare = $derived(Boolean(compareFromVersionId && compareToVersionId && compareFromVersionId !== compareToVersionId));

    function parseVersionId(value: string): number | null {
        return value === '' ? null : Number(value);
    }

    function versionOptionLabel(version: GameVersion): string {
        return `${version.version} (${formatLocalDate(version.published_at) ?? '—'})`;
    }

    function versionPlatforms(version: GameVersion): GameCardPlatform[] {
        return getAllPlatforms().filter((platform) => version[`is_${platform}`]);
    }

    function versionLanguages(version: GameVersion): SupportedLanguage[] {
        return (version.supportedLanguages ?? [])
            .filter((language) => language.is_available)
            .sort((a, b) => a.language.ref_name.localeCompare(b.language.ref_name));
    }
</script>

{#if currentVersions.length > 0}
    <Card id="versions" variant="flat" padding="lg" class="mb-6 scroll-mt-32">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-title font-semibold text-fg">
                Version History
                {#if pagination.total > 1}<span class="ml-1 text-sm font-normal text-fg-faint">{pagination.total}</span>{/if}
            </h2>
            {#if latestVersion && (canBrowseLatestDialogue || latestVersionHasRouteMap)}
                <div class="flex flex-wrap gap-2">
                    {#if canBrowseLatestDialogue}
                        <Button href={route('dialogue.browser', { game: gameSlug, versionId: latestVersion.id })} inertia={false} size="sm">
                            Browse dialogue
                        </Button>
                    {/if}
                    {#if latestVersionHasRouteMap}
                        <Button href={route('games.route-map', { game: gameSlug })} inertia={false} variant="outline" tone="neutral" size="sm">
                            Route map
                        </Button>
                    {/if}
                </div>
            {/if}
        </div>

        {#if comparableVersions.length > 1}
            <div class="mb-4 flex flex-wrap items-end gap-3 rounded-md border border-border bg-surface-alt p-3">
                <Select
                    id="compareFromVersionId"
                    label="Compare from"
                    value={compareFromVersionId ?? ''}
                    onchange={(event) => onCompareFromChange(parseVersionId((event.currentTarget as HTMLSelectElement).value))}
                    class="bg-surface py-1.5 text-ui"
                    fieldClass="min-w-44 flex-1 sm:flex-none"
                >
                    <option value="">Select version…</option>
                    {#each comparableVersions as version (version.id)}
                        <option value={version.id}>{versionOptionLabel(version)}</option>
                    {/each}
                </Select>
                <Select
                    id="compareToVersionId"
                    label="To"
                    value={compareToVersionId ?? ''}
                    onchange={(event) => onCompareToChange(parseVersionId((event.currentTarget as HTMLSelectElement).value))}
                    class="bg-surface py-1.5 text-ui"
                    fieldClass="min-w-44 flex-1 sm:flex-none"
                >
                    <option value="">Select version…</option>
                    {#each comparableVersions as version (version.id)}
                        <option value={version.id}>{versionOptionLabel(version)}</option>
                    {/each}
                </Select>
                <Button type="button" size="sm" onclick={onCompare} disabled={!canCompare}>Compare</Button>
            </div>
        {/if}

        <ul class="divide-y divide-border">
            {#each currentVersions as version (version.id)}
                {@const platforms = versionPlatforms(version)}
                {@const languages = versionLanguages(version)}
                {@const wordCount = getVersionWordCount(version)}
                {@const hasRouteData = versionHasRouteData[version.id] === true || version.has_route_data === true}
                {@const canDownloadArchive = canDownloadOptimizedArchives && versionOptimizedArchiveAvailability[version.id] === true}
                {@const statsBusy = characterStatsLoading === version.id || fileStatsLoading === version.id}
                <li class="py-3 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="min-w-0 flex-1 basis-48">
                            <span class="text-md font-medium text-fg">{version.version}</span>
                            <span class="ml-2 text-ui text-fg-faint">{formatLocalDate(version.published_at)}</span>
                        </div>
                        {#if platforms.length > 0 || languages.length > 0}
                            <div class="flex flex-wrap items-center gap-1.5 text-fg-muted">
                                {#each platforms as platform (platform)}
                                    {@const meta = getPlatformIcon(platform)}
                                    {@const Icon = meta.icon}
                                    <Icon class="h-3.5 w-3.5" aria-hidden={false} aria-label={meta.title} />
                                {/each}
                                {#if platforms.length > 0 && languages.length > 0}
                                    <span class="mx-1 h-3 w-px bg-border" aria-hidden="true"></span>
                                {/if}
                                {#each languages as supportedLanguage (supportedLanguage.iso_code)}
                                    <span
                                        class="fi fi-{supportedLanguage.language.flag_code} rounded-sm"
                                        role="img"
                                        aria-label={supportedLanguage.language.ref_name}
                                        title={supportedLanguage.language.ref_name}
                                    ></span>
                                {/each}
                            </div>
                        {/if}
                        {#if wordCount}
                            <span class="text-ui whitespace-nowrap text-fg-muted tabular-nums">{wordCount} words</span>
                        {/if}
                    </div>
                    {#if versionCharacterCounts[version.id] > 0 || hasRouteData || versionHasFileStats[version.id] || canDownloadArchive}
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1">
                            {#if versionCharacterCounts[version.id] > 0}
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    class="p-0"
                                    onclick={() => onLoadCharacterStats(version.id)}
                                    disabled={statsBusy}
                                    loading={characterStatsLoading === version.id}
                                >
                                    {characterStatsLoading === version.id
                                        ? 'Loading...'
                                        : `View ${versionCharacterCounts[version.id]} ${versionCharacterCounts[version.id] === 1 ? 'character' : 'characters'}`}
                                </Button>
                            {/if}
                            {#if versionHasFileStats[version.id]}
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    class="p-0"
                                    onclick={() => onLoadFileStats(version.id)}
                                    disabled={statsBusy}
                                    loading={fileStatsLoading === version.id}
                                >
                                    {fileStatsLoading === version.id ? 'Loading...' : 'View file stats'}
                                </Button>
                            {/if}
                            {#if hasRouteData}
                                <Button
                                    href={route('games.route-map', { game: gameSlug }) + '?version_id=' + version.id}
                                    inertia={false}
                                    variant="link"
                                    size="sm"
                                    class="p-0"
                                >
                                    Route map
                                </Button>
                            {/if}
                            {#if canDownloadArchive}
                                <Button
                                    href={route('my-games.optimized-download', { game: gameSlug, version: version.id })}
                                    inertia={false}
                                    variant="link"
                                    size="sm"
                                    class="p-0"
                                >
                                    {#snippet icon()}<ArrowDownTrayIcon class="h-3.5 w-3.5" />{/snippet}
                                    Download archive
                                </Button>
                            {/if}
                        </div>
                    {/if}

                    <CharacterStatsModal
                        versionId={version.id}
                        {showCharacterStats}
                        {characterStatsData}
                        statsLoading={characterStatsLoading === version.id}
                        closeCharacterStatsDialog={onCloseCharacterStats}
                    />

                    <FileStatsModal
                        versionId={version.id}
                        {showFileStats}
                        {fileStatsData}
                        statsLoading={fileStatsLoading === version.id}
                        closeFileStatsDialog={onCloseFileStats}
                    />
                </li>
            {/each}
        </ul>

        <Pagination layout="full" meta={pagination} onChange={onPageChange} {onPerPageChange} loading={versionsLoading} label="versions" />
    </Card>
{/if}
