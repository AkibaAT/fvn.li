<?php

declare(strict_types=1);

use App\Console\Commands\UpdateWatchlist;
use App\Models\Game;
use App\Models\Language;
use App\Services\ItchAuthService;
use Symfony\Component\Console\Input\ArrayInput;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\BufferedOutput;

function watchlistCommandForTesting(): UpdateWatchlist
{
    $command = new UpdateWatchlist(Mockery::mock(ItchAuthService::class));
    $input = new ArrayInput([]);
    $output = new BufferedOutput;
    $command->setLaravel(app());
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $output));

    return $command;
}

function ensureWatchlistLanguage(string $id, string $name, string $part1, string $flag): void
{
    Language::withoutEvents(fn () => Language::firstOrCreate([
        'id' => $id,
    ], [
        'part2b' => $id,
        'part2t' => $id,
        'part1' => $part1,
        'scope' => 'I',
        'type' => 'L',
        'ref_name' => $name,
        'flag_code' => $flag,
    ]));
}

function processWatchlistGame(UpdateWatchlist $command, array $gameData, bool $isPaid, ?string $blurb = null): void
{
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('processCollectionGame');
    $method->setAccessible(true);
    $method->invoke($command, $gameData, $isPaid, $blurb);
}

it('updates existing watchlist games with paid pricing sale data and custom tags', function () {
    ensureWatchlistLanguage('eng', 'English', 'en', 'gb');

    $game = Game::factory()->create([
        'itch_id' => 12345,
        'name' => 'Old Title',
        'status' => 'Released',
        'description' => 'Old blurb',
        'thumb_url' => 'https://old.example/thumb.jpg',
        'is_visible' => true,
        'is_paid' => true,
        'source_language_id' => 'eng',
        'screenshots' => [['url' => 'https://img.example/shot.jpg']],
        'full_description' => '<p>Already synced</p>',
    ]);

    processWatchlistGame(watchlistCommandForTesting(), [
        'id' => 12345,
        'title' => 'New Watchlist Title',
        'short_text' => 'New blurb',
        'cover_url' => 'https://new.example/thumb.jpg',
        'published_at' => now()->subYear()->toISOString(),
        'url' => 'https://creator.itch.io/new-watchlist-title',
        'min_price' => 799,
        'sale' => ['rate' => 25],
    ], true, 'tag:ai-assets tag:kinetic-novel');

    $game->refresh();

    expect($game->name)->toBe('New Watchlist Title')
        ->and($game->description)->toBe('New blurb')
        ->and($game->thumb_url)->toBe('https://new.example/thumb.jpg')
        ->and($game->is_paid)->toBeTrue()
        ->and((float) $game->min_price)->toBe(7.99)
        ->and($game->is_on_sale)->toBeTrue()
        ->and($game->sale_discount_percent)->toBe(25)
        ->and($game->custom_tags)->toBe('Ai Assets, Kinetic Novel')
        ->and($game->tags()->pluck('slug')->all())->toContain('ai-assets', 'kinetic-novel');
});

it('skips collection games with invalid source language blurbs', function () {
    processWatchlistGame(watchlistCommandForTesting(), [
        'id' => 99999,
        'title' => 'Invalid Language Game',
        'short_text' => 'Should not be created',
        'cover_url' => null,
        'published_at' => now()->toISOString(),
        'url' => 'https://creator.itch.io/invalid-language-game',
    ], false, 'lang:zzz');

    expect(Game::where('itch_id', 99999)->exists())->toBeFalse();
});
