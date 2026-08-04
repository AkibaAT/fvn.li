<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import { toast } from '@/utils/toast';
    import { Button, Card } from '@/components/ui';

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

    let selectedLanguages = $state<string[]>(untrack(() => languagePreferencesInitial || []));
    let savingLanguages = $state(false);
    let excludedTags = $state<number[]>(untrack(() => excludedTagPreferencesInitial || []));
    let savingExcludedTags = $state(false);
    let tagSearch = $state('');
    let ignoredGames = $state<IgnoredGame[]>(untrack(() => ignoredGamesInitial || []));
    let ignoredGamesCount = $state(untrack(() => ignoredGamesCountInitial || 0));

    const handleUnignoreGame = async (gameId: number) => {
        try {
            const response = await authenticatedFetch(route('user.ignored-games.destroy'), {
                method: 'DELETE',
                body: JSON.stringify({ game_id: gameId }),
            });
            const data = await response.json();
            if (data.success) {
                ignoredGames = ignoredGames.filter((g) => g.id !== gameId);
                ignoredGamesCount -= 1;
                toast.success('Game removed from ignore list');
            } else {
                toast.error(data.message || 'Failed to remove game from ignore list');
            }
        } catch (error) {
            console.error('Failed to unignore game:', error);
            toast.error('Failed to remove game from ignore list');
        }
    };

    const toggleLanguagePreference = (isoCode: string) => {
        selectedLanguages = selectedLanguages.includes(isoCode) ? selectedLanguages.filter((l) => l !== isoCode) : [...selectedLanguages, isoCode];
    };

    const saveLanguagePreferences = async () => {
        savingLanguages = true;
        try {
            const response = await authenticatedFetch(route('user.language-preferences.update'), {
                method: 'PUT',
                body: JSON.stringify({ preferred_languages: selectedLanguages }),
            });
            const data = await response.json();
            if (data.success) toast.success('Language preferences saved');
            else toast.error(data.message || 'Failed to save language preferences');
        } catch (error) {
            console.error('Failed to save language preferences:', error);
            toast.error('Failed to save language preferences');
        } finally {
            savingLanguages = false;
        }
    };

    const toggleExcludedTag = (tagId: number) => {
        excludedTags = excludedTags.includes(tagId) ? excludedTags.filter((id) => id !== tagId) : [...excludedTags, tagId];
    };

    const saveExcludedTags = async () => {
        savingExcludedTags = true;
        try {
            const response = await authenticatedFetch(route('user.excluded-tags.update'), {
                method: 'PUT',
                body: JSON.stringify({ excluded_tags: excludedTags }),
            });
            const data = await response.json();
            if (data.success) toast.success('Excluded tags saved');
            else toast.error(data.message || 'Failed to save excluded tags');
        } catch (error) {
            console.error('Failed to save excluded tags:', error);
            toast.error('Failed to save excluded tags');
        } finally {
            savingExcludedTags = false;
        }
    };

    const filteredTags = $derived(
        Object.entries(availableTags || {})
            .filter(([, label]) => !tagSearch || label.toLowerCase().includes(tagSearch.toLowerCase()))
            .sort(([, a], [, b]) => a.localeCompare(b, undefined, { sensitivity: 'base' })),
    );
</script>

<div class="space-y-6">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Customize how search results are filtered for you. These preferences apply across the site by default.
    </p>

    <!-- Language Preferences -->
    <Card padding="lg">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Language Preferences</h2>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
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
                    class="rounded-full px-3 py-1 text-sm transition-colors {selectedLanguages.includes(iso)
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
                >
                    <span class="fi fi-{lang.flag_code} mr-1 rounded-xs"></span>
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
                disabled={savingLanguages}
                loading={savingLanguages}
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
                {savingLanguages ? 'Saving...' : 'Save Preferences'}
            </Button>
        </div>
    </Card>

    <!-- Excluded Tags -->
    <Card padding="lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Excluded Tags</h2>
            {#if excludedTags.length > 0}
                <span class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                    >{excludedTags.length} excluded</span
                >
            {/if}
        </div>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Select tags to exclude from game search results by default. Games with any of these tags will be hidden unless you explicitly include
            them.
        </p>
        <input
            type="text"
            bind:value={tagSearch}
            placeholder="Search tags..."
            class="mb-3 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        />
        <div class="flex max-h-64 flex-wrap gap-2 overflow-y-auto">
            {#each filteredTags as [tagId, label] (tagId)}
                <Button
                    type="button"
                    variant={excludedTags.includes(Number(tagId)) ? 'solid' : 'soft'}
                    tone={excludedTags.includes(Number(tagId)) ? 'danger' : 'neutral'}
                    onclick={() => toggleExcludedTag(Number(tagId))}
                    class="rounded-full px-3 py-1 text-sm transition-colors {excludedTags.includes(Number(tagId))
                        ? 'bg-red-600 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'}"
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
                disabled={savingExcludedTags}
                loading={savingExcludedTags}
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
                {savingExcludedTags ? 'Saving...' : 'Save Preferences'}
            </Button>
            {#if excludedTags.length > 0}
                <Button
                    type="button"
                    variant="soft"
                    tone="neutral"
                    onclick={() => {
                        excludedTags = [];
                        saveExcludedTags();
                    }}
                    class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                >
                    Clear All
                </Button>
            {/if}
        </div>
    </Card>

    <!-- Ignored Games -->
    <Card padding="lg">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ignored Games</h2>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                >{ignoredGamesCount} game{ignoredGamesCount !== 1 ? 's' : ''}</span
            >
        </div>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Games you've ignored won't appear in search results by default. You can manage your ignored games here.
        </p>
        {#if ignoredGames.length > 0}
            <div class="space-y-2">
                {#each ignoredGames as game (game.id)}
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-2 dark:bg-gray-700/50">
                        <Link href={route('games.show', game.slug)} class="truncate text-sm text-blue-600 hover:underline dark:text-blue-400"
                            >{game.name}</Link
                        >
                        <Button type="button" variant="link" tone="danger" onclick={() => handleUnignoreGame(game.id)} class="ml-2">Remove</Button>
                    </div>
                {/each}
            </div>
        {:else}
            <div class="py-6 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">No ignored games</div>
                <div class="text-xs text-gray-400 dark:text-gray-500">
                    You haven't ignored any games yet. Click the ignore button on any game card to hide it from search results.
                </div>
            </div>
        {/if}
    </Card>
</div>
