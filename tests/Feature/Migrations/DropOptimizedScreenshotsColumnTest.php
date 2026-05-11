<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('migrates legacy optimized screenshots into embedded screenshot data before dropping the column', function () {
    $migration = require base_path('database/migrations/2025_12_07_000000_drop_optimized_screenshots_column.php');
    $migration->down();

    $game = Game::factory()->create([
        'screenshots' => [
            ['url' => 'https://cdn.example.test/screenshot-1.png'],
            [
                'url' => 'https://cdn.example.test/screenshot-2.png',
                'optimized' => [
                    'default' => ['path' => 'screenshots/already_embedded.webp'],
                ],
            ],
        ],
    ]);

    DB::table('games')
        ->where('id', $game->id)
        ->update([
            'optimized_screenshots' => json_encode([
                [
                    'optimized' => [
                        'small' => ['path' => 'screenshots/legacy_small.webp'],
                        'default' => ['path' => 'screenshots/legacy_default.webp'],
                    ],
                ],
                [
                    'optimized' => [
                        'default' => ['path' => 'screenshots/legacy_should_not_overwrite.webp'],
                    ],
                ],
            ]),
        ]);

    $migration->up();

    expect(Schema::hasColumn('games', 'optimized_screenshots'))->toBeFalse();

    $screenshots = json_decode(DB::table('games')->where('id', $game->id)->value('screenshots'), true);

    expect($screenshots[0]['optimized'])->toBe([
        'small' => ['path' => 'screenshots/legacy_small.webp'],
        'default' => ['path' => 'screenshots/legacy_default.webp'],
    ])->and($screenshots[1]['optimized'])->toBe([
        'default' => ['path' => 'screenshots/already_embedded.webp'],
    ]);
});
