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
        type: 'announcement' | 'update' | 'maintenance' | 'incident';
        is_published: boolean;
        published_at: string;
        author: Author;
        created_at: string;
        updated_at: string;
    }

    interface Props {
        newsItem: NewsItem;
        metaTags?: MetaTags;
    }

    let { newsItem, metaTags }: Props = $props();

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
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
</script>

<SeoHead {metaTags} title={newsItem.title} />

<!-- Back Link -->
<div class="mb-8">
    <Link
        href="/news"
        class="inline-flex items-center text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
    >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to News
    </Link>
</div>

<!-- Article -->
<article class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-800">
    <div class="p-8 sm:p-10 lg:p-12">
        <!-- Type Badge -->
        <div class="mb-6 flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium {getTypeColor(newsItem.type)}">
                <span aria-hidden="true" class="text-base">{getTypeIcon(newsItem.type)}</span>
                {newsItem.type.charAt(0).toUpperCase() + newsItem.type.slice(1)}
            </span>
        </div>

        <!-- Title -->
        <h1 class="mb-6 text-4xl leading-tight font-bold text-gray-900 sm:text-5xl dark:text-white">
            {newsItem.title}
        </h1>

        <!-- Meta Information -->
        <div class="mb-10 flex items-center gap-4 border-b border-gray-200 pb-8 dark:border-gray-700">
            <div class="flex items-center gap-3">
                {#if newsItem.author.avatar}
                    <img src={newsItem.author.avatar} alt={newsItem.author.name} class="h-12 w-12 rounded-full" referrerpolicy="no-referrer" />
                {:else}
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-base font-medium text-white">
                        {newsItem.author.name.charAt(0).toUpperCase()}
                    </div>
                {/if}
                <div>
                    <div class="text-base font-medium text-gray-900 dark:text-white">
                        {newsItem.author.name}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {formatDate(newsItem.published_at)}
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="news-content prose prose-lg max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert">
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html newsItem.content}
        </div>

        <!-- Updated At (if different from published) -->
        {#if newsItem.updated_at !== newsItem.created_at}
            <div class="mt-12 border-t border-gray-200 pt-6 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Last updated: {formatDate(newsItem.updated_at)}
                </p>
            </div>
        {/if}
    </div>
</article>

<!-- Back Link (Bottom) -->
<div class="mt-8">
    <Link
        href="/news"
        class="inline-flex items-center text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
    >
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to News
    </Link>
</div>
