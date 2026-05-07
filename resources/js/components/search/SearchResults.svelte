<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { route } from 'ziggy-js';
    import type { SearchResult, SearchPagination } from '@/hooks/useEnhancedSearch.svelte';
    import { highlightPlainText } from '@/utils/safe-highlight';

    interface Props {
        type: 'games' | 'dialogue' | 'global';
        results?: SearchResult[];
        globalResults?: {
            games: SearchResult[];
            dialogue: SearchResult[];
            total_games: number;
            total_dialogue: number;
        };
        pagination?: SearchPagination;
        loading?: boolean;
        query?: string;
        onPageChange?: (page: number) => void;
        class?: string;
    }

    let { type, results = [], globalResults, pagination, loading = false, query = '', onPageChange, class: className = '' }: Props = $props();

    const highlightText = (text: string): string => highlightPlainText(text, query);
</script>

{#if loading}
    <div class="space-y-4 {className}">
        {#each Array(3) as _, i (i)}
            <div class="animate-pulse">
                <div class="mb-2 h-4 w-3/4 rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
        {/each}
    </div>
{:else if type === 'global' && globalResults}
    <div class="space-y-6 {className}">
        {#if globalResults.games.length > 0}
            <div>
                <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">
                    Games ({globalResults.total_games})
                </h3>
                <div class="space-y-3">
                    {#each globalResults.games as game (game.id)}
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                        >
                            <Link href={route('games.show', game.slug as string)} class="block">
                                <h4
                                    class="text-lg font-semibold break-words text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                >
                                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                    {@html highlightText(game.name as string)}
                                </h4>
                                {#if game.authors}
                                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{@html `by ${highlightText(game.authors as string)}`}</p>
                                {/if}
                                {#if game.description}
                                    <p class="mt-2 line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html highlightText((game.description as string).substring(0, 150) + '...')}
                                    </p>
                                {/if}
                                <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="capitalize">{game.status}</span>
                                    {#if game.game_engine}<span>{game.game_engine}</span>{/if}
                                    {#if game.is_nsfw}<span class="text-red-500">NSFW</span>{/if}
                                    {#if game.is_paid}<span class="text-green-500">Paid</span>{/if}
                                </div>
                            </Link>
                        </div>
                    {/each}
                </div>
                {#if globalResults.total_games > globalResults.games.length}
                    <Link
                        href={route('games.index', { search: query })}
                        class="mt-3 inline-block text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        View all {globalResults.total_games} games &rarr;
                    </Link>
                {/if}
            </div>
        {/if}

        {#if globalResults.dialogue.length > 0}
            <div>
                <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">
                    Dialogue ({globalResults.total_dialogue})
                </h3>
                <div class="space-y-3">
                    {#each globalResults.dialogue as dialogue (dialogue.id)}
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <div class="text-gray-900 dark:text-white">
                                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                {@html highlightText((dialogue.text_content as string)?.substring(0, 200) + '...' || '')}
                            </div>
                            {#if (dialogue.character_names as string[])?.length > 0 || (dialogue.game_names as string[])?.length > 0}
                                <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    {#if (dialogue.character_names as string[])?.length > 0}
                                        <span>Characters: {(dialogue.character_names as string[]).join(', ')}</span>
                                    {/if}
                                    {#if (dialogue.game_names as string[])?.length > 0}
                                        <span>Games: {(dialogue.game_names as string[]).join(', ')}</span>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
                {#if globalResults.total_dialogue > globalResults.dialogue.length}
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Showing {globalResults.dialogue.length} of {globalResults.total_dialogue} dialogue results. Visit individual game pages to browse
                        all dialogue.
                    </p>
                {/if}
            </div>
        {/if}

        {#if globalResults.games.length === 0 && globalResults.dialogue.length === 0}
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                No results found for "{query}"
            </div>
        {/if}
    </div>
{:else if results.length === 0 && query}
    <div class="py-8 text-center text-gray-500 dark:text-gray-400 {className}">
        No results found for "{query}"
    </div>
{:else}
    <div class="space-y-4 {className}">
        <div class="space-y-3">
            {#each results as result (result.id)}
                <div>
                    {#if type === 'games'}
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-4 transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                        >
                            <Link href={route('games.show', result.slug as string)} class="block">
                                <h4
                                    class="text-lg font-semibold break-words text-gray-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                                >
                                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                    {@html highlightText(result.name as string)}
                                </h4>
                                {#if result.authors}
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html `by ${highlightText(result.authors as string)}`}
                                    </p>
                                {/if}
                                {#if result.description}
                                    <p class="mt-2 line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                                        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                        {@html highlightText((result.description as string).substring(0, 150) + '...')}
                                    </p>
                                {/if}
                                <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="capitalize">{result.status}</span>
                                    {#if result.game_engine}<span>{result.game_engine}</span>{/if}
                                    {#if result.is_nsfw}<span class="text-red-500">NSFW</span>{/if}
                                    {#if result.is_paid}<span class="text-green-500">Paid</span>{/if}
                                </div>
                            </Link>
                        </div>
                    {:else}
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <div class="text-gray-900 dark:text-white">
                                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                {@html highlightText((result.text_content as string)?.substring(0, 200) + '...' || '')}
                            </div>
                            {#if (result.character_names as string[])?.length > 0 || (result.game_names as string[])?.length > 0}
                                <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    {#if (result.character_names as string[])?.length > 0}
                                        <span>Characters: {(result.character_names as string[]).join(', ')}</span>
                                    {/if}
                                    {#if (result.game_names as string[])?.length > 0}
                                        <span>Games: {(result.game_names as string[]).join(', ')}</span>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/each}
        </div>

        {#if pagination && pagination.last_page > 1 && onPageChange}
            <div class="mt-6 flex justify-center space-x-2">
                <button
                    onclick={() => onPageChange?.(pagination!.current_page - 1)}
                    disabled={pagination.current_page <= 1}
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    Previous
                </button>
                <span class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                    Page {pagination.current_page} of {pagination.last_page}
                </span>
                <button
                    onclick={() => onPageChange?.(pagination!.current_page + 1)}
                    disabled={pagination.current_page >= pagination.last_page}
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    Next
                </button>
            </div>
        {/if}
    </div>
{/if}
