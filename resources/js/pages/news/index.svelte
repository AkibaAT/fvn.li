<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/components/seo/SeoHead.svelte';
    import { Link } from '@inertiajs/svelte';

    interface Author {
        id: number;
        name: string;
        avatar?: string;
    }

    interface NewsItem {
        id: number;
        title: string;
        slug: string;
        content: string;
        excerpt?: string;
        type: 'announcement' | 'update' | 'maintenance' | 'incident';
        is_published: boolean;
        published_at: string;
        author: Author;
        created_at: string;
        updated_at: string;
    }

    interface PaginationLink {
        url: string | null;
        label: string;
        active: boolean;
    }

    interface PaginatedNews {
        data: NewsItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginationLink[];
    }

    interface Props {
        news: PaginatedNews;
        metaTags?: MetaTags;
    }

    let { news, metaTags }: Props = $props();

    function getTypeColor(type: string): string {
        switch (type) {
            case 'announcement':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            case 'update':
                return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
            case 'maintenance':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300';
            case 'incident':
                return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300';
        }
    }

    function getTypeIcon(type: string): string {
        switch (type) {
            case 'announcement':
                return '📢';
            case 'update':
                return '✨';
            case 'maintenance':
                return '🔧';
            case 'incident':
                return '⚠️';
            default:
                return '📰';
        }
    }

    function formatDate(dateString: string): string {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }
</script>

<SeoHead {metaTags} title="News & Announcements" />

<!-- Header -->
<div class="mb-10">
    <h1 class="text-4xl font-bold text-gray-900 sm:text-5xl dark:text-white">News & Announcements</h1>
    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">Stay updated with the latest news and updates from FVN.li</p>
</div>

<!-- News List -->
{#if news.data.length === 0}
    <div class="rounded-lg bg-white p-12 text-center shadow-sm dark:bg-gray-800">
        <p class="text-lg text-gray-600 dark:text-gray-400">No news items available at this time.</p>
    </div>
{:else}
    <div class="space-y-8">
        {#each news.data as item (item.id)}
            <article class="overflow-hidden rounded-lg bg-white shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800">
                <div class="p-8">
                    <!-- Type Badge -->
                    <div class="mb-4 flex items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium {getTypeColor(item.type)}">
                            <span aria-hidden="true" class="text-base">{getTypeIcon(item.type)}</span>
                            {item.type.charAt(0).toUpperCase() + item.type.slice(1)}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {formatDate(item.published_at)}
                        </span>
                    </div>

                    <!-- Title -->
                    <h2 class="mb-4 text-3xl leading-tight font-bold text-gray-900 dark:text-white">
                        <Link href="/news/{item.slug}" class="transition-colors hover:text-blue-600 dark:hover:text-blue-400">
                            {item.title}
                        </Link>
                    </h2>

                    <!-- Excerpt -->
                    <div class="mb-6 text-base leading-relaxed text-gray-600 dark:text-gray-300">
                        {item.excerpt || ''}
                    </div>

                    <!-- Read More Link -->
                    <Link
                        href="/news/{item.slug}"
                        class="inline-flex items-center font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        Read more
                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>

                    <!-- Author -->
                    <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
                        {#if item.author.avatar}
                            <img src={item.author.avatar} alt={item.author.name} class="h-10 w-10 rounded-full" referrerpolicy="no-referrer" />
                        {:else}
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-sm font-medium text-white">
                                {item.author.name.charAt(0).toUpperCase()}
                            </div>
                        {/if}
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            By {item.author.name}
                        </span>
                    </div>
                </div>
            </article>
        {/each}
    </div>
{/if}

<!-- Pagination -->
{#if news.last_page > 1}
    <div class="mt-8 flex justify-center">
        <nav class="inline-flex rounded-md shadow-sm" aria-label="Pagination">
            {#each news.links as link, index (index)}
                <Link
                    href={link.url || '#'}
                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium {link.active
                        ? 'z-10 bg-blue-600 text-white'
                        : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'} {index === 0
                        ? 'rounded-l-md'
                        : index === news.links.length - 1
                          ? 'rounded-r-md'
                          : ''} border border-gray-300 dark:border-gray-600 {!link.url ? 'cursor-not-allowed opacity-50' : ''}"
                    preserveScroll
                >
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    {@html link.label}
                </Link>
            {/each}
        </nav>
    </div>
{/if}
