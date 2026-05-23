<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\GameDataSyncService;
use App\Services\ItchHttpClientService;
use App\ValueObjects\Upload;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('itch metadata refresh does not update pricing', function () {
    $game = Game::factory()->create([
        'name' => 'Three Lesbians in a Barrow',
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://example.itch.io/three-lesbians-in-a-barrow'],
        'is_paid' => true,
        'min_price' => 3.99,
        'currency' => 'USD',
        'is_on_sale' => true,
        'sale_discount_percent' => 20,
        'thumb_url' => null,
        'optimized_thumbnails' => [],
        'screenshots' => [],
    ]);

    $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <div class="game_content">No buy section here</div>
</body>
</html>
HTML;

    $mockClient = Mockery::mock(ItchHttpClientService::class);
    $mockClient->shouldReceive('get')
        ->once()
        ->with($game->getPrimaryUrl(), [], true)
        ->andReturn(new Response(200, [], $html));

    $this->app->instance(ItchHttpClientService::class, $mockClient);

    app(GameDataSyncService::class)->refreshMetadata($game);

    expect($game->is_paid)->toBeTrue()
        ->and($game->min_price)->toBe(3.99)
        ->and($game->currency)->toBe('USD')
        ->and($game->is_on_sale)->toBeTrue()
        ->and($game->sale_discount_percent)->toBe(20);
});

test('only demo processable uploads are treated as unsuitable for stats extraction', function () {
    $service = app(GameDataSyncService::class);
    $method = new ReflectionMethod($service, 'hasOnlyDemoProcessableUploads');

    $demoUpload = Upload::fromArray([
        'filename' => 'Game-1.0-demo-pc.zip',
        'display_name' => null,
        'md5_hash' => null,
        'updated_at' => '2025-09-20T22:25:28Z',
        'build_id' => null,
        'build_updated_at' => null,
        'user_version' => null,
        'traits' => ['p_windows', 'p_linux', 'demo'],
        'type' => 'default',
    ], 1);

    $fullUpload = Upload::fromArray([
        'filename' => 'Game-1.0-pc.zip',
        'display_name' => null,
        'md5_hash' => null,
        'updated_at' => '2025-09-20T22:25:28Z',
        'build_id' => null,
        'build_updated_at' => null,
        'user_version' => null,
        'traits' => ['p_windows', 'p_linux'],
        'type' => 'default',
    ], 2);

    expect($method->invoke($service, [$demoUpload]))->toBeTrue()
        ->and($method->invoke($service, [$demoUpload, $fullUpload]))->toBeFalse()
        ->and($method->invoke($service, []))->toBeFalse();
});
