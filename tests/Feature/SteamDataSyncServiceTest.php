<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
use App\Services\ImageProcessingService;
use App\Services\SteamDataSyncService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeSteamService(?Client $httpClient = null): SteamDataSyncService
{
    $service = new SteamDataSyncService();

    if ($httpClient) {
        setSteamProperty($service, 'httpClient', $httpClient);
    }

    return $service;
}

function invokeSteamMethod(SteamDataSyncService $service, string $method, mixed ...$args): mixed
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($service, ...$args);
}

function setSteamProperty(SteamDataSyncService $service, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty($service, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($service, $value);
}

function getSteamProperty(SteamDataSyncService $service, string $property): mixed
{
    $reflection = new ReflectionProperty($service, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($service);
}

function seedSteamLanguages(): void
{
    DB::table('iso_639_3_languages')->insertOrIgnore([
        [
            'id' => 'eng',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 'fra',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'French',
            'part1' => 'fr',
            'flag_code' => 'fr',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}

it('syncs a current Steam game version with parsed platforms and languages', function () {
    seedSteamLanguages();

    $service = makeSteamService();
    $game = Game::factory()->create([
        'platform' => 'steam',
        'steam_app_id' => '123456',
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Test_Game/'],
    ]);
    $olderVersion = GameVersion::factory()->for($game)->latest()->create([
        'version' => 'old',
    ]);

    setSteamProperty($service, 'parsedPlatforms', [
        'windows' => true,
        'linux' => true,
        'mac' => false,
    ]);
    setSteamProperty($service, 'parsedLanguageIsoCodes', ['eng', 'fra']);

    $service->syncGameVersion($game);

    $current = $game->gameVersions()->where('version', 'current')->firstOrFail();
    $olderVersion->refresh();

    expect($current->is_latest)->toBeTrue()
        ->and($current->is_windows)->toBeTrue()
        ->and($current->is_linux)->toBeTrue()
        ->and($current->is_mac)->toBeFalse()
        ->and($current->is_android)->toBeFalse()
        ->and($current->getSupportedLanguageCodes())->toBe(['eng', 'fra'])
        ->and($olderVersion->is_latest)->toBeFalse()
        ->and(getSteamProperty($service, 'parsedLanguageIsoCodes'))->toBe([])
        ->and(getSteamProperty($service, 'parsedPlatforms'))->toBe([]);
});

it('returns early when adding languages without parsed languages or a latest version', function () {
    $service = makeSteamService();
    $game = Game::factory()->create(['platform' => 'steam']);

    $service->addLanguagesToVersion($game, []);
    $service->addLanguagesToVersion($game, ['eng']);

    expect($game->gameVersions()->count())->toBe(0);
});

it('extracts Steam API data into game fields and parsed language/platform state', function () {
    $game = Game::factory()->create([
        'platform' => 'steam',
        'name' => 'Before API',
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Test_Game/'],
        'screenshots' => [],
    ]);
    $httpClient = Mockery::mock(Client::class);
    $httpClient->shouldReceive('get')
        ->once()
        ->with('https://store.steampowered.com/api/appdetails?appids=123456&cc=us&l=english')
        ->andReturn(new Response(200, [], <<<'JSON'
{"123456":{"success":true,"data":{"name":"Steam VN","short_description":"Short description","detailed_description":"<p>Full description</p>","is_free":false,"price_overview":{"initial":1299,"discount_percent":25,"currency":"eur"},"release_date":{"date":"Apr 12, 2024","coming_soon":false},"header_image":"https://cdn.example/header.jpg","screenshots":[{"path_full":"https://cdn.example/full.jpg","path_thumbnail":"https://cdn.example/thumb.jpg"}],"demos":[{"appid":654321}],"developers":["Dev A","Dev B"],"platforms":{"windows":true,"linux":true,"mac":false},"genres":[{"description":"Visual Novel"},{"description":"Adventure"}],"supported_languages":"English<strong>*</strong>, French, Simplified Chinese<br><strong>*</strong>languages with full audio support","content_descriptors":{"ids":[3]}}}}
JSON));
    $service = makeSteamService($httpClient);

    invokeSteamMethod($service, 'refreshFromSteamApi', $game, '123456');

    expect($game->name)->toBe('Steam VN')
        ->and($game->description)->toBe('Short description')
        ->and($game->full_description)->toBe('<p>Full description</p>')
        ->and($game->is_paid)->toBeTrue()
        ->and($game->min_price)->toBe(12.99)
        ->and($game->currency)->toBe('EUR')
        ->and($game->is_on_sale)->toBeTrue()
        ->and($game->sale_discount_percent)->toBe(25)
        ->and($game->thumb_url)->toBe('https://cdn.example/header.jpg')
        ->and($game->screenshots)->toBe([
            [
                'url' => 'https://cdn.example/full.jpg',
                'thumbnail_url' => 'https://cdn.example/thumb.jpg',
            ],
        ])
        ->and($game->has_demo)->toBeTrue()
        ->and($game->developer)->toBe('Dev A, Dev B')
        ->and($game->authors)->toBe('Dev A, Dev B')
        ->and($game->steam_genres)->toBe(['Visual Novel', 'Adventure'])
        ->and($game->steam_languages)->toContain('English')
        ->and($game->is_nsfw)->toBeTrue()
        ->and($game->status)->toBe('Released')
        ->and(getSteamProperty($service, 'parsedPlatforms'))->toBe([
            'windows' => true,
            'linux' => true,
            'mac' => false,
        ])
        ->and(getSteamProperty($service, 'parsedLanguageIsoCodes'))->toBe(['eng', 'fra', 'zho']);
});

it('throws when the Steam API response is unsuccessful', function () {
    $game = Game::factory()->create([
        'platform' => 'steam',
        'url' => ['steam' => 'https://store.steampowered.com/app/123456/Test_Game/'],
    ]);
    $httpClient = Mockery::mock(Client::class);
    $httpClient->shouldReceive('get')
        ->once()
        ->andReturn(new Response(200, [], '{"123456":{"success":false}}'));

    invokeSteamMethod(makeSteamService($httpClient), 'refreshFromSteamApi', $game, '123456');
})->throws(Exception::class, 'Steam API returned unsuccessful response');

it('extracts Steam store tags and ignores store page failures', function () {
    $game = Game::factory()->create(['platform' => 'steam']);
    $httpClient = Mockery::mock(Client::class);
    $httpClient->shouldReceive('get')
        ->once()
        ->withAnyArgs()
        ->andReturn(new Response(200, [], <<<'HTML'
<html><body>
    <a class="app_tag">Visual Novel</a>
    <a class="app_tag">+</a>
    <a class="app_tag">LGBTQ+</a>
</body></html>
HTML));
    $httpClient->shouldReceive('get')
        ->once()
        ->andThrow(new RuntimeException('store page unavailable'));
    $service = makeSteamService($httpClient);

    invokeSteamMethod($service, 'refreshFromStorePage', $game, 'https://store.steampowered.com/app/123456/Test_Game/');
    invokeSteamMethod($service, 'refreshFromStorePage', $game, 'https://store.steampowered.com/app/123456/Test_Game/');

    expect($game->steam_user_tags)->toBe(['Visual Novel', 'LGBTQ+']);
});

it('syncs Steam genres and user tags to local tags without duplicate case variants', function () {
    $service = makeSteamService();
    $game = Game::factory()->create([
        'steam_genres' => ['Adventure', 'Visual Novel'],
        'steam_user_tags' => ['visual novel', 'Story Rich'],
    ]);
    $existing = Tag::create(['name' => 'Visual Novel']);

    invokeSteamMethod($service, 'syncSteamTags', $game);

    expect($game->tags()->pluck('name')->sort()->values()->all())->toBe([
        'Adventure',
        'Story Rich',
        'Visual Novel',
    ])
        ->and($game->tags()->whereKey($existing->id)->exists())->toBeTrue();
});

it('compares screenshot changes by source URLs only', function () {
    $service = makeSteamService();

    expect(invokeSteamMethod($service, 'screenshotUrlsChanged', [
        ['url' => 'https://cdn.example/1.jpg', 'optimized' => ['ignored' => true]],
    ], [
        ['url' => 'https://cdn.example/1.jpg'],
    ]))->toBeFalse()
        ->and(invokeSteamMethod($service, 'screenshotUrlsChanged', [
            ['url' => 'https://cdn.example/1.jpg'],
        ], [
            ['url' => 'https://cdn.example/2.jpg'],
        ]))->toBeTrue()
        ->and(invokeSteamMethod($service, 'extractScreenshotUrls', null))->toBe([]);
});

it('fails load full details when a game has no Steam URL and records the error', function () {
    $service = makeSteamService();
    $game = Game::factory()->create([
        'platform' => 'steam',
        'url' => [],
    ]);

    try {
        $service->loadFullDetails($game);
        $this->fail('Expected loadFullDetails to throw.');
    } catch (Exception $exception) {
        expect($exception->getMessage())->toBe('Game does not have a Steam URL')
            ->and($game->error)->toBe('Game does not have a Steam URL');
    }
});

it('processes changed Steam images through the image processing service', function () {
    $service = makeSteamService();
    $game = Game::factory()->create([
        'thumb_url' => 'https://cdn.example/new-header.jpg',
        'screenshots' => [
            ['url' => 'https://cdn.example/new-shot.jpg'],
        ],
        'optimized_thumbnails' => [],
    ]);

    $imageService = Mockery::mock(ImageProcessingService::class);
    $imageService->shouldReceive('processGameScreenshots')->once()->with($game);
    $imageService->shouldReceive('processGameThumbnail')->once()->with($game);
    $this->app->instance(ImageProcessingService::class, $imageService);

    invokeSteamMethod($service, 'processImages', $game, 'https://cdn.example/old-header.jpg', [
        ['url' => 'https://cdn.example/old-shot.jpg'],
    ]);
});
