<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\VnList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('user has correct fillable attributes', function () {
    $user = new User;

    expect($user->getFillable())
        ->toContain('name', 'email', 'password', 'avatar', 'is_admin');
});

test('user has correct hidden attributes', function () {
    $user = new User;

    expect($user->getHidden())
        ->toContain('password', 'remember_token');
});

test('user has correct casted attributes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_admin' => true,
    ]);

    expect($user->email_verified_at)
        ->toBeInstanceOf(DateTime::class)
        ->and($user->is_admin)->toBeTrue();
});

test('password is hashed via cast', function () {
    $user = new User;
    $user->password = 'secret123';

    expect($user->password)
        ->not()->toBe('secret123')
        ->and(Hash::check('secret123', $user->password))->toBeTrue();
});

test('getItchioUsername returns null when no social account exists', function () {
    expect($this->user->getItchioUsername())->toBeNull();
});

test('getItchioUsername returns username from social account data', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['username' => 'testuser'],
        ]);

    expect($this->user->getItchioUsername())->toBe('testuser');
});

test('getItchioUsername returns null when provider data has no username', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['other_field' => 'value'],
        ]);

    expect($this->user->getItchioUsername())->toBeNull();
});

test('getItchioUrl returns null when no social account exists', function () {
    expect($this->user->getItchioUrl())->toBeNull();
});

test('getItchioUrl returns url from social account data', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testuser.itch.io'],
        ]);

    expect($this->user->getItchioUrl())->toBe('https://testuser.itch.io');
});

test('ownsGame uses API data when available', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://studiofox.itch.io'],
            'itchio_game_ids' => [12345, 67890],
        ]);

    $game = Game::factory()->create([
        'itch_id' => 12345,
        'url' => ['itch_io' => 'https://studiofox.itch.io/my-visual-novel'],
        'slug' => 'my-visual-novel',
        'name' => 'My VN',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($game))->toBeTrue();

    // Different game ID should not match
    $otherGame = Game::factory()->create([
        'itch_id' => 99999,
        'url' => ['itch_io' => 'https://another.itch.io/other'],
        'slug' => 'other',
        'name' => 'Other',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($otherGame))->toBeFalse();
});

test('ownsGame falls back to URL matching when no API data', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://studiofox.itch.io'],
            'itchio_game_ids' => null,
        ]);

    $game = Game::factory()->create([
        'url' => ['itch_io' => 'https://studiofox.itch.io/my-visual-novel'],
        'slug' => 'my-visual-novel',
        'name' => 'My VN',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($game))->toBeTrue();

    // Different domain should not match
    $otherGame = Game::factory()->create([
        'url' => ['itch_io' => 'https://another.itch.io/other'],
        'slug' => 'other',
        'name' => 'Other',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($otherGame))->toBeFalse();
});

test('ownsGame returns false when user has no itchio url', function () {
    $game = Game::factory()->create();

    expect($this->user->ownsGame($game))->toBeFalse();
});

test('ownsGame handles case insensitive domain comparison in fallback mode', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://StudioFox.Itch.Io'],
            'itchio_game_ids' => null,
        ]);

    $game = Game::factory()->create([
        'url' => ['itch_io' => 'https://studiofox.itch.io/my-visual-novel'],
        'slug' => 'my-visual-novel',
        'name' => 'My VN',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($game))->toBeTrue();
});

test('ownsGame handles invalid urls gracefully in fallback mode', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'invalid-url'],
            'itchio_game_ids' => null,
        ]);

    $game = Game::factory()->create([
        'url' => ['itch_io' => 'invalid-game-url'],
        'slug' => 'test',
        'name' => 'Test',
        'is_visible' => true,
    ]);

    expect($this->user->ownsGame($game))->toBeFalse();
});

test('getOwnedGames returns empty collection when no itchio url', function () {
    expect($this->user->getOwnedGames())
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(0);
});

test('getOwnedGames returns games from API data when available', function () {
    $ownedGame = Game::factory()->create([
        'itch_id' => 12345,
        'url' => ['itch_io' => 'https://testuser.itch.io/owned-game'],
        'is_visible' => true,
    ]);

    Game::factory()->create([
        'itch_id' => 99999,
        'url' => ['itch_io' => 'https://other.itch.io/other-game'],
        'is_visible' => true,
    ]);

    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testuser.itch.io'],
            'itchio_game_ids' => [12345],
        ]);

    $ownedGames = $this->user->getOwnedGames();

    expect($ownedGames)
        ->toHaveCount(1)
        ->and($ownedGames->first()->id)->toBe($ownedGame->id);
});

test('getOwnedGames falls back to URL matching when no API data', function () {
    $ownedGame = Game::factory()->create([
        'url' => ['itch_io' => 'https://testuser.itch.io/owned-game'],
        'is_visible' => true,
    ]);

    Game::factory()->create([
        'url' => ['itch_io' => 'https://other.itch.io/other-game'],
        'is_visible' => true,
    ]);

    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testuser.itch.io'],
            'itchio_game_ids' => null,
        ]);

    $ownedGames = $this->user->getOwnedGames();

    expect($ownedGames)
        ->toHaveCount(1)
        ->and($ownedGames->first()->id)->toBe($ownedGame->id);
});

test('getOwnedGames excludes invisible games', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create([
            'provider_name' => 'itchio',
            'provider_data' => ['url' => 'https://testuser.itch.io'],
        ]);

    Game::factory()->create([
        'url' => ['itch_io' => 'https://testuser.itch.io/invisible-game'],
        'is_visible' => false,
    ]);

    $ownedGames = $this->user->getOwnedGames();
    expect($ownedGames)->toHaveCount(0);
});

test('initializeDefaultLists creates default VN lists', function () {
    // Test that a user automatically gets default lists when created (via UserObserver)
    $freshUser = User::factory()->create();

    $lists = $freshUser->vnLists()->get();

    expect($lists)->toHaveCount(5);

    $listNames = $lists->pluck('name')->toArray();
    expect($listNames)->toContain(
        'Currently Reading',
        'Completed',
        'Plan to Read',
        'On Hold',
        'Dropped'
    );

    // All should be marked as default
    expect($lists->every('is_default'))->toBeTrue();
});

test('user has social accounts relationship', function () {
    SocialAccount::factory()
        ->for($this->user)
        ->create(['provider_name' => 'discord']);

    expect($this->user->socialAccounts)->toHaveCount(1)
        ->and($this->user->socialAccounts->first()->provider_name)
        ->toBe('discord');
});

test('user has addition requests relationship', function () {
    $request = AdditionRequest::factory()->create();
    $this->user->additionRequests()->attach($request);

    expect($this->user->additionRequests)->toHaveCount(1)
        ->and($this->user->additionRequests->first()->id)
        ->toBe($request->id);
});

test('user has vn lists relationship', function () {
    $freshUser = User::factory()->create();
    VnList::factory()->for($freshUser)->create(['name' => 'My Custom List']);

    // User gets 5 default lists + 1 custom list = 6 total
    expect($freshUser->vnLists)->toHaveCount(6);

    $customList = $freshUser->vnLists()->where('name', 'My Custom List')->first();
    expect($customList)->not()->toBeNull()
        ->and($customList->name)->toBe('My Custom List');
});
