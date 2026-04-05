<script lang="ts">
    import { formatLocalDate } from '@/utils/date-formatting';
    import type { Snippet } from 'svelte';

    export interface AdditionalLink {
        id: number | string;
        url: string;
        name: string;
        platform?: string | null;
        last_edited_at?: string | null;
    }

    interface Props {
        gameId: number;
        links: AdditionalLink[];
        getPlatformIcon: (platform: string) => Snippet | string;
    }

    let { gameId, links, getPlatformIcon }: Props = $props();
</script>

{#if links && links.length > 0}
    <div id="downloads" class="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
        <h2 class="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">Downloads</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {#each links as link (link.id)}
                {@const icon = getPlatformIcon(link.platform || 'external')}
                <a
                    href={route('track.custom-link', { game_id: gameId, link_id: link.id, url: link.url })}
                    target="_blank"
                    rel="noopener"
                    class="group flex items-center gap-4 rounded-lg border border-gray-200 p-4 transition-all duration-200 hover:border-blue-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:border-blue-500 dark:hover:bg-gray-700"
                >
                    <div class="flex-shrink-0">
                        <div class="rounded-lg bg-gray-50 p-2 text-xl dark:bg-gray-700">
                            {#if typeof icon === 'string'}
                                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                                {@html icon}
                            {:else}
                                {@render icon()}
                            {/if}
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 font-semibold text-gray-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                            {link.name}
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            {#if link.platform}
                                <span class="font-medium capitalize">{link.platform}</span>
                            {/if}
                            {#if link.last_edited_at}
                                {#if link.platform}<span>&bull;</span>{/if}
                                <span>Updated {formatLocalDate(link.last_edited_at)}</span>
                            {/if}
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <svg
                            class="h-5 w-5 text-gray-400 transition-colors group-hover:text-blue-500 dark:group-hover:text-blue-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </div>
                </a>
            {/each}
        </div>
    </div>
{/if}
