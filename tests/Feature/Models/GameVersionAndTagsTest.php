<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameJam;
use App\Models\GameVersion;
use App\Models\Language;
use App\Models\VersionFileCategory;
use App\Models\VersionFileType;
use Illuminate\Support\Facades\Cache;

function gameVersionTagsLanguage(string $id, string $name, ?string $part1 = null, ?string $flag = null): Language
{
    DB::table('iso_639_3_languages')->updateOrInsert([
        'id' => $id,
    ], [
        'part2b' => $id,
        'part2t' => $id,
        'part1' => $part1,
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => $name,
        'flag_code' => $flag ?? strtolower(substr($id, 0, 2)),
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    return Language::findOrFail($id);
}

it('manages supported language availability on versions', function () {
    gameVersionTagsLanguage('eng', 'English', 'en', 'gb');
    gameVersionTagsLanguage('fra', 'French', 'fr', 'fr');
    gameVersionTagsLanguage('deu', 'German', 'de', 'de');
    $source = GameVersion::factory()->create();
    $target = GameVersion::factory()->for($source->game)->create();

    $source->addSupportedLanguage('eng', true);
    $source->addSupportedLanguage('fra', false);
    $target->addSupportedLanguage('eng', false);
    $target->addSupportedLanguage('fra', true);
    $target->addSupportedLanguage('deu', true);

    expect($source->getSupportedLanguageCodes())->toContain('eng', 'fra')
        ->and($source->isLanguageAvailable('eng'))->toBeTrue()
        ->and($source->isLanguageAvailable('fra'))->toBeFalse()
        ->and($source->isLanguageAvailable('jpn'))->toBeFalse()
        ->and($target->setLanguageAvailability('eng', true))->toBeTrue()
        ->and($target->setLanguageAvailability('jpn', true))->toBeFalse();

    $target->copyLanguageAvailabilityFrom($source);

    expect($target->isLanguageAvailable('eng'))->toBeTrue()
        ->and($target->isLanguageAvailable('fra'))->toBeFalse()
        ->and($target->isLanguageAvailable('deu'))->toBeTrue()
        ->and($target->getAvailableLanguages()->pluck('iso_code')->sort()->values()->all())->toBe(['deu', 'eng']);

    $target->removeSupportedLanguage('deu');

    expect($target->fresh()->getSupportedLanguageCodes())->not->toContain('deu');
});

it('saves file stats, caps corrupt sizes, and replaces previous categories', function () {
    $version = GameVersion::factory()->create();
    $version->saveFileStats([
        'summary' => ['total_images' => 2],
        'images' => [
            'png' => ['count' => 1, 'total_size' => 100],
            'webp' => ['count' => 1, 'total_size' => -5],
        ],
    ]);

    $category = VersionFileCategory::where('game_version_id', $version->id)->where('category', 'images')->firstOrFail();

    expect($category->total_count)->toBe(2)
        ->and($category->total_size)->toBe(9223372036854775807)
        ->and(VersionFileType::where('version_file_category_id', $category->id)->where('extension', 'webp')->value('size'))->toBe(9223372036854775807);

    $version->saveFileStats([
        'summary' => ['total_audio' => 1],
        'audio' => [
            'ogg' => ['count' => 1, 'total_size' => 50],
        ],
    ]);

    expect($version->fileCategories()->pluck('category')->all())->toBe(['audio'])
        ->and($version->fileCategories()->first()->fileTypes()->pluck('extension')->all())->toBe(['ogg']);
});

it('syncs loaded tags and pending tag associations without dropping custom tags', function () {
    Cache::forget('games.recommendations.version');
    $game = Game::factory()->create(['custom_tags' => 'Custom Tag, !!!']);

    expect($game->tags_list)->toBe([])
        ->and($game->tags_string)->toBe('');

    $game->syncTagsFromString('Romance, Drama, Romance');

    $loaded = $game->fresh()->load('tags');

    expect($loaded->tags_list)->toBe(['Custom Tag', 'Drama', 'Romance'])
        ->and($loaded->tags_string)->toBe('Custom Tag,Drama,Romance')
        ->and(Cache::get('games.recommendations.version'))->toBeGreaterThan(1);

    $customOnly = Game::factory()->create(['custom_tags' => 'Custom Only']);
    $customOnly->processPendingTags();

    expect($customOnly->fresh()->load('tags')->tags_list)->toBe(['Custom Only']);

    $pending = Game::factory()->make(['name' => 'Pending Game', 'custom_tags' => 'Queued Custom']);
    $pending->syncTagsFromString('Queued Tag');

    expect($pending->pendingTagIds)->toHaveCount(2);
    $pending->save();
    $pending->processPendingTags();

    expect($pending->fresh()->load('tags')->tags_list)->toBe(['Queued Custom', 'Queued Tag']);
});

it('processes pending game jam associations only after the game exists', function () {
    $jam = GameJam::create([
        'name' => 'Jam',
        'url' => 'https://itch.io/jam/example',
        'needs_details_fetch' => false,
    ]);
    $unsaved = Game::factory()->make(['name' => 'Unsaved']);
    $unsaved->pendingGameJamId = [$jam->id];

    $unsaved->processPendingGameJams();

    expect($unsaved->pendingGameJamId)->toBe([$jam->id]);

    $game = Game::factory()->create();
    $game->pendingGameJamId = [$jam->id, $jam->id];
    $game->processPendingGameJams();
    $game->processPendingGameJams();

    expect($game->fresh()->gameJams()->pluck('game_jams.id')->all())->toBe([$jam->id])
        ->and($game->pendingGameJamId)->toBe([]);
});

it('normalizes custom tags to an empty string instead of null', function () {
    $game = new Game;
    $game->custom_tags = null;

    expect($game->custom_tags)->toBe('');
});
