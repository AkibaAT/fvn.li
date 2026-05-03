<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use App\Services\ItchCssProcessor;
use App\Services\ItchGameMetadataExtractor;
use App\Services\ItchHtmlProcessor;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Storage;

function itchMetadataDocument(string $html): HTMLDocument
{
    return HTMLDocument::createFromString($html, LIBXML_NOERROR);
}

it('detects paid game demos from browser play and upload metadata but ignores unpaid games', function () {
    $extractor = new ItchGameMetadataExtractor;

    $unpaid = Game::factory()->make([
        'name' => 'Free Game',
        'is_paid' => false,
        'has_demo' => true,
        'uploads' => [['filename' => 'demo.zip']],
    ]);

    $extractor->checkForDemo($unpaid, itchMetadataDocument('<a data-action="play_in_browser"></a>'));

    expect($unpaid->has_demo)->toBeFalse();

    $paidBrowser = Game::factory()->make([
        'name' => 'Browser Demo',
        'is_paid' => true,
        'uploads' => [],
    ]);
    $extractor->checkForDemo($paidBrowser, itchMetadataDocument('<button class="play_btn">Play</button>'));

    $paidUpload = Game::factory()->make([
        'name' => 'Upload Demo',
        'is_paid' => true,
        'uploads' => [
            ['filename' => 'full-build.zip', 'display_name' => 'Full Build', 'traits' => []],
            ['filename' => 'trial-build.zip', 'display_name' => 'Trial Build', 'traits' => ['p_free']],
        ],
    ]);
    $extractor->checkForDemo($paidUpload, itchMetadataDocument('<html></html>'));

    $paidTrait = Game::factory()->make([
        'name' => 'Trait Demo',
        'is_paid' => true,
        'uploads' => [
            ['filename' => 'game.zip', 'display_name' => 'Game', 'traits' => ['demo']],
        ],
    ]);
    $extractor->checkForDemo($paidTrait, itchMetadataDocument('<html></html>'));

    expect($paidBrowser->has_demo)->toBeTrue()
        ->and($paidUpload->has_demo)->toBeTrue()
        ->and($paidTrait->has_demo)->toBeTrue();
});

it('extracts full descriptions and fills short descriptions only when missing', function () {
    $extractor = new ItchGameMetadataExtractor;
    $htmlProcessor = Mockery::mock(ItchHtmlProcessor::class);
    $htmlProcessor->shouldReceive('process')
        ->once()
        ->with('<p><strong>Long text</strong></p>')
        ->andReturn('<p><strong>Processed long text</strong></p>');

    $game = Game::factory()->make(['description' => null]);

    $extractor->extractFullDescription(
        $game,
        itchMetadataDocument('<div class="formatted_description"><p><strong>Long text</strong></p></div>'),
        $htmlProcessor
    );

    expect($game->full_description)->toBe('<p><strong>Processed long text</strong></p>')
        ->and($game->description)->toBe('Processed long text');

    $existingDescription = Game::factory()->make(['description' => 'Already short']);
    $htmlProcessor->shouldReceive('process')
        ->once()
        ->andReturn('<p>Another long text</p>');

    $extractor->extractFullDescription(
        $existingDescription,
        itchMetadataDocument('<div class="formatted_description"><p>Another long text</p></div>'),
        $htmlProcessor
    );

    expect($existingDescription->description)->toBe('Already short');
});

it('extracts screenshots from multiple itch carousel shapes and cleans optimized files removed from order', function () {
    Storage::fake('public');

    $extractor = new ItchGameMetadataExtractor;
    $game = Game::factory()->create([
        'screenshots' => [
            [
                'url' => 'https://img.example/old.png',
                'thumbnail_url' => 'https://img.example/old-thumb.png',
                'optimized' => true,
            ],
            [
                'url' => 'https://img.example/keep.png',
                'thumbnail_url' => 'https://img.example/keep-thumb.png',
            ],
        ],
    ]);

    Storage::disk('public')->put("screenshots/{$game->id}_screenshot_0_abcdef12.webp", 'old optimized');

    $extractor->extractScreenshots($game, itchMetadataDocument(<<<'HTML'
<div class="screenshot_list">
    <a class="screenshot_link" href="https://img.example/keep.png"><img src="https://img.example/keep-thumb.png"></a>
    <a class="screenshot_link" href="https://img.example/new.webp"></a>
    <a class="screenshot_link" href="https://img.example/readme.txt"></a>
</div>
HTML));

    expect($game->screenshots)->toBe([
        ['url' => 'https://img.example/keep.png', 'thumbnail_url' => 'https://img.example/keep-thumb.png'],
        ['url' => 'https://img.example/new.webp', 'thumbnail_url' => 'https://img.example/new.webp'],
    ])
        ->and(Storage::disk('public')->exists("screenshots/{$game->id}_screenshot_0_abcdef12.webp"))->toBeFalse();

    $fallbackGame = Game::factory()->make(['screenshots' => []]);
    $extractor->extractScreenshots($fallbackGame, itchMetadataDocument(<<<'HTML'
<div class="screenshot_list">
    <a data-image_lightbox="true" href="https://img.example/lightbox.jpg"><img src="https://img.example/lightbox-thumb.jpg"></a>
</div>
HTML));

    expect($fallbackGame->screenshots[0]['url'])->toBe('https://img.example/lightbox.jpg');
});

it('extracts and scopes custom CSS or clears it when processing returns empty', function () {
    $extractor = new ItchGameMetadataExtractor;
    $cssProcessor = Mockery::mock(ItchCssProcessor::class);
    $cssProcessor->shouldReceive('process')
        ->once()
        ->with(".theme { color: red; }\n\n.custom { color: blue; }")
        ->andReturn('.theme { color: red; }');

    $game = Game::factory()->make();

    $extractor->extractCustomCss($game, <<<'HTML'
<style id="game_theme">.theme { color: red; }</style>
<style id="custom_css">.custom { color: blue; }</style>
HTML, $cssProcessor);

    expect($game->custom_css)->toBe(".game_description {\n.theme { color: red; }\n}");

    $cssProcessor->shouldReceive('process')
        ->once()
        ->andReturn('');

    $extractor->extractCustomCss($game, '<style id="custom_css">bad css</style>', $cssProcessor);

    expect($game->custom_css)->toBeNull();

    $extractor->extractCustomCss($game, '<html></html>', $cssProcessor);

    expect($game->custom_css)->toBeNull();
});

it('extracts game jam associations from explicit and fallback itch links without duplicates', function () {
    $extractor = new ItchGameMetadataExtractor;
    $game = Game::factory()->make(['pendingGameJamId' => []]);

    $extractor->extractGameJamInfo($game, itchMetadataDocument(<<<'HTML'
<div class="game_jam_info"><a href="https://itch.io/jam/explicit-jam/rate/123">Explicit Jam</a></div>
HTML));

    $jam = GameJam::where('url', 'https://itch.io/jam/explicit-jam')->first();

    expect($jam)->not->toBeNull()
        ->and($jam->name)->toBe('Explicit Jam')
        ->and($game->pendingGameJamId)->toBe([$jam->id]);

    $extractor->extractGameJamInfo($game, itchMetadataDocument(<<<'HTML'
<a href="/jam/explicit-jam/rate/456">Submission to Explicit Jam</a>
HTML));

    expect($game->pendingGameJamId)->toBe([$jam->id]);

    $fallback = Game::factory()->make(['pendingGameJamId' => []]);
    $extractor->extractGameJamInfo($fallback, itchMetadataDocument(<<<'HTML'
<title>Fallback Jam - itch.io</title>
<a href="https://itch.io/jam/fallback-jam">Jam page</a>
HTML));

    $fallbackJam = GameJam::where('url', 'https://itch.io/jam/fallback-jam')->first();

    expect($fallbackJam)->not->toBeNull()
        ->and($fallbackJam->name)->toBe('Fallback Jam')
        ->and($fallback->pendingGameJamId)->toBe([$fallbackJam->id]);
});
