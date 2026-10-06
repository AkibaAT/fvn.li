<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Tag Game Count
    |--------------------------------------------------------------------------
    |
    | Tags used by fewer games than this threshold are hidden from public UI
    | surfaces (game cards, filters, account settings, and global tag search).
    |
    */

    'min_tag_game_count' => (int) env('MIN_TAG_GAME_COUNT', 5),

    /*
    |--------------------------------------------------------------------------
    | Hidden Tags
    |--------------------------------------------------------------------------
    |
    | Tag slugs that apply to the whole catalogue and are never shown publicly.
    |
    */

    'hidden_tag_slugs' => ['furry', 'fvn', 'furry-visual-novel'],

    /*
    |--------------------------------------------------------------------------
    | Public Page Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds a shared cache (Cloudflare) may serve public pages to visitors
    | without a session. Zero keeps those responses private.
    |
    */

    'public_page_cache_ttl' => (int) env('PUBLIC_PAGE_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Game Page Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds the game detail page's data is cached for signed-out visitors.
    | Saving the game starts a new cache entry; zero disables the cache.
    |
    */

    'game_page_cache_ttl' => (int) env('GAME_PAGE_CACHE_TTL', 300),

];
