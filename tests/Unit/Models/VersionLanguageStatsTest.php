<?php

use App\Models\VersionLanguageStats;

test('language stat numeric attributes are cast to integers', function () {
    $stats = new VersionLanguageStats;
    $stats->setRawAttributes([
        'blocks' => '10',
        'words' => '12345',
        'menus' => '2',
        'options' => '4',
    ], true);

    expect($stats->blocks)->toBe(10)
        ->and($stats->words)->toBe(12345)
        ->and($stats->menus)->toBe(2)
        ->and($stats->options)->toBe(4);
});
