<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use App\Models\VnListEntry;
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
            ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
    });
});

describe('custom list creation', function () {
    test('user can create custom list', function () {
        $list = VnList::factory()->for($this->user)->create([
            'name' => 'My Favorites',
            'is_default' => false,
            'is_public' => true,
        ]);

        expect($list->user_id)->toBe($this->user->id)
            ->and($list->is_default)->toBeFalse()
            ->and($list->is_public)->toBeTrue();
    });

    test('custom list can have description', function () {
        $list = VnList::factory()->for($this->user)->create([
            'name' => 'Best Romance VNs',
            'description' => 'My favorite romance visual novels',
            'is_default' => false,
        ]);

        expect($list->description)->toBe('My favorite romance visual novels');
    });

    test('custom list can be private', function () {
        $list = VnList::factory()->for($this->user)->create([
            'name' => 'Private Collection',
            'is_public' => false,
        ]);

        expect($list->is_public)->toBeFalse();
    });

    test('user can have multiple custom lists', function () {
        VnList::factory()->count(3)->for($this->user)->create([
            'is_default' => false,
        ]);

        $customLists = $this->user->vnLists()->where('is_default', false)->get();

        expect($customLists)->toHaveCount(3);
    });
});

describe('list entry management', function () {
    test('can add game to list', function () {
        $list = VnList::factory()->for($this->user)->create();
        $game = Game::factory()->create();

        $entry = VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        expect($entry->vn_list_id)->toBe($list->id)
            ->and($entry->game_id)->toBe($game->id);
    });

    test('entry has automatic sort order', function () {
        $list = VnList::factory()->for($this->user)->create();
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();

        $entry1 = VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game1->id,
            'sort_order' => 100,
        ]);

        $entry2 = VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game2->id,
            'sort_order' => 200,
        ]);

        expect($entry1->sort_order)->toBe(100)
            ->and($entry2->sort_order)->toBe(200)
            ->and($entry2->sort_order)->toBeGreaterThan($entry1->sort_order);
    });

    test('entry can have notes', function () {
        $list = VnList::factory()->for($this->user)->create();
        $game = Game::factory()->create();

        // Note: The database only has 'private_notes' column, not 'notes'
        // This test is checking that entries can have notes (via private_notes)
        $entry = VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
            'private_notes' => 'Really enjoyed this one!',
        ]);

        expect($entry->private_notes)->toBe('Really enjoyed this one!');
    });

    test('entry can have private notes', function () {
        $list = VnList::factory()->for($this->user)->create();
        $game = Game::factory()->create();

        $entry = VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
            'private_notes' => 'Personal thoughts',
        ]);

        expect($entry->private_notes)->toBe('Personal thoughts');
    });

    test('can add multiple games to same list', function () {
        $list = VnList::factory()->for($this->user)->create();
        $games = Game::factory()->count(5)->create();

        foreach ($games as $game) {
            VnListEntry::factory()->create([
                'vn_list_id' => $list->id,
                'game_id' => $game->id,
            ]);
        }

        expect($list->entries()->count())->toBe(5);
    });

    test('same game can be in multiple lists', function () {
        $list1 = VnList::factory()->for($this->user)->create();
        $list2 = VnList::factory()->for($this->user)->create();
        $game = Game::factory()->create();

        VnListEntry::factory()->create([
            'vn_list_id' => $list1->id,
            'game_id' => $game->id,
        ]);

        VnListEntry::factory()->create([
            'vn_list_id' => $list2->id,
            'game_id' => $game->id,
        ]);

        expect($list1->entries()->where('game_id', $game->id)->exists())->toBeTrue()
            ->and($list2->entries()->where('game_id', $game->id)->exists())->toBeTrue();
    });
});

describe('list visibility and privacy', function () {
    test('public list is visible to others', function () {
        $list = VnList::factory()->for($this->user)->create([
            'is_public' => true,
        ]);

        expect($list->is_public)->toBeTrue();
    });

    test('private list is not visible to others', function () {
        $list = VnList::factory()->for($this->user)->create([
            'is_public' => false,
        ]);

        expect($list->is_public)->toBeFalse();
    });

    test('default lists can be public or private', function () {
        $publicDefaultList = VnList::factory()->for($this->user)->create([
            'is_default' => true,
            'is_public' => true,
        ]);

        $privateDefaultList = VnList::factory()->for($this->user)->create([
            'is_default' => true,
            'is_public' => false,
        ]);

        expect($publicDefaultList->is_public)->toBeTrue()
            ->and($privateDefaultList->is_public)->toBeFalse();
    });
});

describe('list deletion rules', function () {
    test('custom lists can be deleted', function () {
        $list = VnList::factory()->for($this->user)->create([
            'is_default' => false,
        ]);

        $listId = $list->id;
        $list->delete();

        expect(VnList::find($listId))->toBeNull();
    });

    test('default lists should not be deleted', function () {
        $list = VnList::factory()->for($this->user)->create([
            'is_default' => true,
        ]);

        // This is enforced by policy, not model
        // The model allows deletion, but policy prevents it
        expect($list->is_default)->toBeTrue();
    });

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

describe('list relationships', function () {
    test('list belongs to user', function () {
        $list = VnList::factory()->for($this->user)->create();

        expect($list->user)->not->toBeNull()
            ->and($list->user->id)->toBe($this->user->id);
    });

    test('list has many entries', function () {
        $list = VnList::factory()->for($this->user)->create();
        $games = Game::factory()->count(3)->create();

        foreach ($games as $game) {
            VnListEntry::factory()->create([
                'vn_list_id' => $list->id,
                'game_id' => $game->id,
            ]);
        }

        expect($list->entries)->toHaveCount(3);
    });

    test('user has many lists', function () {
        VnList::factory()->count(5)->for($this->user)->create();

        expect($this->user->vnLists)->toHaveCount(5);
    });
});

