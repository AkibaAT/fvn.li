<script lang="ts">
    import ArrowUpTrayIcon from '@/components/icons/ArrowUpTray.svelte';
    import StarIcon from '@/components/icons/Star.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import EditableGameContent from '@/components/editor/EditableGameContent.svelte';
    import EditableGameName from '@/components/editor/EditableGameName.svelte';
    import GameCardUserSection from '@/components/GameCardUserSection.svelte';
    import PlatformLink from '@/components/game-card/PlatformLink.svelte';
    import GameFlags from '@/components/games/GameFlags.svelte';
    import GlyphStrip from '@/components/games/GlyphStrip.svelte';
    import { Button, Card } from '@/components/ui';
    import { gameCoverAltText } from '@/utils/imageAltText';
    import { gamesFilterUrl } from '@/utils/game-show';
    import type { GameCardPlatform } from '@/hooks/usePlatformIcons';
    import type { GameFact, SupportedLanguage } from '@/types/game-show';

    interface GameHeaderProps {
        game: any;
        isAuthenticated: boolean;
        currentThumbnail: string | null;
        activePlatforms: Record<GameCardPlatform, boolean>;
        supportedLanguages: SupportedLanguage[];
        facts: GameFact[];
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
        supportedLanguages,
        facts,
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
    let thumbnailInput = $state<HTMLInputElement | null>(null);

    const platformList = $derived((Object.keys(activePlatforms) as GameCardPlatform[]).filter((platform) => activePlatforms[platform]));
    const languageList = $derived(supportedLanguages.map((sl) => sl.language));
    const hasRating = $derived(typeof game.rating_score === 'number' && game.rating_score > 0);
    const description = $derived(game.effective_description || game.full_description || game.description || '');
    const tags = $derived((game.tags ?? []) as Array<{ id: number; name: string; slug: string }>);

    const visitFilter = (filters: Parameters<typeof gamesFilterUrl>[0]) => router.visit(gamesFilterUrl(filters));
</script>

<Card variant="flat" padding="lg" class="mb-6">
    <div class="flex flex-col gap-6 md:flex-row">
        {#if game.is_visible && (currentThumbnail || editPermissions.canEdit)}
            <div class="relative shrink-0 self-start">
                {#if currentThumbnail}
                    <img
                        src={currentThumbnail}
                        alt={gameCoverAltText(game.name)}
                        class="max-h-52 max-w-64 rounded-md border border-border {game.platform === 'steam' ? 'object-contain' : 'object-cover'}"
                    />
                {:else}
                    <div class="flex h-36 w-64 items-center justify-center rounded-md border border-border bg-surface-alt text-ui text-fg-muted">
                        No thumbnail
                    </div>
                {/if}
                {#if editPermissions.canEdit}
                    <Button
                        type="button"
                        size="icon-sm"
                        class="absolute top-2 right-2"
                        loading={isUploadingThumbnail}
                        title="Change thumbnail"
                        ariaLabel="Change thumbnail"
                        onclick={() => thumbnailInput?.click()}
                    >
                        {#if !isUploadingThumbnail}<ArrowUpTrayIcon class="h-4 w-4" />{/if}
                    </Button>
                    <input
                        bind:this={thumbnailInput}
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        tabindex="-1"
                        aria-label="Upload thumbnail"
                        disabled={isUploadingThumbnail}
                        onchange={(e) => {
                            const input = e.target as HTMLInputElement;
                            const file = input.files?.[0];
                            if (file) onThumbnailUpload(file);
                            input.value = '';
                        }}
                    />
                {/if}
            </div>
        {/if}

        <div class="min-w-0 flex-1">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="group min-w-0 flex-1">
                    {#if editPermissions.canEdit}
                        <EditableGameName {game} {previewingVisitorView} previewName={visitorName} {onNameUpdate} />
                    {:else}
                        <h1 class="text-display font-bold break-words text-fg">{game.effective_name}</h1>
                    {/if}
                    {#if game.authors}
                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                        <div class="mt-1 text-sm text-fg-muted">{@html game.authors}</div>
                    {/if}
                </div>
                {#if game.primary_url}
                    <PlatformLink url={game.primary_url} platform={game.platform} gameId={game.id} />
                {/if}
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2">
                <GameFlags
                    {game}
                    onStatusClick={(status) => visitFilter({ selectedStatuses: [status] })}
                    onNsfwToggle={() => visitFilter({ nsfw: true })}
                    onPaidToggle={() => visitFilter({ showPaid: true })}
                    onDemoToggle={() => visitFilter({ showDemo: true })}
                    onSaleToggle={() => visitFilter({ showSale: true })}
                />
                {#if platformList.length > 0 || languageList.length > 0}
                    <GlyphStrip
                        platforms={platformList}
                        languages={languageList}
                        onPlatformClick={(platform) => visitFilter({ selectedPlatforms: [platform] })}
                        onLanguageClick={(iso) => visitFilter({ selectedLanguages: [iso] })}
                        languageLimit="full"
                        class="flex-wrap gap-y-1.5 overflow-visible"
                    />
                {/if}
            </div>

            {#if game.is_visible}
                <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-border pt-4 sm:grid-cols-3 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-fg-faint">Rating</dt>
                        <dd class="mt-0.5 text-sm font-medium text-fg">
                            {#if hasRating}
                                <span class="inline-flex items-center gap-1">
                                    <StarIcon class="h-3.5 w-3.5 fill-current text-accent" />
                                    <span class="font-semibold">{game.rating_score.toFixed(1)}</span>
                                    {#if game.rating_count > 0}
                                        <span class="font-normal text-fg-faint">
                                            ({game.rating_count.toLocaleString()}
                                            {game.rating_count === 1 ? 'rating' : 'ratings'})
                                        </span>
                                    {/if}
                                </span>
                            {:else}
                                <span class="font-normal text-fg-faint">No ratings yet</span>
                            {/if}
                        </dd>
                    </div>
                    {#each facts as fact (fact.label)}
                        <div>
                            <dt class="text-xs text-fg-faint">{fact.label}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-fg">
                                {fact.value}
                                {#if fact.hint}<span class="font-normal text-fg-faint">{fact.hint}</span>{/if}
                            </dd>
                        </div>
                    {/each}
                    <div>
                        <dt class="text-xs text-fg-faint">Price</dt>
                        <dd class="mt-0.5 text-sm font-medium text-fg">
                            {#if !game.is_paid}
                                Free
                            {:else if game.is_on_sale && game.formatted_current_price && game.formatted_original_price}
                                <span class="mr-1 font-normal text-fg-faint line-through">{game.formatted_original_price}</span>
                                {game.formatted_current_price}
                                {#if typeof game.discount_percentage === 'number'}
                                    <span class="font-normal text-fg-faint">(-{game.discount_percentage}%)</span>
                                {/if}
                            {:else}
                                {game.formatted_current_price || 'Paid'}
                            {/if}
                        </dd>
                    </div>
                </dl>

                {#if tags.length > 0}
                    <p class="mt-4 text-ui leading-relaxed text-fg-muted">
                        <span class="sr-only">Tags:</span>
                        {#each tags as tag, index (tag.id)}
                            {#if index > 0}<span class="text-fg-faint">, </span>{/if}
                            <Link href={route('tags.show', tag.slug)} class="hover:text-fg hover:underline">{tag.name}</Link>
                        {/each}
                    </p>
                {/if}
            {/if}
        </div>
    </div>

    {#if isAuthenticated}
        <div class="mt-5 border-t border-border">
            <GameCardUserSection
                gameId={game.id}
                gameName={game.effective_name}
                isPaid={game.is_paid}
                userProgress={game.user_progress?.[0] ?? null}
                listMemberships={game.user_list_memberships ?? []}
            />
        </div>
    {:else}
        <p class="mt-5 border-t border-border pt-4 text-ui text-fg-muted">
            <Link href={route('login')} class="text-fg underline underline-offset-2">Log in</Link>
            to track your reading progress
        </p>
    {/if}

    {#if editPermissions.canEdit || (game.is_visible && description)}
        <div class="group mt-5 border-t border-border pt-5">
            <div id="edit-controls-container" class="mb-3 flex justify-end empty:hidden" bind:this={editControlsContainer}></div>
            {#if editPermissions.canEdit}
                <EditableGameContent
                    {game}
                    controlsTarget={editControlsContainer}
                    {previewingVisitorView}
                    previewContent={visitorDescription}
                    {onPreviewingVisitorViewChange}
                    {onViewModeUpdate}
                    {onContentUpdate}
                />
            {:else}
                <div class="game_description prose max-w-none dark:prose-invert">
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    {@html description}
                </div>
            {/if}
        </div>
    {/if}
</Card>
