<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import EditableGameContent from '@/components/editor/EditableGameContent.svelte';
    import EditableGameName from '@/components/editor/EditableGameName.svelte';
    import GameCardUserSection from '@/components/GameCardUserSection.svelte';
    import PlatformLink from '@/components/game-card/PlatformLink.svelte';
    import { Card } from '@/components/ui';
    import { gameCoverAltText } from '@/utils/imageAltText';

    interface GameHeaderProps {
        game: any;
        isAuthenticated: boolean;
        currentThumbnail: string | null;
        activePlatforms: Record<string, boolean>;
        editPermissions: { canEdit: boolean; hasCustomPage: boolean; isOwner: boolean; isAdmin: boolean };
        previewingVisitorView: boolean;
        visitorName: string;
        visitorDescription: string;
        isUploadingThumbnail: boolean;
        onThumbnailUpload: (file: File) => void;
        onPreviewingVisitorViewChange: (previewing: boolean) => void;
        onViewModeUpdate: (data: {
            view_mode?: 'custom' | 'original';
            effective_name?: string | null;
            effective_description?: string | null;
            effective_screenshots?: unknown[];
        }) => void;
        onNameUpdate: (name: string) => void;
        onContentUpdate: (content: string) => void;
    }

    let {
        game,
        isAuthenticated,
        currentThumbnail,
        activePlatforms,
        editPermissions,
        previewingVisitorView,
        visitorName,
        visitorDescription,
        isUploadingThumbnail,
        onThumbnailUpload,
        onPreviewingVisitorViewChange,
        onViewModeUpdate,
        onNameUpdate,
        onContentUpdate,
    }: GameHeaderProps = $props();

    let editControlsContainer = $state<HTMLElement | undefined>(undefined);
</script>

<Card padding="lg" class="mb-6">
    <div class="flex flex-col gap-6 md:flex-row">
        {#if game.is_visible && currentThumbnail}
            <div class="group relative shrink-0">
                <img
                    src={currentThumbnail}
                    alt={gameCoverAltText(game.name)}
                    class="max-h-52 max-w-64 rounded-lg {game.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                />
                {#if editPermissions.canEdit}
                    <label
                        class="absolute top-2 right-2 cursor-pointer rounded-full bg-blue-600 p-2 text-white shadow-lg transition-colors hover:bg-blue-700"
                    >
                        {#if isUploadingThumbnail}
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582M20 20v-5h-.581M5.8 9A7 7 0 0118 7.38M18.2 15A7 7 0 016 16.62"
                                />
                            </svg>
                        {:else}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                />
                            </svg>
                        {/if}
                        <input
                            type="file"
                            accept="image/*"
                            class="hidden"
                            onchange={(e) => {
                                const file = (e.target as HTMLInputElement).files?.[0];
                                if (file) {
                                    onThumbnailUpload(file);
                                }
                            }}
                        />
                    </label>
                {/if}
            </div>
        {/if}

        <div class="flex-1">
            <div class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div class="group min-w-0 flex-1">
                    {#if editPermissions.canEdit}
                        <EditableGameName {game} {previewingVisitorView} previewName={visitorName} {onNameUpdate} />
                    {:else}
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{game.effective_name}</h1>
                    {/if}
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    {#if game.primary_url}
                        <PlatformLink
                            url={game.primary_url}
                            platform={game.platform}
                            gameId={game.id}
                            class="inline-flex items-center gap-2 font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                        />
                    {/if}
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    {#if Object.values(activePlatforms).some(Boolean)}
                        <div class="flex items-center gap-2 text-lg">
                            {#if activePlatforms.windows}<i class="icon-windows text-platform-windows" title="Windows"></i>{/if}
                            {#if activePlatforms.linux}<i class="icon-linux text-platform-linux" title="Linux"></i>{/if}
                            {#if activePlatforms.mac}<i class="icon-apple text-platform-mac" title="Mac"></i>{/if}
                            {#if activePlatforms.android}<i class="icon-android text-platform-android" title="Android"></i>{/if}
                            {#if activePlatforms.web}<i class="icon-web text-platform-web" title="Web"></i>{/if}
                        </div>
                    {/if}

                    {#if game.is_nsfw}
                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">NSFW</span>
                    {/if}
                    {#if game.is_delisted}
                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                            >Delisted</span
                        >
                    {/if}
                    {#if game.is_on_sale}
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            Sale{typeof game.discount_percentage === 'number' ? ` -${game.discount_percentage}%` : ''}
                        </span>
                    {/if}
                    {#if game.is_paid}
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {#if game.is_on_sale && game.formatted_current_price && game.formatted_original_price}
                                <span class="mr-1 text-blue-500 line-through dark:text-blue-400">{game.formatted_original_price}</span>
                                {game.formatted_current_price}
                            {:else}
                                {game.formatted_current_price || 'Paid'}
                            {/if}
                        </span>
                    {/if}
                    {#if game.has_demo}
                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200"
                            >Demo</span
                        >
                    {/if}
                </div>
                <div id="edit-controls-container" bind:this={editControlsContainer}></div>
            </div>

            {#if game.authors}
                <div class="mb-3 text-gray-600 dark:text-gray-300">
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    <div>{@html game.authors}</div>
                </div>
            {/if}

            <div class="group">
                {#if editPermissions.canEdit}
                    <EditableGameContent
                        {game}
                        controlsTarget={editControlsContainer}
                        {previewingVisitorView}
                        previewContent={visitorDescription}
                        onPreviewingVisitorViewChange={(previewing) => {
                            onPreviewingVisitorViewChange(previewing);
                        }}
                        {onViewModeUpdate}
                        {onContentUpdate}
                    />
                {:else if game.is_visible && (game.effective_description || game.full_description || game.description)}
                    <div class="game_description prose max-w-none dark:prose-invert">
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        {@html game.effective_description || game.full_description || game.description || ''}
                    </div>
                {/if}
            </div>
        </div>
    </div>
    <!-- User Section (outside flex row, inside card) -->
    {#if isAuthenticated}
        <div class="mt-4">
            <GameCardUserSection
                gameId={game.id}
                gameName={game.effective_name}
                isPaid={game.is_paid}
                userProgress={(game as any).user_progress ?? null}
            />
        </div>
    {:else}
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <Link href={route('login')} class="text-blue-600 underline underline-offset-2 dark:text-blue-400">Log in</Link>
                    to track your reading progress
                </div>
            </div>
        </div>
    {/if}
</Card>
