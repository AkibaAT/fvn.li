<?php

declare(strict_types=1);

use App\Services\GameImageIntegrityService;
use Illuminate\Support\Facades\Storage;

it('reports legacy boolean screenshot metadata instead of throwing', function () {
    Storage::fake('public');

    expect((new GameImageIntegrityService)->screenshotIssues([
        ['url' => 'https://img.itch.zone/legacy.png', 'optimized' => true],
    ]))->toBe([
        0 => [
            'small: missing path metadata',
            'default: missing path metadata',
            'large: missing path metadata',
        ],
    ]);
});
