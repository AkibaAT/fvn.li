<script lang="ts" module>
    interface GameTag {
        name: string;
    }

    interface GameVersion {
        is_windows?: boolean;
        is_mac?: boolean;
        is_linux?: boolean;
        is_android?: boolean;
        is_web?: boolean;
    }

    interface Game {
        id: number | string;
        slug?: string;
        name: string;
        effective_name: string;
        description?: string;
        thumb_url?: string;
        optimized_thumbnails?: {
            default?: {
                path?: string;
            };
        };
        is_visible?: boolean;
        initially_published_at?: string;
        latest_version_published_at?: string;
        updated_at?: string;
        authors_text?: string;
        tags?: GameTag[];
        is_windows?: boolean;
        is_mac?: boolean;
        is_linux?: boolean;
        is_android?: boolean;
        is_web?: boolean;
        rating_score?: number;
        rating_count?: number;
        latest_version?: GameVersion;
    }

    export interface MetaTags {
        title?: string;
        browserTitle?: string;
        socialTitle?: string;
        description?: string;
        image?: string;
        url?: string;
        type?: string;
        siteName?: string;
        locale?: string;
        noindex?: boolean;
        canonical?: string;
        structuredData?: Record<string, unknown>;
        twitterCard?: 'summary' | 'summary_large_image' | 'app' | 'player';
        keywords?: string;
        author?: string;
        publishedTime?: string;
        modifiedTime?: string;
        section?: string;
        tags?: string[];
    }

    function getBaseUrl(): string {
        if (typeof window === 'undefined') {
            try {
                return globalThis.ziggy?.url || 'https://fvn.li';
            } catch {
                return 'https://fvn.li';
            }
        }
        return window.location.origin;
    }

    export function createGameMetaTags(game: Game, options: Partial<MetaTags> = {}): MetaTags {
        const baseUrl = getBaseUrl();
        const gameUrl = `${baseUrl}/games/${game.slug || game.id}`;
        const gameName = game.effective_name;

        return {
            title: gameName,
            description: game.description
                ? `${game.description.substring(0, 155)}...`
                : `Discover ${gameName}, a furry visual novel. Read reviews, ratings, and community discussions.`,
            image: game.optimized_thumbnails?.default?.path
                ? `${baseUrl}/storage/${game.optimized_thumbnails.default.path}`
                : `${baseUrl}/images/social-fallback.jpg`,
            url: gameUrl,
            type: 'article',
            noindex: game.is_visible === false,
            publishedTime: game.initially_published_at,
            modifiedTime: game.latest_version_published_at || game.updated_at,
            author: game.authors_text,
            section: 'Visual Novels',
            tags: game.tags?.map((tag: GameTag) => tag.name) || [],
            structuredData: {
                '@type': 'VideoGame',
                name: gameName,
                description: game.description,
                image: game.optimized_thumbnails?.default?.path ? `${baseUrl}/storage/${game.optimized_thumbnails.default.path}` : undefined,
                url: gameUrl,
                author: game.authors_text
                    ? {
                          '@type': 'Person',
                          name: game.authors_text,
                      }
                    : undefined,
                datePublished: game.initially_published_at,
                dateModified: game.latest_version_published_at || game.updated_at,
                genre: 'Visual Novel',
                keywords: game.tags?.map((tag: GameTag) => tag.name).join(', '),
                operatingSystem: [
                    (game.latest_version?.is_windows || game.is_windows) && 'Windows',
                    (game.latest_version?.is_mac || game.is_mac) && 'macOS',
                    (game.latest_version?.is_linux || game.is_linux) && 'Linux',
                    (game.latest_version?.is_android || game.is_android) && 'Android',
                    (game.latest_version?.is_web || game.is_web) && 'Web Browser',
                ]
                    .filter(Boolean)
                    .join(', '),
                aggregateRating:
                    game.rating_count && game.rating_count > 0
                        ? {
                              '@type': 'AggregateRating',
                              ratingValue: game.rating_score,
                              ratingCount: game.rating_count,
                              bestRating: 5,
                              worstRating: 1,
                          }
                        : undefined,
            },
            ...options,
        };
    }

    export function createListMetaTags(title: string, description: string, options: Partial<MetaTags> = {}): MetaTags {
        const baseUrl = getBaseUrl();
        const currentUrl = typeof window !== 'undefined' ? window.location.href : baseUrl;

        return {
            title: title,
            description,
            image: `${baseUrl}/images/social-fallback.jpg`,
            url: currentUrl,
            type: 'website',
            structuredData: {
                '@type': 'CollectionPage',
                name: title,
                description,
                url: currentUrl,
            },
            ...options,
        };
    }
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        metaTags = {},
        title,
        children,
    }: {
        metaTags?: MetaTags;
        title?: string;
        children?: Snippet;
    } = $props();

    const {
        title: metaTitle,
        browserTitle,
        socialTitle,
        description,
        image,
        url,
        type = 'website',
        siteName = 'FVN.li',
        locale = 'en_US',
        noindex = false,
        canonical,
        structuredData,
        twitterCard = 'summary_large_image',
        keywords,
        author,
        publishedTime,
        modifiedTime,
        section,
        tags,
    } = $derived(metaTags);

    const finalBrowserTitle = $derived(browserTitle || title || metaTitle);
    const finalSocialTitle = $derived(socialTitle || finalBrowserTitle);

    const structuredDataJson = $derived(structuredData ? JSON.stringify({ '@context': 'https://schema.org', ...structuredData }) : null);
    const structuredDataScript = $derived(
        structuredDataJson ? '<script' + ' type="application/ld+json">' + structuredDataJson + '</script' + '>' : null,
    );
</script>

<svelte:head>
    {#if finalBrowserTitle}
        <title>{finalBrowserTitle}</title>
    {/if}

    <!-- Basic Meta Tags -->
    {#if description}<meta name="description" content={description} />{/if}
    {#if keywords}<meta name="keywords" content={keywords} />{/if}
    {#if author}<meta name="author" content={author} />{/if}

    <!-- Robots Directive -->
    {#if noindex}<meta name="robots" content="noindex" />{/if}

    <!-- Canonical URL -->
    {#if canonical}<link rel="canonical" href={canonical} />{/if}

    <!-- Open Graph / Facebook -->
    {#if finalSocialTitle}<meta property="og:title" content={finalSocialTitle} />{/if}
    {#if description}<meta property="og:description" content={description} />{/if}
    {#if image}<meta property="og:image" content={image} />{/if}
    {#if url}<meta property="og:url" content={url} />{/if}
    <meta property="og:type" content={type} />
    <meta property="og:site_name" content={siteName} />
    <meta property="og:locale" content={locale} />
    {#if publishedTime}<meta property="article:published_time" content={publishedTime} />{/if}
    {#if modifiedTime}<meta property="article:modified_time" content={modifiedTime} />{/if}
    {#if author}<meta property="article:author" content={author} />{/if}
    {#if section}<meta property="article:section" content={section} />{/if}
    {#if tags}
        {#each tags as tag, _i (_i)}
            <meta property="article:tag" content={tag} />
        {/each}
    {/if}

    <!-- Twitter Cards -->
    <meta name="twitter:card" content={twitterCard} />
    {#if finalSocialTitle}<meta name="twitter:title" content={finalSocialTitle} />{/if}
    {#if description}<meta name="twitter:description" content={description} />{/if}
    {#if image}<meta name="twitter:image" content={image} />{/if}
    {#if url}<meta name="twitter:url" content={url} />{/if}

    <!-- Structured Data -->
    {#if structuredDataScript}
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html structuredDataScript}
    {/if}

    <!-- Additional custom meta tags -->
    {#if children}
        {@render children()}
    {/if}
</svelte:head>
