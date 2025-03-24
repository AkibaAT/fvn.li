<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Keys
    |--------------------------------------------------------------------------
    |
    | You can generate these keys using the command:
    | php artisan webpush:generate-keys
    |
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notifications Settings
    |--------------------------------------------------------------------------
    |
    | Settings for push notifications
    |
    */
    'settings' => [
        'batch_size' => env('PUSH_BATCH_SIZE', 100),
        'retry_attempts' => env('PUSH_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('PUSH_RETRY_DELAY', 5), // In minutes
    ],
];
