<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Language;
use App\Models\LanguageMapping;
use App\Services\LanguageMappingService;
use App\Services\PlatformDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
