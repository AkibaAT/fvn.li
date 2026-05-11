<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Language;
use App\Models\LanguageMapping;
use App\Services\GameSearchFilterService;
use App\Services\ItchCssProcessor;
use App\Services\LanguageMappingService;
use App\Services\PlatformDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('detects platforms and extracts platform identifiers from URLs', function () {
    $service = new PlatformDetectionService;

    expect($service->detectPlatform('https://creator.itch.io/game-name'))->toBe('itch_io')
        ->and($service->detectPlatform('https://store.steampowered.com/app/1084640/Game/'))->toBe('steam')
        ->and($service->detectPlatform('https://example.com/game'))->toBe('other')
        ->and($service->extractPlatformId('https://creator.itch.io/game-name?download', 'itch_io'))->toBe('creator/game-name')
        ->and($service->extractPlatformId('https://store.steampowered.com/app/1084640/Game/', 'steam'))->toBe('1084640')
        ->and($service->extractPlatformId('https://example.com/game', 'other'))->toBeNull()
        ->and($service->getPlatformName('itch_io'))->toBe('itch.io')
        ->and($service->getPlatformName('steam'))->toBe('Steam')
        ->and($service->getPlatformName('unknown'))->toBe('Unknown')
        ->and($service->isValidPlatform('other'))->toBeTrue()
        ->and($service->isValidPlatform('bad'))->toBeFalse()
        ->and($service->getSupportedPlatforms())->toBe(['itch_io', 'steam', 'other']);
});

it('builds game search filters and normalized search params from requests', function () {
    $service = new GameSearchFilterService;
    $request = Request::create('/games', 'GET', [
        'selectedStatuses' => 'Released,In Development',
        'selectedEngines' => ['Ren\'Py', ''],
        'selectedPlatforms' => ['windows', 'linux'],
        'selectedLanguages' => 'eng,jpn',
        'selectedTags' => ['romance'],
        'sfw' => '1',
        'showFree' => '1',
        'showDemo' => '1',
        'showSale' => '1',
        'search' => ' visual novel ',
        'perPage' => '100',
        'page' => '3',
    ]);

    expect($service->buildFiltersFromRequest($request))->toMatchArray([
        'is_visible' => true,
        'status' => ['Released', 'In Development'],
        'game_engine' => ['Ren\'Py'],
        'is_windows' => true,
        'is_linux' => true,
        'supported_languages' => ['eng', 'jpn'],
        'tags' => ['romance'],
        'is_nsfw' => false,
        'is_paid' => false,
        'has_demo' => true,
        'is_on_sale' => true,
    ]);

    expect($service->getSearchParams($request))->toMatchArray([
        'search' => ' visual novel ',
        'isSearching' => true,
        'sortField' => 'relevance',
        'sortDirection' => 'desc',
        'perPage' => 32,
        'page' => 3,
    ]);

    expect($service->getSearchParams(Request::create('/games'))['sortField'])->toBe('first_visible_at')
        ->and($service->getReviewSortParams(Request::create('/reviews', 'GET', ['sort' => 'rating_low'])))->toBe(['rating', 'asc'])
        ->and($service->getReviewSortParams(Request::create('/reviews', 'GET', ['sort' => 'bad'])))->toBe(['published_at', 'desc']);
});

it('builds enhanced API filters while preserving explicit false booleans', function () {
    $service = new GameSearchFilterService;
    $request = Request::create('/api/games', 'GET', [
        'status' => 'Released',
        'is_nsfw' => '0',
        'is_paid' => '1',
        'has_demo' => '0',
        'game_engine' => 'Ren\'Py',
        'tags' => ['drama'],
        'supported_languages' => ['eng'],
    ]);

    expect($service->buildEnhancedApiFilters($request))->toBe([
        'status' => 'Released',
        'is_nsfw' => false,
        'is_paid' => true,
        'has_demo' => false,
        'game_engine' => 'Ren\'Py',
        'tags' => ['drama'],
        'supported_languages' => ['eng'],
        'is_visible' => true,
    ]);
});

it('removes unsafe color and heading CSS while preserving layout declarations', function () {
    $processor = new ItchCssProcessor;
    $css = <<<'CSS'
        h1, .game h2 { color: red; margin: 1rem; }
        .panel {
            color: #fff;
            border: 1px solid rgb(0, 0, 0);
            background-image: linear-gradient(red, blue);
            padding: 2rem;
            display: grid;
        }
        @media (min-width: 600px) {
            .panel { box-shadow: 0 0 2px black; gap: 1rem; }
        }
    CSS;

    $result = $processor->process($css);

    expect($result)->not->toContain('h1')
        ->and($result)->not->toContain('color')
        ->and($result)->not->toContain('linear-gradient')
        ->and($result)->toContain('padding')
        ->and($result)->not->toContain('display')
        ->and($result)->not->toContain('gap');
});

it('returns null for empty or invalid CSS', function () {
    $processor = new ItchCssProcessor;

    expect($processor->process(null))->toBeNull()
        ->and($processor->process(''))->toBeNull();
});

it('resolves language keys from game mappings global mappings languages and placeholders', function () {
    $service = new LanguageMappingService;
    $game = Game::factory()->create();
    Language::withoutEvents(fn () => Language::create([
        'id' => 'eng',
        'part2b' => 'eng',
        'part2t' => 'eng',
        'part1' => 'en',
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => 'English',
        'flag_code' => 'gb',
    ]));
    Language::withoutEvents(fn () => Language::create([
        'id' => 'quc',
        'part2b' => 'quc',
        'part2t' => 'quc',
        'part1' => null,
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => "K'iche'",
        'flag_code' => 'gt',
    ]));
    LanguageMapping::create([
        'game_id' => $game->id,
        'game_language_key' => 'custom',
        'iso_code' => 'eng',
    ]);
    LanguageMapping::create([
        'game_id' => null,
        'game_language_key' => 'global',
        'iso_code' => 'eng',
    ]);
    LanguageMapping::create([
        'game_id' => null,
        'game_language_key' => 'real-q-language',
        'iso_code' => 'quc',
    ]);

    expect($service->resolveLanguageCode('CUSTOM', $game))->toBe('eng')
        ->and($service->resolveLanguageCode('GLOBAL', $game))->toBe('eng')
        ->and($service->resolveLanguageCode('quc'))->toBe('quc')
        ->and($service->resolveLanguageCode('en'))->toBe('eng')
        ->and(LanguageMapping::where('game_language_key', 'en')->where('iso_code', 'eng')->exists())->toBeTrue()
        ->and($service->resolveLanguageCode('made-up-language'))->toBe('qaa')
        ->and($service->resolveLanguageCode('another-made-up-language'))->toBe('qab');
});

it('throws when placeholder language codes are exhausted', function () {
    LanguageMapping::create([
        'game_id' => null,
        'game_language_key' => 'last-placeholder',
        'iso_code' => 'qtz',
    ]);

    expect(fn () => app(LanguageMappingService::class)->resolveLanguageCode('overflow-language'))
        ->toThrow(RuntimeException::class, 'No more placeholder codes available');
});
