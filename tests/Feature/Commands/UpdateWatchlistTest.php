<?php

declare(strict_types=1);

use App\Console\Commands\UpdateWatchlist;
use App\Models\Game;
use App\Models\Tag;
use App\Services\ItchAuthService;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function processExistingWatchlistGame(Game $game, ?string $blurb): void
{
    $command = new UpdateWatchlist(Mockery::mock(ItchAuthService::class));
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $method = new ReflectionMethod(UpdateWatchlist::class, 'processCollectionGame');
    $method->setAccessible(true);
    $method->invoke($command, [
        'id' => $game->itch_id,
        'title' => $game->name,
        'short_text' => $game->description,
        'cover_url' => $game->thumb_url,
        'url' => $game->getUrlForPlatform('itch_io'),
    ], false, $blurb);
}

test('watchlist collection blurbs can tag existing games', function () {
    $game = Game::factory()->create([
        'itch_id' => 123456,
        'name' => 'Confirmed AI Game',
        'description' => 'Test description',
        'thumb_url' => 'https://example.com/thumb.jpg',
        'custom_tags' => '',
        'is_visible' => true,
        'is_paid' => false,
    ]);

    processExistingWatchlistGame($game, 'tag:ai-generated tag:"confirmed use" tag:ai-generated');

    $game->refresh()->load('tags');

    expect($game->custom_tags)->toBe('AI Generated, Confirmed Use')
        ->and($game->tags->pluck('slug')->all())->toEqualCanonicalizing([
            'ai-generated',
            'confirmed-use',
        ]);
});

test('watchlist collection tags reuse existing database tags', function () {
    $tag = Tag::create(['name' => 'AI Generated']);
    $game = Game::factory()->create([
        'itch_id' => 223344,
        'name' => 'Existing Tag Game',
        'description' => 'Test description',
        'thumb_url' => 'https://example.com/thumb.jpg',
        'custom_tags' => '',
        'is_visible' => true,
        'is_paid' => false,
    ]);

    processExistingWatchlistGame($game, 'tag:ai-generated');

    $game->refresh()->load('tags');

    expect(Tag::where('slug', 'ai-generated')->count())->toBe(1)
        ->and($game->tags->pluck('id')->all())->toBe([$tag->id]);
});

test('watchlist collection sync without tag markers preserves existing custom tags', function () {
    $game = Game::factory()->create([
        'itch_id' => 654321,
        'name' => 'Manually Tagged Game',
        'description' => 'Test description',
        'thumb_url' => 'https://example.com/thumb.jpg',
        'custom_tags' => 'Manual Tag',
        'is_visible' => true,
        'is_paid' => false,
    ]);

    processExistingWatchlistGame($game, null);

    $game->refresh()->load('tags');

    expect($game->custom_tags)->toBe('Manual Tag')
        ->and($game->tags->pluck('slug')->all())->toEqualCanonicalizing(['manual-tag']);
});
