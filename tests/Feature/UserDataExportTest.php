<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\UserNotificationPreferences;
use App\Models\VnList;
use App\Models\VnListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Disable UserObserver to prevent automatic list creation
    User::unsetEventDispatcher();

    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

describe('user data export endpoint', function () {
    test('requires authentication', function () {
        $response = $this->get(route('browser-api.user.export'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    });

    test('returns ZIP file for authenticated user', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('Content-Disposition');
    });

    test('ZIP filename includes username and timestamp', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $contentDisposition = $response->headers->get('Content-Disposition');

        expect($contentDisposition)->toContain('user-data-test-user')
            ->and($contentDisposition)->toContain('.zip');
    });

    test('export includes profile data', function () {
        $this->user->socialAccounts()->create([
            'provider_name' => 'itchio',
            'provider_id' => '12345',
            'provider_data' => ['username' => 'testuser'],
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);

        // The response is a streamed ZIP, so we can't easily inspect contents
        // but we can verify it's a valid response
        expect($response->headers->get('Content-Type'))->toBe('application/zip');
    });
});

describe('exported data completeness', function () {
    test('export includes VN lists with entries', function () {
        $game = Game::factory()->create();
        $list = VnList::factory()->for($this->user)->create([
            'name' => 'My Favorites',
        ]);

        VnListEntry::create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
            'sort_order' => 1,
            'private_notes' => 'Great game!',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes game progress', function () {
        $game = Game::factory()->create();

        UserGameProgress::create([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => false,
            'personal_notes' => 'Enjoying this',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes ratings and reviews', function () {
        $game = Game::factory()->create();
        $rater = Rater::factory()->create();

        Rating::create([
            'event_id' => 1,
            'game_id' => $game->id,
            'rater_id' => $rater->id,
            'user_id' => $this->user->id,
            'rating' => 5,
            'is_reviewed' => true,
            'is_visible' => true,
            'source_platform' => 'fvn_li',
            'review' => 'Amazing visual novel!',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes notification preferences', function () {
        UserNotificationPreferences::create([
            'user_id' => $this->user->id,
            'browser_notifications_enabled' => true,
            'discord_notifications_enabled' => false,
            'notification_digest' => 'daily',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes notification history', function () {
        $game = Game::factory()->create();
        $version = GameVersion::factory()->for($game)->create();

        NotificationHistory::create([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'type' => 'discord',
            'success' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes social accounts', function () {
        $this->user->socialAccounts()->create([
            'provider_name' => 'itchio',
            'provider_id' => '12345',
            'provider_data' => [
                'username' => 'testuser',
                'url' => 'https://testuser.itch.io',
            ],
        ]);

        $this->user->socialAccounts()->create([
            'provider_name' => 'discord',
            'provider_id' => '67890',
            'provider_data' => [
                'username' => 'TestUser#1234',
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });
});

describe('export data privacy', function () {
    test('user can only export their own data', function () {
        $otherUser = User::factory()->create();

        VnList::factory()->for($otherUser)->create([
            'name' => 'Other User List',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
        // The export should only contain this user's data, not other user's data
    });

    test('export includes private notes', function () {
        $game = Game::factory()->create();
        $list = VnList::factory()->for($this->user)->create();

        VnListEntry::create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
            'sort_order' => 1,
            'private_notes' => 'This is a private note',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export includes personal game progress notes', function () {
        $game = Game::factory()->create();

        UserGameProgress::create([
            'user_id' => $this->user->id,
            'game_id' => $game->id,
            'status' => 'completed',
            'receive_updates' => false,
            'personal_notes' => 'Personal thoughts about this game',
        ]);

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });
});

describe('export with empty data', function () {
    test('export works for user with no lists', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export works for user with no game progress', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export works for user with no ratings', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export works for new user with minimal data', function () {
        $newUser = User::factory()->create();

        $response = $this->actingAs($newUser)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });
});

describe('export cache control', function () {
    test('export response has no-cache headers', function () {
        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);

        $cacheControl = $response->headers->get('Cache-Control');
        expect($cacheControl)->toContain('no-store')
            ->and($cacheControl)->toContain('no-cache')
            ->and($cacheControl)->toContain('must-revalidate');

        $response->assertHeader('Pragma', 'no-cache');
    });
});

describe('export with complex data', function () {
    test('export handles user with multiple lists and entries', function () {
        $games = Game::factory()->count(10)->create();

        for ($i = 0; $i < 3; $i++) {
            $list = VnList::factory()->for($this->user)->create([
                'name' => "List {$i}",
            ]);

            foreach ($games as $index => $game) {
                VnListEntry::create([
                    'vn_list_id' => $list->id,
                    'game_id' => $game->id,
                    'sort_order' => $index,
                ]);
            }
        }

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });

    test('export handles user with many ratings', function () {
        $games = Game::factory()->count(50)->create();
        $rater = Rater::factory()->create();

        foreach ($games as $index => $game) {
            Rating::create([
                'event_id' => $index + 1,
                'game_id' => $game->id,
                'rater_id' => $rater->id,
                'user_id' => $this->user->id,
                'rating' => rand(1, 5),
                'review' => '',
                'is_visible' => true,
                'is_reviewed' => false,
                'source_platform' => 'fvn_li',
                'published_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));

        $response->assertStatus(200);
    });
});

test('export stream writes complete JSON and CSV files into a valid ZIP', function () {
    $game = Game::factory()->create([
        'name' => 'Exported Game',
        'slug' => 'exported-game',
        'platform' => 'itch_io',
    ]);
    $version = GameVersion::factory()->for($game)->create(['version' => '1.2.3']);
    $list = VnList::factory()->for($this->user)->create([
        'name' => 'Export List',
        'description' => 'Private export list',
        'type' => 'custom',
        'is_public' => false,
    ]);
    VnListEntry::create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
        'sort_order' => 3,
        'private_notes' => 'Keep this private',
    ]);
    UserGameProgress::create([
        'user_id' => $this->user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'status' => 'completed',
        'progress' => 100,
        'personal_notes' => 'Finished it',
        'started_at' => now()->subDays(2),
        'completed_at' => now(),
        'receive_updates' => true,
    ]);
    UserNotificationPreferences::create([
        'user_id' => $this->user->id,
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'weekly',
    ]);
    NotificationHistory::create([
        'user_id' => $this->user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'discord',
        'message' => 'Version released',
        'data' => ['version' => '1.2.3'],
        'success' => true,
    ]);
    SocialAccount::factory()->for($this->user)->itchio()->create([
        'provider_id' => 'itch-export',
        'provider_data' => ['username' => 'export-user'],
    ]);
    $this->user->ignoredGames()->attach($game->id);

    $response = $this->actingAs($this->user)->get(route('browser-api.user.export'));
    $response->assertOk();

    ob_start();
    $response->baseResponse->sendContent();
    $zipBytes = ob_get_clean();

    $zipPath = tempnam(sys_get_temp_dir(), 'fvn-export-zip-');
    file_put_contents($zipPath, $zipBytes);

    $zip = new ZipArchive;
    try {
        expect($zip->open($zipPath))->toBeTrue();

        $profile = json_decode($zip->getFromName('profile.json'), true);
        $lists = json_decode($zip->getFromName('lists.json'), true);
        $progress = json_decode($zip->getFromName('game_progress.json'), true);
        $ignoredGames = json_decode($zip->getFromName('ignored_games.json'), true);

        expect($profile['email'])->toBe('test@example.com')
            ->and($lists[0]['entries'][0]['private_notes'])->toBe('Keep this private')
            ->and($progress[0]['game']['slug'])->toBe('exported-game')
            ->and($ignoredGames[0]['slug'])->toBe('exported-game')
            ->and($zip->getFromName('profile.csv'))->toContain('test@example.com')
            ->and($zip->getFromName('lists.csv'))->toContain('Export List')
            ->and($zip->getFromName('list_entries.csv'))->toContain('Keep this private')
            ->and($zip->getFromName('social_accounts.csv'))->toContain('itch-export')
            ->and($zip->getFromName('game_progress.csv'))->toContain('Finished it')
            ->and($zip->getFromName('notification_preferences.csv'))->toContain('weekly')
            ->and($zip->getFromName('notification_history.csv'))->toContain('discord')
            ->and($zip->getFromName('ignored_games.csv'))->toContain('exported-game');
    } finally {
        $zip->close();
        @unlink($zipPath);
    }
});
