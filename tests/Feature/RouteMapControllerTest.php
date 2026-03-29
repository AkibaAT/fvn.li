<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\VersionRouteLabel;
use App\Models\VersionSupportedLanguage;

uses()->group('route-map');

test('route map only exposes languages that are available for the selected version', function () {
    config()->set('scout.driver', 'null');

    Language::query()->upsert(
        [
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
                'id' => 'jpn',
                'scope' => 'I',
                'type' => 'L',
                'ref_name' => 'Japanese',
                'part1' => 'ja',
                'flag_code' => 'jp',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ],
        ['id'],
        ['scope', 'type', 'ref_name', 'part1', 'flag_code', 'updated_at']
    );

    $game = Game::withoutEvents(fn () => Game::factory()->create([
        'name' => 'Route Map Languages',
        'slug' => 'route-map-languages',
        'is_visible' => true,
    ]));

    $version = GameVersion::withoutEvents(fn () => GameVersion::factory()->create([
        'game_id' => $game->id,
        'version' => '1.0.0',
    ]));
    $version->is_latest = true;
    $version->saveQuietly();

    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'eng',
        'is_available' => true,
    ]);
    VersionSupportedLanguage::create([
        'game_version_id' => $version->id,
        'iso_code' => 'jpn',
        'is_available' => false,
    ]);
    VersionRouteLabel::create([
        'game_version_id' => $version->id,
        'name' => 'start',
        'file_path' => 'game/script.rpy',
        'line_number' => 1,
        'is_ending' => true,
    ]);

    $disabledLanguageResponse = $this->get(route('games.route-map', [
        'game' => $game->slug,
        'lang' => 'jpn',
    ]));

    $disabledLanguageResponse->assertOk();

    $disabledLanguageProps = $disabledLanguageResponse->viewData('page')['props'];

    expect($disabledLanguageProps['availableLanguages'])->toBe(['eng'])
        ->and($disabledLanguageProps['currentLanguage'])->toBeNull();

    $availableLanguageResponse = $this->get(route('games.route-map', [
        'game' => $game->slug,
        'lang' => 'eng',
    ]));

    $availableLanguageResponse->assertOk();

    $availableLanguageProps = $availableLanguageResponse->viewData('page')['props'];

    expect($availableLanguageProps['availableLanguages'])->toBe(['eng'])
        ->and($availableLanguageProps['currentLanguage'])->toBe('eng');

    $this->getJson(route('react-api.games.version.route-graph', [
        'game' => $game->slug,
        'version' => $version->id,
    ]))
        ->assertOk()
        ->assertJsonPath('available_languages', ['eng']);
});
