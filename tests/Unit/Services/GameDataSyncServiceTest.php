<?php

declare(strict_types=1);

use App\Models\Game;
use App\Services\GameDataSyncService;
use App\Services\ItchHttpClientService;
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
