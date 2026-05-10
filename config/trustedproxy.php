<?php

declare(strict_types=1);

$trustedProxies = env('TRUSTED_PROXIES');

return [
    'proxies' => $trustedProxies
        ? array_values(array_filter(array_map('trim', explode(',', $trustedProxies))))
        : null,
];
