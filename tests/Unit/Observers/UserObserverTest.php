<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('UserObserver default list initialization', function () {
    test('creates default lists when user is created', function () {
        $user = User::factory()->create();

        $defaultLists = $user->vnLists()->where('is_default', true)->get();

        expect($defaultLists)->toHaveCount(5)
            ->and($defaultLists->pluck('name')->toArray())->toContain(
                'Currently Reading',
                'Completed',
                'Plan to Read',
                'On Hold',
                'Dropped'
            );
    });

    test('default lists have correct types', function () {
        $user = User::factory()->create();

        $lists = $user->vnLists()->where('is_default', true)->get();

        $expectedTypes = [
            'Currently Reading' => 'reading',
            'Completed' => 'completed',
            'Plan to Read' => 'plan_to_read',
            'On Hold' => 'on_hold',
            'Dropped' => 'dropped',
        ];

        foreach ($expectedTypes as $name => $type) {
            $list = $lists->firstWhere('name', $name);
            expect($list)->not->toBeNull()
                ->and($list->type)->toBe($type)
                ->and($list->is_default)->toBeTrue()
                ->and($list->user_id)->toBe($user->id);
        }
    });

    test('each new user gets their own default lists', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Lists = $user1->vnLists()->where('is_default', true)->get();
        $user2Lists = $user2->vnLists()->where('is_default', true)->get();

        expect($user1Lists)->toHaveCount(5)
            ->and($user2Lists)->toHaveCount(5)
            ->and($user1Lists->pluck('id')->toArray())
            ->not->toEqual($user2Lists->pluck('id')->toArray());
    });

    test('observer does not interfere with user updates', function () {
        $user = User::factory()->create();

        $initialListCount = $user->vnLists()->count();

        $user->update(['name' => 'Updated Name']);

        expect($user->vnLists()->count())->toBe($initialListCount);
    });
});
