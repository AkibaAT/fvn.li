import {Head, usePage} from '@inertiajs/react';
import React from 'react';

interface GameTag {
    name: string;
}

interface Game {
    id: number | string;
    slug?: string;
    name: string;
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
}

export interface MetaTags {
    title?: string;
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

interface SeoHeadProps {
    metaTags?: MetaTags;
    title?: string;
    children?: React.ReactNode;
}

/**
 * Comprehensive SEO Head component for consistent meta tag implementation
 * Supports Open Graph, Twitter Cards, robots directives, and structured data
 */
export default function SeoHead({metaTags = {}, title, children}: SeoHeadProps) {
    const {
        title: metaTitle,
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
    } = metaTags;

    // Use provided title or metaTitle
    const finalTitle = title || metaTitle;

    // Generate structured data JSON-LD if provided
    const structuredDataScript = structuredData ? (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{
                __html: JSON.stringify({
                    '@context': 'https://schema.org',
                    ...structuredData,
                }),
            }}
        />
    ) : null;

    return (
        <Head>
            {/* Basic Meta Tags */}
            {finalTitle && <title>{finalTitle}</title>}
            {description && <meta name="description" content={description} />}
            {keywords && <meta name="keywords" content={keywords} />}
            {author && <meta name="author" content={author} />}

            {/* Robots Directive */}
            {noindex && <meta name="robots" content="noindex" />}

            {/* Canonical URL */}
            {canonical && <link rel="canonical" href={canonical} />}

            {/* Open Graph / Facebook */}
            {finalTitle && <meta property="og:title" content={finalTitle} />}
            {description && <meta property="og:description" content={description} />}
            {image && <meta property="og:image" content={image} />}
            {url && <meta property="og:url" content={url} />}
            <meta property="og:type" content={type} />
            <meta property="og:site_name" content={siteName} />
            <meta property="og:locale" content={locale} />
            {publishedTime && <meta property="article:published_time" content={publishedTime} />}
            {modifiedTime && <meta property="article:modified_time" content={modifiedTime} />}
            {author && <meta property="article:author" content={author} />}
            {section && <meta property="article:section" content={section} />}
            {tags && tags.map((tag, index) => (
                <meta key={index} property="article:tag" content={tag} />
            ))}

            {/* Twitter Cards */}
            <meta name="twitter:card" content={twitterCard} />
            {finalTitle && <meta name="twitter:title" content={finalTitle} />}
            {description && <meta name="twitter:description" content={description} />}
            {image && <meta name="twitter:image" content={image} />}
            {url && <meta name="twitter:url" content={url} />}

            {/* Structured Data */}
            {structuredDataScript}

            {/* Additional custom meta tags */}
            {children}
        </Head>
    );
}

/**
 * Get base URL for SSR-safe URL generation
 */
function getBaseUrl(): string {
    // In SSR, use the URL from Inertia's ziggy config
    if (typeof window === 'undefined') {
        // This will be available in SSR context from Inertia
        try {
            // @ts-ignore - ziggy is available globally in SSR
            return globalThis.ziggy?.url || 'https://fvn.li';
        } catch {
            return 'https://fvn.li';
        }
    }
    // In browser, use window.location.origin
    return window.location.origin;
}

/**
 * Helper function to create meta tags for game pages
 */
export function createGameMetaTags(game: Game, options: Partial<MetaTags> = {}): MetaTags {
    const baseUrl = getBaseUrl();
    const gameUrl = `${baseUrl}/games/${game.slug || game.id}`;

    return {
        title: `${game.name} - FVN.li`,
        description: game.description
            ? `${game.description.substring(0, 155)}...`
            : `Discover ${game.name}, a furry visual novel. Read reviews, ratings, and community discussions.`,
        image: game.thumb_url || game.optimized_thumbnails?.default?.path
            ? `${baseUrl}/storage/${game.optimized_thumbnails?.default?.path}`
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
            name: game.name,
            description: game.description,
            image: game.thumb_url,
            url: gameUrl,
            author: game.authors_text ? {
                '@type': 'Person',
                name: game.authors_text,
            } : undefined,
            datePublished: game.initially_published_at,
            dateModified: game.latest_version_published_at || game.updated_at,
            genre: 'Visual Novel',
            keywords: game.tags?.map((tag: GameTag) => tag.name).join(', '),
            operatingSystem: [
                game.is_windows && 'Windows',
                game.is_mac && 'macOS',
                game.is_linux && 'Linux',
                game.is_android && 'Android',
                game.is_web && 'Web Browser',
            ].filter(Boolean).join(', '),
        },
        ...options,
    };
}

/**
 * Helper function to create meta tags for list pages
 */
export function createListMetaTags(
    title: string,
    description: string,
    options: Partial<MetaTags> = {}
): MetaTags {
    const baseUrl = getBaseUrl();
    const currentUrl = typeof window !== 'undefined' ? window.location.href : baseUrl;

    return {
        title: `${title} - FVN.li`,
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
