<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Support\Facades\DB;

function generateUniqueSlug(string $name, int $id): string
{
    return DB::selectOne('SELECT generate_unique_slug(?, ?) AS slug', [$name, $id])->slug;
}

// The model rewrites slugs on save, so conflicting slugs are planted directly on the row.
function gameWithSlug(string $slug): Game
{
    $game = Game::factory()->create();

    DB::table('games')->where('id', $game->id)->update(['slug' => $slug]);

    return $game;
}

it('slugifies a name into ascii characters', function () {
    expect(generateUniqueSlug('Some Game: The Sequel!', 1))->toBe('some-game-the-sequel');
});

it('falls back to a stable id slug when a name has no slug characters', function () {
    expect(generateUniqueSlug('日本語!!!', 42))->toBe('game-42');
});

it('appends a counter when the slug is already taken by another game', function () {
    $taken = gameWithSlug('shared-name');

    expect(generateUniqueSlug('Shared Name', $taken->id + 1))->toBe('shared-name-1');
});

it('keeps its own slug when the only match is the game itself', function () {
    $game = gameWithSlug('existing-slug');

    expect(generateUniqueSlug('Existing Slug', $game->id))->toBe('existing-slug');
});
