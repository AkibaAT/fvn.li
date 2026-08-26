<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VnList;
use App\Models\VnListEntry;
use Illuminate\Support\Facades\Cache;

function linkedRatingGame(): Game
{
    return Game::factory()->create([
        'name' => 'Linked Rating Game',
        'is_visible' => true,
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://example.itch.io/linked-rating-game'],
    ]);
}

it('links an existing itch rater when a matching social account is saved', function () {
    $user = User::factory()->create();
    $rater = Rater::factory()->create([
        'itch_id' => 123456,
        'external_platform' => 'itch_io',
    ]);

    expect($rater->fresh()->user_id)->toBeNull();

    SocialAccount::factory()->itchio()->for($user)->create([
        'provider_id' => '123456',
    ]);

    expect($rater->fresh()->user_id)->toBe($user->id);
});

it('links an existing steam rater when a matching social account is saved', function () {
    $user = User::factory()->create();
    $rater = Rater::factory()->create([
        'itch_id' => null,
        'steam_id' => '76561198000000000',
        'external_platform' => 'steam',
    ]);

    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'steam',
        'provider_id' => '76561198000000000',
    ]);

    expect($rater->fresh()->user_id)->toBe($user->id);
});

it('sets user_id when a rater is created after the social account exists', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->itchio()->for($user)->create([
        'provider_id' => '654321',
    ]);

    $rater = Rater::factory()->create([
        'itch_id' => 654321,
        'external_platform' => 'itch_io',
    ]);

    expect($rater->user_id)->toBe($user->id);
});

it('clears the rater user link when the matching social account is deleted', function () {
    $user = User::factory()->create();
    $rater = Rater::factory()->create([
        'itch_id' => 777888,
        'external_platform' => 'itch_io',
    ]);
    $account = SocialAccount::factory()->itchio()->for($user)->create([
        'provider_id' => '777888',
    ]);

    expect($rater->fresh()->user_id)->toBe($user->id);

    $account->delete();

    expect($rater->fresh()->user_id)->toBeNull();
});

it('shows the linked site user on imported ratings on the ratings index', function () {
    Cache::flush();
    $user = User::factory()->create(['name' => 'Linked Reviewer']);
    $game = linkedRatingGame();
    $rater = Rater::factory()->create([
        'name' => 'Itch Handle',
        'itch_id' => 111222,
        'external_platform' => 'itch_io',
    ]);
    SocialAccount::factory()->itchio()->for($user)->create([
        'provider_id' => '111222',
    ]);
    $rating = Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => '<p>Imported itch review.</p>',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $response = $this->get(route('ratings.index', [
        'showOnlyReviews' => 'true',
        'showOnlyVisibleGames' => 'true',
    ]));

    $response->assertOk();
    $row = collect($response->viewData('page')['props']['ratings']['data'])->firstWhere('id', $rating->id);

    expect($row)->not->toBeNull()
        ->and($row['user']['id'])->toBe($user->id)
        ->and($row['user']['name'])->toBe('Linked Reviewer')
        ->and($row['rater']['id'])->toBe($rater->id)
        ->and($row['source_platform'])->toBe('itch_io');
});

it('shows linked itch ratings on the list owner reading list', function () {
    $owner = User::factory()->create();
    $game = linkedRatingGame();
    $list = $owner->vnLists()->where('type', 'reading')->first()
        ?? VnList::factory()->for($owner)->create([
            'name' => 'Reading List',
            'type' => 'reading',
            'is_public' => false,
        ]);
    VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
        'sort_order' => 1,
    ]);
    $rater = Rater::factory()->create([
        'itch_id' => 333444,
        'external_platform' => 'itch_io',
    ]);
    SocialAccount::factory()->itchio()->for($owner)->create([
        'provider_id' => '333444',
    ]);
    $rating = Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 4,
        'review' => 'Itch take.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('lists.show', $list));

    $response->assertOk();
    $ratings = $response->viewData('page')['props']['vnList']['entries'][0]['game']['ratings'];

    expect($ratings)->toHaveCount(1)
        ->and($ratings[0]['id'])->toBe($rating->id)
        ->and((int) $ratings[0]['rating'])->toBe(4);
});

it('includes linked rater reviews on the user reviews page', function () {
    $user = User::factory()->create(['name' => 'Linked Author']);
    $game = linkedRatingGame();
    $rater = Rater::factory()->create([
        'itch_id' => 555666,
        'external_platform' => 'itch_io',
    ]);
    SocialAccount::factory()->itchio()->for($user)->create([
        'provider_id' => '555666',
    ]);
    $rating = Rating::create([
        'game_id' => $game->id,
        'rater_id' => $rater->id,
        'rating' => 5,
        'review' => 'From itch.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $response = $this->get(route('users.reviews', $user));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['reviews']['total'])->toBe(1)
        ->and($props['reviews']['data'][0]['id'])->toBe($rating->id)
        ->and($props['reviews']['data'][0]['source_platform'])->toBe('itch_io')
        ->and($props['stats']['total_ratings'])->toBe(1);
});
