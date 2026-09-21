<script lang="ts">
    import ArrowUpTrayIcon from '@/components/icons/ArrowUpTray.svelte';
    import PanelLeftIcon from '@/components/icons/PanelLeft.svelte';
    import { Button, Checkbox, Select, TextInput } from '@/components/ui';

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
            aria-label="Game version"
            class="cursor-pointer rounded-md border border-border-input bg-surface-alt px-3 py-1.5 text-sm text-fg transition-colors focus:border-fg-muted focus:outline-none"
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
        <Select
            aria-label="Language"
            class="w-auto py-1.5"
            value={selectedLanguage ?? ''}
            onchange={(e) => onChangeLanguage(e.currentTarget.value || null)}
            disabled={isLoading}
        >
            <option value="">Original</option>
            {#each visibleLanguages as lang (lang)}
                <option value={lang} selected={lang === selectedLanguage}>
                    {lang.toUpperCase()}
                </option>
            {/each}
        </Select>
    {/if}

    <TextInput
        type="text"
        placeholder="Search nodes..."
        aria-label="Search nodes"
        value={searchQuery}
        oninput={(e) => onSearch(e.currentTarget.value)}
        class="py-1.5"
        fieldClass="w-48"
    />

    {#if canInspectFullRouteMap}
        <div
            class="rounded-md border border-border-input bg-surface-alt px-3 py-1.5 transition-colors hover:border-fg-muted"
            title="Show labels that are present in the script but unreachable from start"
        >
            <Checkbox
                label="Show unreachable"
                checked={includeUnreachable}
                disabled={isLoading}
                onchange={(e) => onToggleUnreachable(e.currentTarget.checked)}
            />
        </div>
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
            tone="neutral"
            size="icon-sm"
            class={seenCount > 0 ? 'border-green-600 text-green-700 dark:border-green-500 dark:text-green-400' : ''}
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
            <span class="text-xs text-green-700 dark:text-green-400">
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
