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

];
