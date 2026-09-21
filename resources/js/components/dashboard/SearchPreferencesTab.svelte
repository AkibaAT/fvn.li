<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import { Link } from '@inertiajs/svelte';
    import { unignoreGame, updateExcludedTags, updateLanguagePreferences } from '@/api/user-preferences';
    import { getErrorMessage, useAsyncAction } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Badge, Button, Card, TextInput } from '@/components/ui';

    interface IgnoredGame {
        id: number;
        name: string;
        slug: string;
        thumb_url?: string;
        optimized_thumbnails?: { default?: { path: string; width: number; height: number } };
        platform?: 'itch_io' | 'steam' | 'other';
    }

    interface SearchPreferencesTabProps {
        languagePreferences: string[];
        availableLanguages: Record<string, { ref_name: string; flag_code: string }>;
        excludedTagPreferences: number[];
        availableTags: Record<string, string>;
        ignoredGamesInitial: IgnoredGame[];
        ignoredGamesCountInitial: number;
    }

    let {
        languagePreferences: languagePreferencesInitial,
        availableLanguages,
        excludedTagPreferences: excludedTagPreferencesInitial,
        availableTags,
        ignoredGamesInitial,
        ignoredGamesCountInitial,
    }: SearchPreferencesTabProps = $props();

    let languageDraft = $state<string[] | null>(null);
    const selectedLanguages = $derived(languageDraft ?? languagePreferencesInitial);
    const saveLanguagesAction = useAsyncAction();
    let excludedTagDraft = $state<number[] | null>(null);
    const excludedTags = $derived(excludedTagDraft ?? excludedTagPreferencesInitial);
    const saveExcludedTagsAction = useAsyncAction();
    let tagSearch = $state('');
    const ignoredGames = $derived(ignoredGamesInitial);
    const ignoredGamesCount = $derived(ignoredGamesCountInitial);
    let removingGameIds = $state<number[]>([]);

    const refreshPreferences = refreshPage;

    const handleUnignoreGame = async (gameId: number) => {
        if (removingGameIds.includes(gameId)) return;
        removingGameIds = [...removingGameIds, gameId];
        try {
            await unignoreGame(gameId);
            if (!(await refreshPreferences(['ignoredGames', 'ignoredGamesCount']))) return;
            toast.success('Game removed from ignore list');
        } catch (error) {
            console.error('Failed to unignore game:', error);
            toast.error(getErrorMessage(error, 'Failed to remove game from ignore list'));
        } finally {
            removingGameIds = removingGameIds.filter((id) => id !== gameId);
        }
    };

    const toggleLanguagePreference = (isoCode: string) => {
        languageDraft = selectedLanguages.includes(isoCode) ? selectedLanguages.filter((l) => l !== isoCode) : [...selectedLanguages, isoCode];
    };

    const saveLanguagePreferences = async () => {
        if (saveLanguagesAction.isLoading) return;
        const saved = await saveLanguagesAction.run(
            async () => {
                await updateLanguagePreferences(selectedLanguages);
                return refreshPreferences(['languagePreferences']);
            },
            { fallbackError: 'Failed to save language preferences' },
        );
        if (!saved) return;
        languageDraft = null;
        toast.success('Language preferences saved');
    };

    const toggleExcludedTag = (tagId: number) => {
        excludedTagDraft = excludedTags.includes(tagId) ? excludedTags.filter((id) => id !== tagId) : [...excludedTags, tagId];
    };

    const saveExcludedTags = async () => {
        if (saveExcludedTagsAction.isLoading) return;
        const saved = await saveExcludedTagsAction.run(
            async () => {
                await updateExcludedTags(excludedTags);
                return refreshPreferences(['excludedTagPreferences']);
            },
            { fallbackError: 'Failed to save excluded tags' },
        );
        if (!saved) return;
        excludedTagDraft = null;
        toast.success('Excluded tags saved');
    };

    const filteredTags = $derived(
        Object.entries(availableTags || {})
            .filter(([, label]) => !tagSearch || label.toLowerCase().includes(tagSearch.toLowerCase()))
            .sort(([, a], [, b]) => a.localeCompare(b, undefined, { sensitivity: 'base' })),
    );
</script>

<div class="space-y-6">
    <p class="text-sm text-fg-muted">Customize how search results are filtered for you. These preferences apply across the site by default.</p>

    <Card variant="flat" padding="lg">
        <h2 class="mb-4 text-title font-semibold text-fg">Language Preferences</h2>
        <p class="mb-3 text-sm text-fg-muted">
            Set your preferred languages to auto-filter the games list. When set, the games page will show only games available in these languages by
            default.
        </p>
        <div class="flex flex-wrap gap-2">
            {#each Object.entries(availableLanguages) as [iso, lang] (iso)}
                <Button
                    type="button"
                    variant={selectedLanguages.includes(iso) ? 'solid' : 'soft'}
                    tone={selectedLanguages.includes(iso) ? 'primary' : 'neutral'}
                    onclick={() => toggleLanguagePreference(iso)}
                    disabled={saveLanguagesAction.isLoading}
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                >
                    <span class="fi fi-{lang.flag_code} mr-1 rounded-sm"></span>
                    {lang.ref_name}
                </Button>
            {/each}
        </div>
        <div class="mt-4">
            <Button
                type="button"
                variant="solid"
                tone="primary"
                onclick={saveLanguagePreferences}
                disabled={saveLanguagesAction.isLoading}
                loading={saveLanguagesAction.isLoading}
            >
                {saveLanguagesAction.isLoading ? 'Saving...' : 'Save Preferences'}
            </Button>
        </div>
    </Card>

    <Card variant="flat" padding="lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-title font-semibold text-fg">Excluded Tags</h2>
            {#if excludedTags.length > 0}
                <Badge tone="danger">{excludedTags.length} excluded</Badge>
            {/if}
        </div>
        <p class="mb-3 text-sm text-fg-muted">
            Select tags to exclude from game search results by default. Games with any of these tags will be hidden unless you explicitly include
            them.
        </p>
        <TextInput type="text" bind:value={tagSearch} placeholder="Search tags..." aria-label="Search tags" fieldClass="mb-3" />
        <div class="flex max-h-64 flex-wrap gap-2 overflow-y-auto">
            {#each filteredTags as [tagId, label] (tagId)}
                <Button
                    type="button"
                    variant={excludedTags.includes(Number(tagId)) ? 'solid' : 'soft'}
                    tone={excludedTags.includes(Number(tagId)) ? 'danger' : 'neutral'}
                    onclick={() => toggleExcludedTag(Number(tagId))}
                    disabled={saveExcludedTagsAction.isLoading}
                    class="rounded-full px-3 py-1 text-sm transition-colors"
                >
                    {label}
                </Button>
            {/each}
        </div>
        <div class="mt-4 flex gap-2">
            <Button
                type="button"
                variant="solid"
                tone="primary"
                onclick={saveExcludedTags}
                disabled={saveExcludedTagsAction.isLoading}
                loading={saveExcludedTagsAction.isLoading}
            >
                {saveExcludedTagsAction.isLoading ? 'Saving...' : 'Save Preferences'}
            </Button>
            {#if excludedTags.length > 0}
                <Button
                    type="button"
                    variant="soft"
                    tone="neutral"
                    disabled={saveExcludedTagsAction.isLoading}
                    onclick={() => {
                        excludedTagDraft = [];
                        saveExcludedTags();
                    }}
                >
                    Clear All
                </Button>
            {/if}
        </div>
    </Card>

    <Card variant="flat" padding="lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-title font-semibold text-fg">Ignored Games</h2>
            <Badge tone="neutral">{ignoredGamesCount} game{ignoredGamesCount !== 1 ? 's' : ''}</Badge>
        </div>
        <p class="mb-3 text-sm text-fg-muted">
            Games you've ignored won't appear in search results by default. You can manage your ignored games here.
        </p>
        {#if ignoredGames.length > 0}
            <div class="space-y-2">
                {#each ignoredGames as game (game.id)}
                    <div class="flex items-center justify-between rounded-lg border border-border bg-surface-alt p-2">
                        <Link href={route('games.show', game.slug)} class="truncate text-sm text-fg-muted hover:text-fg hover:underline"
                            >{game.name}</Link
                        >
                        <Button
                            type="button"
                            variant="link"
                            tone="danger"
                            onclick={() => handleUnignoreGame(game.id)}
                            disabled={removingGameIds.includes(game.id)}
                            class="ml-2">Remove</Button
                        >
                    </div>
                {/each}
            </div>
        {:else}
            <div class="py-6 text-center">
                <div class="text-sm font-medium text-fg-muted">No ignored games</div>
                <div class="text-xs text-fg-faint">
                    You haven't ignored any games yet. Click the ignore button on any game card to hide it from search results.
                </div>
            </div>
        {/if}
    </Card>
</div>
