<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bot Detection
    |--------------------------------------------------------------------------
    |
    | Rules used to classify a click_stats row as non-human. A matching row is
    | still persisted, tagged with the reason that matched, and excluded from
    | every analytics surface. Changing these rules and re-running
    | `analytics:backfill-bot-flags` re-classifies stored rows.
    |
    */

    'bot_detection' => [

        /*
         * Networks whose traffic is treated as automated regardless of what it
         * claims to be. Prefixes must be /24 or larger: stored addresses are
         * anonymised to their /24 before they reach the database, so anything
         * narrower can never match.
         *
         * Extra prefixes can be appended at runtime via a comma-separated
         * ANALYTICS_BLOCKED_NETWORKS environment variable.
         */
        'blocked_networks' => array_values(array_filter(array_map(
            'trim',
            array_merge(
                [
                    // Resold proxy space carrying a crawler that opens a fresh
                    // session for every request and never follows a link out.
                    '40.223.0.0/16',
                    '172.121.0.0/16',
                    '172.252.0.0/16',

                    // Hosting and further resold space running the same
                    // pattern at lower volume across the whole catalogue.
                    '23.230.0.0/16',
                    '45.38.0.0/16',
                    '45.39.0.0/16',
                    '69.12.0.0/16',
                ],
                explode(',', (string) env('ANALYTICS_BLOCKED_NETWORKS', ''))
            )
        ))),

        /*
         * User agents matching any of these expressions are treated as
         * automated. The first expression covers the HTTP product-token
         * convention shared by well-behaved crawlers (`Googlebot/2.1`), which
         * requires the trailing slash so device names such as `CUBOT NOTE 20`
         * are left alone.
         */
        'crawler_user_agent_patterns' => [
            '~(?:^|[\s(;])[a-z0-9._+-]*(?:bot|crawler|spider|scraper|indexer)/~i',
            '~\(compatible;[^)]*\+https?://~i',
            '~(?:slurp|bytespider|baiduspider|yandexbot|yandeximages|petalbot|semrushbot|ahrefsbot|mj12bot|dotbot|dataforseo|serpstatbot|barkrowler|zoominfobot)~i',
            '~(?:gptbot|oai-searchbot|chatgpt-user|claudebot|claude-web|anthropic-ai|ccbot|perplexitybot|applebot|amazonbot|meta-externalagent|facebookexternalhit|bingpreview)~i',
            '~(?:^|[\s(;])(?:curl|wget|python-requests|python-urllib|aiohttp|httpx|scrapy|go-http-client|java|okhttp|libwww-perl|guzzlehttp|node-fetch|axios|postmanruntime)/~i',
            '~(?:headlesschrome|phantomjs|puppeteer|playwright|selenium|webdriver)~i',
        ],

        /*
         * A browser stores its session cookie and sends it back, so a
         * population of real visitors sharing a user agent always produces
         * some multi-hit sessions. A client that opens a fresh session for
         * every request stores nothing, and at volume that is not a browser.
         *
         * The signal only means anything in aggregate (a single hit proves
         * nothing, since every genuine first-time visitor starts one session),
         * so a user agent is judged one day at a time and every threshold below
         * must be met before any of that day's rows are flagged.
         */
        'session_churn' => [

            // Rows a user agent must produce in a day to be judged at all.
            'min_rows' => 50,

            // Share of a user agent's sessions carrying more than one hit.
            'max_session_reuse_ratio' => 0.01,

            // Distinct /24s per row. Real visitors revisit from one address;
            // a rotating pool spends a fresh one on nearly every request.
            'min_subnet_dispersion' => 0.5,

            // A signed-in visitor is a person, and one is enough to spare the
            // whole user agent.
            'max_authenticated_rows' => 0,

        ],

    ],

];
