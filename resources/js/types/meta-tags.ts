export interface MetaTags {
    title?: string;
    browserTitle?: string;
    socialTitle?: string;
    description?: string;
    image?: string;
    url?: string;
    type?: string;
    noindex?: boolean;
    publishedTime?: string;
    modifiedTime?: string;
    author?: string;
    section?: string;
    tags?: string[];
    structuredData?: Record<string, unknown>;
    twitterCard?: 'summary' | 'summary_large_image' | 'app' | 'player';
    siteName?: string;
    locale?: string;
}
