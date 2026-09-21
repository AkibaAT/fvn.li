<script lang="ts">
    import ArrowUpTrayIcon from '@/components/icons/ArrowUpTray.svelte';
    import PanelLeftIcon from '@/components/icons/PanelLeft.svelte';
    import { Button } from '@/components/ui';

    type GameVersionOption = {
        id: number;
        version: string;
    };

    let {
        gameVersions,
        visibleLanguages,
        selectedVersionId,
        selectedLanguage,
        searchQuery,
        canInspectFullRouteMap,
        includeUnreachable,
        isLoading,
        showSidebar,
        seenCount,
        totalNodes,
        totalEdges,
        endingsCount,
        isUploadingSave,
        saveUploadError,
        onLoadVersion,
        onChangeLanguage,
        onSearch,
        onToggleUnreachable,
        onToggleSidebar,
        onUploadSaveFile,
        onClearSeenData,
    }: {
        gameVersions: GameVersionOption[];
        visibleLanguages: string[];
        selectedVersionId: number;
        selectedLanguage: string | null;
        searchQuery: string;
        canInspectFullRouteMap: boolean;
        includeUnreachable: boolean;
        isLoading: boolean;
        showSidebar: boolean;
        seenCount: number;
        totalNodes: number;
        totalEdges: number;
        endingsCount: number;
        isUploadingSave: boolean;
        saveUploadError: string | null;
        onLoadVersion: (versionId: number) => void;
        onChangeLanguage: (language: string | null) => void;
        onSearch: (query: string) => void;
        onToggleUnreachable: (checked: boolean) => void;
        onToggleSidebar: () => void;
        onUploadSaveFile: (file: File) => void;
        onClearSeenData: () => void;
    } = $props();
</script>

<div class="mb-4 flex flex-wrap items-center gap-3">
    {#if gameVersions && gameVersions.length > 1}
        <select
            class="cursor-pointer rounded-md border border-border bg-surface-alt px-3 py-1.5 text-sm text-fg transition-colors focus:border-border-strong focus:outline-none"
            value={selectedVersionId}
            onchange={(e) => {
                const target = e.target as HTMLSelectElement;
                const requestedVersion = Number(target.value);
                target.value = String(selectedVersionId);
                onLoadVersion(requestedVersion);
            }}
            disabled={isLoading}
        >
            {#each gameVersions as version (version.id)}
                <option value={version.id} selected={version.id === selectedVersionId}>
                    v{version.version}
                </option>
            {/each}
        </select>
    {/if}

    {#if visibleLanguages.length > 1}
        <select
            class="cursor-pointer rounded-md border border-border bg-surface-alt px-3 py-1.5 text-sm text-fg transition-colors focus:border-border-strong focus:outline-none"
            value={selectedLanguage ?? ''}
            onchange={(e) => {
                const target = e.target as HTMLSelectElement;
                onChangeLanguage(target.value || null);
            }}
            disabled={isLoading}
        >
            <option value="">Original</option>
            {#each visibleLanguages as lang (lang)}
                <option value={lang} selected={lang === selectedLanguage}>
                    {lang.toUpperCase()}
                </option>
            {/each}
        </select>
    {/if}

    <div class="relative">
        <input
            type="text"
            placeholder="Search nodes..."
            value={searchQuery}
            oninput={(e) => onSearch(e.currentTarget.value)}
            class="w-48 rounded-md border border-border bg-surface-alt px-3 py-1.5 text-sm text-fg transition-colors placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
        />
    </div>

    {#if canInspectFullRouteMap}
        <label
            class="inline-flex items-center gap-2 rounded-md border border-border bg-surface-alt px-3 py-1.5 text-sm text-fg transition-colors hover:border-border-strong"
            title="Show labels that are present in the script but unreachable from start"
        >
            <input
                type="checkbox"
                class="h-4 w-4 shrink-0 cursor-pointer rounded-[3px] border-border bg-surface-alt accent-accent"
                checked={includeUnreachable}
                disabled={isLoading}
                onchange={(e) => onToggleUnreachable((e.currentTarget as HTMLInputElement).checked)}
            />
            <span>Show unreachable</span>
        </label>
    {/if}

    <Button
        type="button"
        variant="outline"
        tone={showSidebar ? 'primary' : 'neutral'}
        size="icon-sm"
        onclick={onToggleSidebar}
        title={showSidebar ? 'Hide details' : 'Show details'}
    >
        <PanelLeftIcon class="h-4 w-4" />
    </Button>

    <div class="relative flex items-center gap-2">
        <Button
            type="button"
            variant="outline"
            tone={seenCount > 0 ? 'success' : 'neutral'}
            size="icon-sm"
            class="rounded-md border px-2 py-1.5 transition-colors {seenCount > 0
                ? 'border-emerald-600 text-emerald-700 dark:border-emerald-500 dark:text-emerald-400'
                : 'border-border-strong bg-surface text-fg hover:border-fg'}"
            onclick={() => document.getElementById('save-upload')?.click()}
            disabled={isUploadingSave}
            loading={isUploadingSave}
            title="Upload Ren'Py save or persistent file to mark seen nodes"
        >
            <ArrowUpTrayIcon class="h-4 w-4" />
        </Button>
        <input
            id="save-upload"
            type="file"
            accept="*"
            class="hidden"
            onchange={(e) => {
                const target = e.target as HTMLInputElement;
                if (target.files?.[0]) {
                    onUploadSaveFile(target.files[0]);
                    target.value = '';
                }
            }}
        />

        {#if seenCount > 0}
            <span class="text-xs text-emerald-700 dark:text-emerald-400">
                {seenCount}/{totalNodes} seen
            </span>
            <Button type="button" variant="link" tone="neutral" size="xs" onclick={onClearSeenData} title="Clear seen data">clear</Button>
        {/if}

        {#if saveUploadError}
            <span class="text-xs text-red-600 dark:text-red-400">{saveUploadError}</span>
        {/if}
    </div>

    <div class="flex gap-3 text-xs">
        <span class="text-fg-faint">
            {totalNodes} nodes, {totalEdges} edges
        </span>

        {#if endingsCount > 0}
            <span class="text-fg-faint">&middot;</span>
            <span class="text-red-600 dark:text-red-400">{endingsCount} endings</span>
        {/if}

        {#if isLoading}
            <span class="text-xs text-fg-faint">Loading...</span>
        {/if}
    </div>
</div>
