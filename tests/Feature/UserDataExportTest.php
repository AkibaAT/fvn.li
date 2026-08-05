<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
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

});

describe('export with empty data', function () {
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
