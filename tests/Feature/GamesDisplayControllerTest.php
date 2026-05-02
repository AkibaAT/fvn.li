<?php

use App\Models\Game;

function gameShowInertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}

test('game show exposes itch screenshots as effective screenshots in original view mode', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'original',
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
            ['url' => 'https://itch.example/original-b.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom-a.jpg'],
        ],
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($response->json('component'))->toBe('games/show')
        ->and($gameProps['screenshots'])->toHaveCount(2)
        ->and($gameProps['custom_screenshots'])->toHaveCount(1)
        ->and($gameProps['effective_screenshots'])->toHaveCount(2)
        ->and($gameProps['effective_screenshots'][0]['url'])->toBe('https://itch.example/original-a.jpg')
        ->and($gameProps['custom_screenshots'][0]['url'])->toBe('https://custom.example/custom-a.jpg');
});

test('game show exposes custom screenshots as effective screenshots in custom view mode', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => true,
        'view_mode' => 'custom',
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
        ],
        'custom_screenshots' => [
            ['url' => 'https://custom.example/custom-a.jpg'],
            ['url' => 'https://custom.example/custom-b.jpg'],
        ],
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($response->json('component'))->toBe('games/show')
        ->and($gameProps['screenshots'])->toHaveCount(1)
        ->and($gameProps['custom_screenshots'])->toHaveCount(2)
        ->and($gameProps['effective_screenshots'])->toHaveCount(2)
        ->and($gameProps['effective_screenshots'][0]['url'])->toBe('https://custom.example/custom-a.jpg')
        ->and($gameProps['screenshots'][0]['url'])->toBe('https://itch.example/original-a.jpg');
});

test('game show preserves missing custom screenshot list as null', function () {
    $game = Game::factory()->create([
        'is_visible' => true,
        'has_custom_page' => false,
        'screenshots' => [
            ['url' => 'https://itch.example/original-a.jpg'],
        ],
        'custom_screenshots' => null,
    ]);

    $response = $this
        ->withHeaders(gameShowInertiaHeaders())
        ->get(route('games.show', $game));

    $response->assertOk();

    $gameProps = $response->json('props.game');

    expect($gameProps['custom_screenshots'])->toBeNull()
        ->and($gameProps['effective_screenshots'])->toHaveCount(1)
        ->and($gameProps['effective_screenshots'][0]['url'])->toBe('https://itch.example/original-a.jpg');
});
