<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;
use App\Services\AdditionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('validate url accepts any supported http url', function () {
    $service = new AdditionRequestService;

    expect($service->validateUrl('https://developer.itch.io/game-name')['valid'])->toBeTrue();
    expect($service->validateUrl('https://store.steampowered.com/app/123456/game')['valid'])->toBeTrue();
    expect($service->validateUrl('https://example.com/my-game')['valid'])->toBeTrue();

    expect($service->validateUrl('ftp://example.com/game')['valid'])->toBeFalse();
    expect($service->validateUrl('not-a-url')['valid'])->toBeFalse();
    expect($service->validateUrl('https://')['valid'])->toBeFalse();
});

test('parse urls splits input correctly', function () {
    $service = new AdditionRequestService;
    $input = "https://dev1.itch.io/game1\nhttps://dev2.itch.io/game2\n\nhttps://dev3.itch.io/game3";

    $urls = $service->parseUrls($input);

    expect($urls)->toHaveCount(3);
    expect($urls[0])->toBe('https://dev1.itch.io/game1');
    expect($urls[1])->toBe('https://dev2.itch.io/game2');
    expect($urls[2])->toBe('https://dev3.itch.io/game3');
});

test('submit requests handles valid urls', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;
    $urls = [
        'https://dev1.itch.io/game1',
        'https://dev2.itch.io/game2',
    ];

    $results = $service->submitRequests($user, $urls);

    expect($results['success_count'])->toBe(2);
    expect($results['duplicate_count'])->toBe(0);
    expect($results['invalid_count'])->toBe(0);
    expect($results['errors'])->toBeEmpty();
    expect($results['requests'])->toHaveCount(2);
});

test('submit requests handles invalid urls', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;
    $urls = [
        'https://dev1.itch.io/game1',
        'https://example.com/new-game',
        'ftp://unsupported.dev/game',
        'not-a-url',
    ];

    $results = $service->submitRequests($user, $urls);

    expect($results['success_count'])->toBe(2);
    expect($results['duplicate_count'])->toBe(0);
    expect($results['invalid_count'])->toBe(2);
    expect($results['errors'])->toHaveCount(2);
    expect($results['requests'])->toHaveCount(2);
});

test('submit requests handles duplicates', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;
    $url = 'https://dev1.itch.io/game1';

    // Submit the same URL twice
    $results1 = $service->submitRequests($user, [$url]);
    $results2 = $service->submitRequests($user, [$url]);

    expect($results1['success_count'])->toBe(1);
    expect($results1['duplicate_count'])->toBe(0);

    expect($results2['success_count'])->toBe(0);
    expect($results2['duplicate_count'])->toBe(1);
});

test('submit requests filters out existing visible games', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;

    $existingUrl = 'https://existing-dev.itch.io/existing-game';
    Game::factory()->create([
        'url' => ['itch_io' => $existingUrl],
        'is_visible' => true,
    ]);

    $urls = [
        'https://new-dev.itch.io/new-game',  // This should work
        $existingUrl,  // This should be filtered out
    ];

    $results = $service->submitRequests($user, $urls);

    expect($results['success_count'])->toBe(1);
    expect($results['already_exists_count'])->toBe(1);
    expect($results['errors'])->toHaveCount(1);
    expect($results['errors'][0])->toContain('Game already exists on the site');
});

test('game already exists checks various URL formats', function () {
    Game::factory()->create([
        'url' => ['itch_io' => 'https://developer.itch.io/game-name'],
        'is_visible' => true,
    ]);

    // Test various URL formats that should match
    expect(AdditionRequest::gameAlreadyExists('https://developer.itch.io/game-name'))->toBeTrue();
    expect(AdditionRequest::gameAlreadyExists('https://www.developer.itch.io/game-name'))->toBeTrue();
    expect(AdditionRequest::gameAlreadyExists('http://developer.itch.io/game-name'))->toBeTrue();
    expect(AdditionRequest::gameAlreadyExists('https://developer.itch.io/game-name/'))->toBeTrue();

    // Test URL that should not match
    expect(AdditionRequest::gameAlreadyExists('https://other-dev.itch.io/other-game'))->toBeFalse();
});

test('game already exists only checks visible games', function () {
    Game::factory()->create([
        'url' => ['itch_io' => 'https://developer.itch.io/hidden-game'],
        'is_visible' => false,
    ]);

    // Should return false because the game is not visible
    expect(AdditionRequest::gameAlreadyExists('https://developer.itch.io/hidden-game'))->toBeFalse();
});

test('get user requests filters by status', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;

    $pendingRequest = AdditionRequest::factory()->create();
    $approvedRequest = AdditionRequest::factory()->approved()->create();
    $rejectedRequest = AdditionRequest::factory()->rejected()->create();

    $pendingRequest->addUser($user);
    $approvedRequest->addUser($user);
    $rejectedRequest->addUser($user);

    $allRequests = $service->getUserRequests($user);
    $pendingRequests = $service->getUserRequests($user, AdditionRequest::STATUS_PENDING);
    $approvedRequests = $service->getUserRequests($user, AdditionRequest::STATUS_APPROVED);

    expect($allRequests)->toHaveCount(3);
    expect($pendingRequests)->toHaveCount(1);
    expect($approvedRequests)->toHaveCount(1);
});

test('get statistics returns correct counts', function () {
    $service = new AdditionRequestService;

    AdditionRequest::factory()->count(2)->create();
    AdditionRequest::factory()->approved()->create();
    AdditionRequest::factory()->rejected()->create();

    $stats = $service->getStatistics();

    expect($stats['total'])->toBe(4);
    expect($stats['pending'])->toBe(2);
    expect($stats['approved'])->toBe(1);
    expect($stats['rejected'])->toBe(1);
});

test('cancel user request succeeds for pending request', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;
    $request = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);

    $request->addUser($user);

    $result = $service->cancelUserRequest($user, $request);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('cancelled successfully');
    expect($request->users()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('cancel user request fails for processed request', function () {
    $user = User::factory()->create();
    $service = new AdditionRequestService;
    $request = AdditionRequest::factory()->approved()->create();

    $request->addUser($user);

    $result = $service->cancelUserRequest($user, $request);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('cannot be cancelled');
    expect($request->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('cancel user request fails for non-associated user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $service = new AdditionRequestService;
    $request = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);

    $request->addUser($user1);

    $result = $service->cancelUserRequest($user2, $request);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('cannot be cancelled');
    expect($request->users()->where('user_id', $user1->id)->exists())->toBeTrue();
});
