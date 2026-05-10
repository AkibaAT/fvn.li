<?php

declare(strict_types=1);

use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\PushSubscription;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\UserNotificationPreferences;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Support\SystemAuditUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable UserObserver to prevent automatic list creation
    User::unsetEventDispatcher();
});

describe('user deletion', function () {
    test('deletes user from database', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        expect(User::find($userId))->toBeNull();
    });

    test('cascades deletion to VN lists', function () {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $list = VnList::factory()->for($user)->create(['name' => 'Test List']);
        $listId = $list->id;

        VnListEntry::create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
            'sort_order' => 1,
        ]);

        $user->delete();

        expect(VnList::find($listId))->toBeNull()
            ->and(VnListEntry::where('vn_list_id', $listId)->exists())->toBeFalse();
    });

    test('cascades deletion to game progress', function () {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $progress = UserGameProgress::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => false,
        ]);

        $user->delete();

        expect(UserGameProgress::find($progress->id))->toBeNull();
    });

    test('cascades deletion to social accounts', function () {
        $user = User::factory()->create();

        $socialAccount = SocialAccount::create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'provider_id' => '12345',
        ]);

        $user->delete();

        expect(SocialAccount::find($socialAccount->id))->toBeNull();
    });

    test('cascades deletion to notification preferences', function () {
        $user = User::factory()->create();

        $prefs = UserNotificationPreferences::create([
            'user_id' => $user->id,
            'discord_enabled' => true,
            'telegram_enabled' => false,
            'email_enabled' => true,
            'browser_enabled' => false,
        ]);

        $user->delete();

        expect(UserNotificationPreferences::find($prefs->id))->toBeNull();
    });

    test('cascades deletion to notification history', function () {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $version = GameVersion::factory()->for($game)->create();

        $notification = NotificationHistory::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'type' => 'discord',
            'success' => true,
        ]);

        $user->delete();

        expect(NotificationHistory::find($notification->id))->toBeNull();
    });

    test('cascades deletion to push subscriptions', function () {
        $user = User::factory()->create();

        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/push',
            'p256dh' => 'test-key',
            'auth' => 'test-auth',
        ]);

        $user->delete();

        expect(PushSubscription::find($subscription->id))->toBeNull();
    });

    test('deletes user with multiple related records', function () {
        $user = User::factory()->create();
        $userId = $user->id;
        $game = Game::factory()->create();
        $version = GameVersion::factory()->for($game)->create();

        // Create multiple types of related data
        VnList::factory()->for($user)->create(['name' => 'List 1']);
        VnList::factory()->for($user)->create(['name' => 'List 2']);

        UserGameProgress::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => false,
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_name' => 'itchio',
            'provider_id' => '12345',
        ]);

        NotificationHistory::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'type' => 'email',
            'success' => true,
        ]);

        $user->delete();

        expect(User::find($userId))->toBeNull()
            ->and(VnList::where('user_id', $userId)->exists())->toBeFalse()
            ->and(UserGameProgress::where('user_id', $userId)->exists())->toBeFalse()
            ->and(SocialAccount::where('user_id', $userId)->exists())->toBeFalse()
            ->and(NotificationHistory::where('user_id', $userId)->exists())->toBeFalse();
    });
});

describe('GDPR-compliant deletion workflow', function () {
    test('anonymizes audit logs before deletion', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        // Create an audit log
        ChangeLog::create([
            'user_id' => $user->id,
            'event_type' => 'created',
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);

        // Simulate the deletion workflow from DashboardController
        DB::transaction(function () use ($user) {
            ChangeLog::anonymizeUserData($user->id);
            $user->delete();
        });

        expect(User::find($userId))->toBeNull()
            ->and(ChangeLog::where('user_id', $userId)->exists())->toBeFalse()
            ->and(ChangeLog::where('user_id', SystemAuditUser::id())->exists())->toBeTrue();
    });

    test('anonymizes click statistics before deletion', function () {
        $user = User::factory()->create();
        $userId = $user->id;
        $game = Game::factory()->create();

        // Create click statistics
        ClickStat::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'type' => 'download',
            'ip_address' => '192.168.1.100',
            'session_id' => 'test-session',
            'clicked_at' => now(),
        ]);

        // Simulate the deletion workflow from DashboardController
        DB::transaction(function () use ($user) {
            ClickStat::anonymizePersonalDataForUser($user->id);
            $user->delete();
        });

        expect(User::find($userId))->toBeNull()
            ->and(ClickStat::where('user_id', $userId)->exists())->toBeFalse()
            ->and(ClickStat::whereNull('user_id')->where('game_id', $game->id)->exists())->toBeTrue();
    });

    test('handles foreign key constraints properly', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        // Create an addition request reviewed by this user
        DB::table('addition_requests')->insert([
            'game_id' => Game::factory()->create()->id,
            'game_url' => 'https://example.itch.io/game',
            'normalized_url' => 'example.itch.io/game',
            'reviewed_by' => $user->id,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a game with custom page updated by this user
        DB::table('games')->where('id', Game::factory()->create()->id)->update([
            'custom_page_updated_by' => $user->id,
            'has_custom_page' => true,
        ]);

        // Simulate the deletion workflow from DashboardController
        DB::transaction(function () use ($user) {
            // Reassign addition request reviews to system user
            DB::table('addition_requests')
                ->where('reviewed_by', $user->id)
                ->update(['reviewed_by' => SystemAuditUser::id()]);

            // Reset custom game pages
            DB::table('games')
                ->where('custom_page_updated_by', $user->id)
                ->update(['custom_page_updated_by' => null, 'has_custom_page' => false]);

            $user->delete();
        });

        expect(User::find($userId))->toBeNull()
            ->and(DB::table('addition_requests')->where('reviewed_by', $userId)->exists())->toBeFalse()
            ->and(DB::table('addition_requests')->where('reviewed_by', SystemAuditUser::id())->exists())->toBeTrue()
            ->and(DB::table('games')->where('custom_page_updated_by', $userId)->exists())->toBeFalse();
    });
});
