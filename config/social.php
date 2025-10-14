<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Social Media Images
    |--------------------------------------------------------------------------
    |
    | Configure static social media images for different page types.
    | These images are used for Open Graph and Twitter Card meta tags.
    |
    */
    'images' => [
        // Default fallback image for all pages
        'default' => 'storage/social-images/social-fallback.jpg',

        // Home page
        'home' => 'storage/social-images/social-home.jpg',

        // Games list/search page
        'games_list' => 'storage/social-images/social-games.jpg',

        // Public lists page
        'public_lists' => 'storage/social-images/social-lists.jpg',

        // Ratings page
        'ratings' => 'storage/social-images/social-ratings.jpg',
    ],
];
