<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use App\Models\VnListEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable UserObserver to prevent automatic default list creation
    User::unsetEventDispatcher();
    $this->user = User::factory()->create();
});

describe('default list initialization', function () {
    test('user has default lists after initialization', function () {
        $this->user->initializeDefaultLists();

        $lists = $this->user->vnLists()->where('is_default', true)->get();

        expect($lists)->toHaveCount(5)
            ->and($lists->pluck('name')->toArray())->toContain(
                'Currently Reading',
                'Completed',
                'Plan to Read',
                'On Hold',
                'Dropped'
            );
    });

    test('default lists have correct types', function () {
        $this->user->initializeDefaultLists();

        $lists = $this->user->vnLists()->where('is_default', true)->get();

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
                ->and($list->is_default)->toBeTrue();
        }
    });

    test('default lists are created only once', function () {
        $this->user->initializeDefaultLists();

        // Calling again should throw a unique constraint violation
        // because the database prevents duplicate (user_id, name) combinations
        expect(fn () => $this->user->initializeDefaultLists())
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('list deletion rules', function () {
    test('deleting list removes entries', function () {
        $list = VnList::factory()->for($this->user)->create();
        $game = Game::factory()->create();

        VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        $list->delete();

        expect(VnListEntry::where('vn_list_id', $list->id)->exists())->toBeFalse();
    });
});
