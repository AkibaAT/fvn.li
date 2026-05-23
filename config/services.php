<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'itch' => [
        'username' => env('ITCH_USERNAME'),
        'password' => env('ITCH_PASSWORD'),
        'free_collection_id' => env('ITCH_FREE_COLLECTION_ID'),
        'paid_collection_id' => env('ITCH_PAID_COLLECTION_ID'),
        'max_retries' => env('ITCH_MAX_RETRIES', 5),
        'retry_cooldown' => env('ITCH_RETRY_COOLDOWN', 30),
    ],

    'itchio' => [
        'client_id' => env('ITCHIO_CLIENT_ID'),
        'client_secret' => env('ITCHIO_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/itchio/callback',
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),

        'allow_gif_avatars' => (bool) env('DISCORD_AVATAR_GIF', true),
        'avatar_default_extension' => env('DISCORD_EXTENSION_DEFAULT', 'webp'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'telegram' => [
        'bot' => env('TELEGRAM_BOT_NAME'),
        'client_id' => null,
        'client_secret' => env('TELEGRAM_TOKEN'),
        'redirect' => env('TELEGRAM_REDIRECT_URI'),
    ],

    'steam' => [
        'client_id' => null,
        'client_secret' => env('STEAM_CLIENT_SECRET'),
        'redirect' => env('STEAM_REDIRECT_URI'),
        'allowed_hosts' => [
            env('APP_DOMAIN'),
        ],
    ],

    'renpy' => [
        'sdk_path' => env('RENPY_SDK_PATH'),
        'analysis_mode' => env('RENPY_ANALYSIS_MODE', 'sandbox'),
        'analyzer_url' => env('RENPY_ANALYZER_URL'),
        'analyzer_token' => env('RENPY_ANALYZER_TOKEN'),
        'analyzer_server' => env('RENPY_ANALYZER_SERVER', false),
        'analyzer_timeout' => (int) env('RENPY_ANALYZER_TIMEOUT', 300),
        'analyzer_image' => env('RENPY_ANALYZER_IMAGE', env('DOCKER_IMAGE', 'fvn.li:latest')),
        'analyzer_php_binary' => env('RENPY_ANALYZER_PHP_BINARY', 'php'),
        'analyzer_container_work_dir' => env('RENPY_ANALYZER_CONTAINER_WORK_DIR', '/runner-work'),
        'analyzer_host_work_dir' => env('RENPY_ANALYZER_HOST_WORK_DIR', '/var/lib/fvn-renpy-analyzer'),
        'analyzer_memory' => env('RENPY_ANALYZER_MEMORY', '1g'),
        'analyzer_cpus' => env('RENPY_ANALYZER_CPUS', '1'),
        'analyzer_pids_limit' => (int) env('RENPY_ANALYZER_PIDS_LIMIT', 128),
        'analyzer_tmp_size' => env('RENPY_ANALYZER_TMP_SIZE', '256m'),
        'analyzer_work_size' => env('RENPY_ANALYZER_WORK_SIZE', '4g'),
        'analyzer_stale_cleanup_seconds' => (int) env('RENPY_ANALYZER_STALE_CLEANUP_SECONDS', 7200),
        'analyzer_shared_path' => env('RENPY_ANALYZER_SHARED_PATH', storage_path('app/renpy-analyzer-requests')),
        'sdk_container_path' => env('RENPY_SDK_CONTAINER_PATH', '/opt/renpy-sdk'),
        'sdk_host_path' => env('RENPY_SDK_DOCKER_HOST_PATH', env('RENPY_SDK_PATH')),
    ],

    'android' => [
        'keystore_password' => env('ANDROID_KEYSTORE_PASSWORD', 'fvnli'),
        'key_password' => env('ANDROID_KEY_PASSWORD', 'fvnli'),
        'keystore_path' => env('ANDROID_KEYSTORE_PATH', storage_path('app/keystores')),
    ],

    'flaresolverr' => [
        'url' => env('FLARESOLVERR_URL', 'http://flaresolverr:8191'),
        'max_timeout' => env('FLARESOLVERR_MAX_TIMEOUT', 60000),
        'enabled' => env('FLARESOLVERR_ENABLED', false),
        'allowed_itch_hosts' => array_filter(array_map('trim', explode(',', env(
            'FLARESOLVERR_ALLOWED_ITCH_HOSTS',
            'itch.io'
        )))),
    ],

    'route_graph_layout' => [
        'timeout' => env('ROUTE_GRAPH_LAYOUT_TIMEOUT', 300),
    ],

    'denkit_stash' => [
        'enabled' => filter_var(env('DENKIT_STASH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'auto_persist' => filter_var(env('DENKIT_STASH_AUTO_PERSIST', false), FILTER_VALIDATE_BOOLEAN),
        'delete_local_after_push' => filter_var(env('DENKIT_STASH_DELETE_LOCAL_AFTER_PUSH', true), FILTER_VALIDATE_BOOLEAN),
        'url' => env('DENKIT_STASH_URL', 'http://denkit-stash:8081'),
        'public_url' => env('DENKIT_STASH_PUBLIC_URL', 'https://denkit-stash-fvn-li.ddev.site'),
        'username' => env('DENKIT_STASH_USERNAME', 'fvn-li'),
        'api_key' => env('DENKIT_STASH_API_KEY'),
        'client_path' => env('BUTLER_CLIENT_PATH', '/var/www/butler-client'),
    ],

];
