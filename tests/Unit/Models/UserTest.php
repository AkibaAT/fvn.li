<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('user has correct hidden attributes', function () {
    $user = new User;

    expect($user->getHidden())
        ->toContain('password', 'remember_token');
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
