<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\AndroidBuild;
use App\Models\BugReport;
use App\Models\DiscordServer;
use App\Models\DiscordServerMember;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationQueue;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\ReviewReport;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\AccountMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable observers to have full control over test data
    User::unsetEventDispatcher();

    $this->service = new AccountMergeService;
    $this->mergingUser = User::factory()->create(['name' => 'Merging User']);
    $this->otherUser = User::factory()->create(['name' => 'Other User']);
});

describe('Account Merge Service', function () {
    test('merges two accounts successfully', function () {
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

        expect(User::find($this->otherUser->id))->toBeNull();

        expect(SocialAccount::where('user_id', $this->mergingUser->id)->count())->toBe(2);
    });

    test('merges VN list entries without duplicates', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        $game1 = Game::factory()->create();
        $game2 = Game::factory()->create();
        $game3 = Game::factory()->create();

        $mergingReadingList = $this->mergingUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $mergingReadingList->id,
            'game_id' => $game1->id,
        ]);

        $otherReadingList = $this->otherUser->vnLists()->where('name', 'Currently Reading')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $otherReadingList->id,
            'game_id' => $game1->id, // Duplicate
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $otherReadingList->id,
            'game_id' => $game2->id, // New
        ]);

        $otherCompletedList = $this->otherUser->vnLists()->where('name', 'Completed')->first();
        VnListEntry::factory()->create([
            'vn_list_id' => $otherCompletedList->id,
            'game_id' => $game3->id,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        // Refresh merging user
        $this->mergingUser->refresh();

        $readingEntries = $this->mergingUser->vnLists()
            ->where('name', 'Currently Reading')
            ->first()
            ->entries()
            ->pluck('game_id')
            ->toArray();

        expect($readingEntries)->toContain($game1->id)
            ->and($readingEntries)->toContain($game2->id)
            ->and(count($readingEntries))->toBe(2);

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

        expect($this->mergingUser->gameProgress()->count())->toBe(2);

        $preservedProgress = $this->mergingUser->gameProgress()
            ->where('game_id', $mergingProgress->game_id)
            ->first();

        expect($preservedProgress->status)->toBe('completed');

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

        expect($this->mergingUser->socialAccounts()->count())->toBe(3);
    });

    test('handles empty lists', function () {
        $this->mergingUser->initializeDefaultLists();
        $this->otherUser->initializeDefaultLists();

        // Don't add any entries to lists

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        expect(User::find($this->otherUser->id))->toBeNull();
        expect($this->mergingUser->vnLists()->count())->toBeGreaterThan(0);
    });

    test('handles user with multiple custom lists', function () {
        VnList::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
            'is_default' => false,
        ]);

        // Perform merge
        $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

        expect($this->mergingUser->vnLists()->where('is_default', false)->count())->toBe(3);
    });
});

test('merges renamed default lists by type without losing entries', function (string $renamed) {
    $this->mergingUser->initializeDefaultLists();
    $this->otherUser->initializeDefaultLists();
    $target = $this->mergingUser->vnLists()->where('type', 'reading')->firstOrFail();
    $source = $this->otherUser->vnLists()->where('type', 'reading')->firstOrFail();
    ($renamed === 'source' ? $source : $target)->update(['name' => 'My Reading Queue']);
    $entry = $source->entries()->create(['game_id' => Game::factory()->create()->id, 'private_notes' => 'Retained']);
    $this->service->mergeAccounts($this->mergingUser, $this->otherUser);
    expect($entry->fresh()->vn_list_id)->toBe($target->id)
        ->and($entry->fresh()->private_notes)->toBe('Retained')
        ->and(User::find($this->otherUser->id))->toBeNull();
})->with(['source', 'target']);

test('creates a missing default list while preserving the target account reading status', function () {
    $this->mergingUser->initializeDefaultLists();
    $this->otherUser->initializeDefaultLists();
    $this->mergingUser->vnLists()->where('type', 'reading')->delete();
    $source = $this->otherUser->vnLists()->where('type', 'reading')->firstOrFail();
    $game = Game::factory()->create();
    $targetCompleted = $this->mergingUser->vnLists()->where('type', 'completed')->firstOrFail();
    $targetEntry = $targetCompleted->entries()->create(['game_id' => $game->id]);
    $source->entries()->create(['game_id' => $game->id]);
    $uniqueEntry = $source->entries()->create(['game_id' => Game::factory()->create()->id, 'private_notes' => 'Retained']);

    $this->service->mergeAccounts($this->mergingUser, $this->otherUser);

    expect($uniqueEntry->fresh()->list->user_id)->toBe($this->mergingUser->id)
        ->and($uniqueEntry->fresh()->list->type)->toBe('reading')
        ->and($targetEntry->fresh()->vn_list_id)->toBe($targetCompleted->id)
        ->and($this->mergingUser->vnLists()->whereHas('entries', fn ($query) => $query->where('game_id', $game->id))->count())->toBe(1);
});

test('preserves user owned records and resolves duplicate histories without aborting postgres', function () {
    $target = $this->mergingUser;
    $source = $this->otherUser;
    $source->update(['is_review_banned' => true, 'is_admin' => true]);
    $game = Game::factory()->create(['custom_page_updated_by' => $source->id]);
    $version = GameVersion::factory()->for($game)->create();
    $rating = Rating::create([
        'user_id' => $source->id, 'game_id' => $game->id, 'rating' => 4, 'review' => 'Source review',
        'source_platform' => 'fvn_li', 'published_at' => now(),
    ]);
    $rater = Rater::factory()->create(['user_id' => $source->id]);
    $bug = BugReport::create([
        'user_id' => $source->id, 'page_url' => '/games', 'description' => 'Keep report', 'resolved_by' => $source->id,
    ]);
    $comment = $bug->comments()->create(['user_id' => $source->id, 'message' => 'Keep comment']);
    $build = AndroidBuild::factory()->create(['user_id' => $source->id]);
    $push = $source->pushSubscriptions()->create(['endpoint' => 'https://push.example/merge', 'p256dh' => 'test', 'auth' => 'test']);
    $preferences = $source->preferences()->create(['preferred_languages' => ['eng'], 'excluded_tags' => ['test']]);
    $notifications = $source->notificationPreferences()->create(['browser_notifications_enabled' => true, 'notification_digest' => 'weekly']);
    $server = DiscordServer::factory()->create(['owner_user_id' => $source->id]);
    $member = DiscordServerMember::create([
        'discord_server_id' => $server->id, 'user_id' => $source->id, 'discord_user_id' => 'merge-user',
        'discord_username' => 'Source', 'is_admin' => true,
    ]);
    $request = AdditionRequest::factory()->create(['reviewed_by' => $source->id]);
    $source->additionRequests()->attach($request->id);
    $target->additionRequests()->attach($request->id);
    $source->ignoredGames()->attach($game->id);
    $target->ignoredGames()->attach($game->id);
    $uniqueIgnored = Game::factory()->create();
    $source->ignoredGames()->attach($uniqueIgnored->id);
    $history = ['game_id' => $game->id, 'game_version_id' => $version->id, 'type' => 'browser', 'meta_data' => ['kept' => true]];
    $target->notificationHistory()->create($history);
    $source->notificationHistory()->create($history);
    $uniqueHistory = $source->notificationHistory()->create(array_merge($history, ['type' => 'discord']));
    $queue = NotificationQueue::create([
        'user_id' => $source->id, 'game_id' => $game->id, 'game_version_id' => $version->id,
        'channel' => 'browser', 'status' => 'processing', 'batch_key' => 'old-lease', 'scheduled_at' => now(),
    ]);
    $report = ReviewReport::create([
        'rating_id' => $rating->id, 'reporter_id' => $source->id, 'reason' => 'spam', 'reviewed_by' => $source->id,
    ]);
    $notice = $source->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['body' => 'Keep notice']]);
    $token = $source->createToken('old-account')->accessToken;

    $this->service->mergeAccounts($target, $source);

    foreach ([$rating, $rater, $bug, $comment, $build, $push, $preferences, $notifications, $member, $uniqueHistory, $queue] as $record) {
        expect($record->fresh()->user_id)->toBe($target->id);
    }
    expect($server->fresh()->owner_user_id)->toBe($target->id)
        ->and($game->fresh()->custom_page_updated_by)->toBe($target->id)
        ->and($bug->fresh()->resolved_by)->toBe($target->id)
        ->and($request->fresh()->reviewed_by)->toBe($target->id)
        ->and($report->fresh()->reporter_id)->toBe($target->id)
        ->and($report->fresh()->reviewed_by)->toBe($target->id)
        ->and($notice->fresh()->notifiable_id)->toBe($target->id)
        ->and($queue->fresh()->status)->toBe('pending')
        ->and($queue->fresh()->batch_key)->toBeNull()
        ->and($target->notificationHistory()->count())->toBe(2)
        ->and($target->additionRequests()->count())->toBe(1)
        ->and($target->ignoredGames()->count())->toBe(2)
        ->and($target->fresh()->is_review_banned)->toBeTrue()
        ->and($target->fresh()->is_admin)->toBeFalse()
        ->and($token->fresh())->toBeNull()
        ->and($source->fresh())->toBeNull();
});

test('keeps target preferences and current review while retaining duplicate review text and both custom lists', function () {
    $target = $this->mergingUser;
    $source = $this->otherUser;
    $target->preferences()->create(['preferred_languages' => ['eng'], 'excluded_tags' => null]);
    $source->preferences()->create(['preferred_languages' => ['fra'], 'excluded_tags' => ['blocked']]);
    $target->notificationPreferences()->create(['browser_notifications_enabled' => false]);
    $source->notificationPreferences()->create(['browser_notifications_enabled' => true]);
    $targetList = VnList::factory()->for($target)->create(['name' => 'Favorites', 'is_default' => false]);
    $sourceList = VnList::factory()->for($source)->create(['name' => 'Favorites', 'is_default' => false]);
    $game = Game::factory()->create();
    $attributes = ['game_id' => $game->id, 'rating' => 4, 'source_platform' => 'fvn_li', 'published_at' => now()];
    $targetReview = $target->ratings()->create($attributes + ['review' => 'Current review']);
    $sourceReview = $source->ratings()->create($attributes + ['review' => 'Previous account review']);
    $this->service->mergeAccounts($target, $source);
    expect($target->fresh()->preferences->preferred_languages)->toBe(['eng'])
        ->and($target->fresh()->preferences->excluded_tags)->toBe(['blocked'])
        ->and($target->fresh()->notificationPreferences->browser_notifications_enabled)->toBeFalse()
        ->and($targetReview->fresh()->review)->toBe('Current review')
        ->and($targetReview->fresh()->external_metadata['merged_reviews'][0]['review'])->toBe('Previous account review')
        ->and($sourceReview->fresh()->is_visible)->toBeFalse()
        ->and($sourceList->fresh()->name)->not->toBe($targetList->name)
        ->and($sourceList->fresh()->user_id)->toBe($target->id);
});

test('rejects self merge without deleting the account', function () {
    expect(fn () => $this->service->mergeAccounts($this->mergingUser, $this->mergingUser))
        ->toThrow(InvalidArgumentException::class);
    expect($this->mergingUser->fresh())->not->toBeNull();
});
