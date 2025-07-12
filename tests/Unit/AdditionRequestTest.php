<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;

test('normalize url removes protocol and www', function () {
    $url = 'https://www.developer.itch.io/game-name';
    $normalized = AdditionRequest::normalizeUrl($url);

    expect($normalized)->toBe('developer.itch.io/game-name');
});

test('normalize url removes trailing slash', function () {
    $url = 'https://developer.itch.io/game-name/';
    $normalized = AdditionRequest::normalizeUrl($url);

    expect($normalized)->toBe('developer.itch.io/game-name');
});

test('normalize url removes query parameters', function () {
    $url = 'https://developer.itch.io/game-name?ref=something';
    $normalized = AdditionRequest::normalizeUrl($url);

    expect($normalized)->toBe('developer.itch.io/game-name');
});

test('find or create for url creates new request', function () {
    $url = 'https://developer.itch.io/game-name';

    [$request, $isNew] = AdditionRequest::findOrCreateForUrl($url);

    expect($isNew)->toBeTrue();
    expect($request->itch_url)->toBe($url);
    expect($request->normalized_url)->toBe('developer.itch.io/game-name');
    expect($request->status)->toBe(AdditionRequest::STATUS_PENDING);
});

test('find or create for url finds existing request', function () {
    $url1 = 'https://developer.itch.io/game-name';
    $url2 = 'https://www.developer.itch.io/game-name/';

    [$request1, $isNew1] = AdditionRequest::findOrCreateForUrl($url1);
    [$request2, $isNew2] = AdditionRequest::findOrCreateForUrl($url2);

    expect($isNew1)->toBeTrue();
    expect($isNew2)->toBeFalse();
    expect($request1->id)->toBe($request2->id);
});

test('find or create for url returns null for existing visible games', function () {
    $url = 'https://existing-dev.itch.io/existing-game';

    // Create a visible game with this URL
    Game::factory()->create([
        'url' => $url,
        'is_visible' => true,
    ]);

    $result = AdditionRequest::findOrCreateForUrl($url);

    expect($result)->toBeNull();
});

test('find or create for url works for invisible games', function () {
    $url = 'https://hidden-dev.itch.io/hidden-game';

    // Create an invisible game with this URL
    Game::factory()->create([
        'url' => $url,
        'is_visible' => false,
    ]);

    $result = AdditionRequest::findOrCreateForUrl($url);

    expect($result)->not->toBeNull();
    [$request, $isNew] = $result;
    expect($isNew)->toBeTrue();
    expect($request->itch_url)->toBe($url);
});

test('add user to request', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->create();

    $result = $request->addUser($user);

    expect($result)->toBeTrue();
    expect($request->users()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('add user to request prevents duplicates', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->create();

    $result1 = $request->addUser($user);
    $result2 = $request->addUser($user);

    expect($result1)->toBeTrue();
    expect($result2)->toBeFalse();
    expect($request->users()->count())->toBe(1);
});

test('approve request', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);

    $request->approve($admin);

    expect($request->status)->toBe(AdditionRequest::STATUS_APPROVED);
    expect($request->reviewed_by)->toBe($admin->id);
    expect($request->reviewed_at)->not->toBeNull();
    expect($request->rejection_reason)->toBeNull();
});

test('reject request', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $request = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);
    $reason = 'Not a visual novel';

    $request->reject($admin, $reason);

    expect($request->status)->toBe(AdditionRequest::STATUS_REJECTED);
    expect($request->reviewed_by)->toBe($admin->id);
    expect($request->reviewed_at)->not->toBeNull();
    expect($request->rejection_reason)->toBe($reason);
});

test('status check methods', function () {
    $pendingRequest = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);
    $approvedRequest = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_APPROVED]);
    $rejectedRequest = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_REJECTED]);

    expect($pendingRequest->isPending())->toBeTrue();
    expect($pendingRequest->isApproved())->toBeFalse();
    expect($pendingRequest->isRejected())->toBeFalse();

    expect($approvedRequest->isPending())->toBeFalse();
    expect($approvedRequest->isApproved())->toBeTrue();
    expect($approvedRequest->isRejected())->toBeFalse();

    expect($rejectedRequest->isPending())->toBeFalse();
    expect($rejectedRequest->isApproved())->toBeFalse();
    expect($rejectedRequest->isRejected())->toBeTrue();
});

test('remove user from request', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $request = AdditionRequest::factory()->create();

    $request->addUser($user1);
    $request->addUser($user2);

    expect($request->users()->count())->toBe(2);

    $result = $request->removeUser($user1);

    expect($result)->toBeTrue();
    expect($request->users()->count())->toBe(1);
    expect($request->users()->where('user_id', $user1->id)->exists())->toBeFalse();
    expect($request->users()->where('user_id', $user2->id)->exists())->toBeTrue();
});

test('remove user from request returns false if user not associated', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->create();

    $result = $request->removeUser($user);

    expect($result)->toBeFalse();
});

test('remove last user deletes pending request', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);

    $request->addUser($user);
    expect($request->users()->count())->toBe(1);

    $request->removeUser($user);

    // Request should be deleted since it was pending and no users left
    expect(AdditionRequest::find($request->id))->toBeNull();
});

test('remove last user does not delete processed request', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->approved()->create();

    $request->addUser($user);
    expect($request->users()->count())->toBe(1);

    $request->removeUser($user);

    // Request should still exist since it was processed
    expect(AdditionRequest::find($request->id))->not->toBeNull();
    expect($request->fresh()->users()->count())->toBe(0);
});

test('can be cancelled by user checks correctly', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $pendingRequest = AdditionRequest::factory()->create(['status' => AdditionRequest::STATUS_PENDING]);
    $approvedRequest = AdditionRequest::factory()->approved()->create();

    $pendingRequest->addUser($user1);
    $approvedRequest->addUser($user1);

    // User can cancel their pending request
    expect($pendingRequest->canBeCancelledByUser($user1))->toBeTrue();

    // User cannot cancel processed request
    expect($approvedRequest->canBeCancelledByUser($user1))->toBeFalse();

    // User not associated with request cannot cancel
    expect($pendingRequest->canBeCancelledByUser($user2))->toBeFalse();
});
