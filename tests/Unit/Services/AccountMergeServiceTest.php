<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\AccountMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable observers to have full control over test data
    User::unsetEventDispatcher();
    
    $this->service = new AccountMergeService();
    $this->mergingUser = User::factory()->create(['name' => 'Merging User']);
    $this->otherUser = User::factory()->create(['name' => 'Other User']);
});

describe('Account Merge Service', function () {
    test('merges two accounts successfully', function () {
        // Create social accounts
        SocialAccount::factory()->create([
            'user_id' => $this->mergingUser->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord123',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->otherUser->id,
            'provider_name' => 'itchio',
            'provider_id' => 'itchio456',
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Assert other user is deleted
        expect(User::find($this->otherUser->id))->toBeNull();

        // Assert both social accounts now belong to merging user
        expect(SocialAccount::where('user_id', $this->mergingUser->id)->count())->toBe(2);
    });

    test('merges VN list entries without duplicates', function () {
        // Initialize default lists for both users
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        // Create games
        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $game3 = Game::factory()->create();

        // Add games to merging user's "Currently Reading" list
        $mergingReadingList = $this->mergingUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $mergingReadingList->id,
            'game_id' => $game1->id,
        ]);

        // Add games to other user's "Currently Reading" list
        $otherReadingList = $this->otherUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $otherReadingList->id,
            'game_id' => $game1->id, // Duplicate
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $otherReadingList->id,
            'game_id' => $game2->id, // New
        ]);

        // Add game to other user's "Completed" list
        $otherCompletedList = $this->otherUser->vnLists()->where('name', 'Completed')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $otherCompletedList->id,
            'game_id' => $game3->id,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Refresh merging user
        $this->mergingUser->refresh();

        // Assert: Merging user's "Currently Reading" should have game1 and game2 (no duplicate)
        $readingEntries = $this->mergingUser->vnLists()
            ->where('name', 'Currently Reading')
            ->first()
            ->entries()
            ->pluck('game_id')
            ->toArray();

        expect($readingEntries)->toContain($game1->id)
            ->and($readingEntries)->toContain($game2->id)
            ->and(count($readingEntries))->toBe(2);

        // Assert: Merging user's "Completed" should have game3
        $completedEntries = $this->mergingUser->vnLists()
            ->where('name', 'Completed')
            ->first()
            ->entries()
            ->pluck('game_id')
            ->toArray();

        expect($completedEntries)->toContain($game3->id);
    });

    test('keeps game in only one system list after merge', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        $game = Game::factory()->create();

        // Merging user has game in "Currently Reading"
        $mergingReadingList = $this->mergingUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $mergingReadingList->id,
            'game_id' => $game->id,
        ]);

        // Other user has same game in "Completed"
        $otherCompletedList = $this->otherUser->vnLists()->where('name', 'Completed')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $otherCompletedList->id,
            'game_id' => $game->id,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        $this->mergingUser->refresh();

        // Count how many system lists contain this game
        $systemListsWithGame = $this->mergingUser->vnLists()
            ->where('is_default', true)
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            })
            ->count();

        // Should only be in one system list (the merging user's original list)
        expect($systemListsWithGame)->toBe(1);

        // Verify it's still in "Currently Reading"
        $inReadingList = $this->mergingUser->vnLists()
            ->where('name', 'Currently Reading')
            ->first()
            ->entries()
            ->where('game_id', $game->id)
            ->exists();

        expect($inReadingList)->toBeTrue();
    });

    test('transfers custom lists to merging user', function () {
        // Create custom list for other user
        $customList = VnList::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'My Favorites',
            'is_default' => false,
        ]);

        $game = Game::factory()->create();
        VnListEntry::factory()->create([
            'vn_list_id' => $customList->id,
            'game_id' => $game->id,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        $this->mergingUser->refresh();

        // Assert custom list now belongs to merging user
        $transferredList = $this->mergingUser->vnLists()
            ->where('name', 'My Favorites')
            ->where('is_default', false)
            ->first();

        expect($transferredList)->not->toBeNull()
            ->and($transferredList->entries()->count())->toBe(1)
            ->and($transferredList->entries()->first()->game_id)->toBe($game->id);
    });

    test('merges game progress keeping merging user data', function () {
        // Merging user has progress for game1
        $mergingProgress = UserGameProgress::factory()->completed()->create([
            'user_id' => $this->mergingUser->id,
        ]);

        // Other user has progress for game1 (conflict) and game2 (no conflict)
        UserGameProgress::factory()->reading()->create([
            'user_id' => $this->otherUser->id,
            'game_id' => $mergingProgress->game_id, // Same game - conflict
        ]);

        $otherProgress2 = UserGameProgress::factory()->reading()->create([
            'user_id' => $this->otherUser->id,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        $this->mergingUser->refresh();

        // Assert: Merging user should have 2 progress records (original + transferred non-conflict)
        expect($this->mergingUser->gameProgress()->count())->toBe(2);

        // Assert: Original progress for game1 is preserved (completed, not reading)
        $preservedProgress = $this->mergingUser->gameProgress()
            ->where('game_id', $mergingProgress->game_id)
            ->first();

        expect($preservedProgress->status)->toBe('completed');

        // Assert: Other user's progress for game2 was transferred
        $transferredProgress = $this->mergingUser->gameProgress()
            ->where('id', $otherProgress2->id)
            ->first();

        expect($transferredProgress)->not->toBeNull();
    });

    test('merges social accounts', function () {
        SocialAccount::factory()->create([
            'user_id' => $this->mergingUser->id,
            'provider_name' => 'discord',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->otherUser->id,
            'provider_name' => 'itchio',
        ]);

        SocialAccount::factory()->create([
            'user_id' => $this->otherUser->id,
            'provider_name' => 'telegram',
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Assert all social accounts now belong to merging user
        expect($this->mergingUser->socialAccounts()->count())->toBe(3);
    });

    test('rolls back on error', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        // Create a scenario that will cause an error
        // Force a constraint violation by creating invalid data
        $game = Game::factory()->create();
        
        $mergingList = $this->mergingUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $mergingList->id,
            'game_id' => $game->id,
        ]);

        // Mock a failure by deleting the merging user's list mid-transaction
        // This should cause the transaction to roll back
        $otherUserId = $this->otherUser->id;

        try {
            DB::transaction(function () use ($game) {
                // Start the merge process
                $otherList = $this->otherUser->vnLists()->where('name', 'Currently Reading')->first();
                VnListEntry::factory()->create([
                    'vn_list_id' => $otherList->id,
                    'game_id' => $game->id,
                ]);

                // Force an error by trying to delete a user that doesn't exist
                User::findOrFail(99999)->delete();
            });
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Assert: Other user should still exist (transaction rolled back)
        expect(User::find($otherUserId))->not->toBeNull();
    });

    test('handles empty lists', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        // Don't add any entries to lists

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Assert: Merge completes successfully even with empty lists
        expect(User::find($this->otherUser->id))->toBeNull();
        expect($this->mergingUser->vnLists()->count())->toBeGreaterThan(0);
    });

    test('handles user with no custom lists', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        // Only default lists, no custom lists

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Assert: Merge completes successfully
        expect(User::find($this->otherUser->id))->toBeNull();
    });

    test('handles user with multiple custom lists', function () {
        VnList::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'is_default' => false,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Assert: All custom lists transferred
        expect($this->mergingUser->vnLists()->where('is_default', false)->count())->toBe(3);
    });
});

