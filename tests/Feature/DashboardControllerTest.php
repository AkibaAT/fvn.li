<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Support\SystemAuditUser;
use Illuminate\Support\Facades\DB;

it('renders the dashboard with connected account, owned games, ignored games, and notification state', function () {
    config(['services.discord.client_id' => 'discord-client']);

    $user = User::factory()->create(['name' => 'Dashboard User']);
    $ownedGame = Game::factory()->create([
        'itch_id' => 12345,
        'name' => 'Owned Game',
        'is_visible' => true,
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://owner.itch.io/owned-game'],
        'additional_links' => [['label' => 'Guide', 'url' => 'https://example.com/guide']],
    ]);
    $ignoredGame = Game::factory()->create(['name' => 'Ignored Game']);
    $ownedVersion = GameVersion::factory()->create(['game_id' => $ownedGame->id]);

    SocialAccount::factory()->for($user)->itchio()->create([
        'provider_data' => [
            'username' => 'owner',
            'url' => 'https://owner.itch.io',
        ],
        'itchio_game_ids' => [12345],
    ]);
    SocialAccount::factory()->for($user)->discord()->create();

    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'daily',
    ]);
    $user->ignoredGames()->attach($ignoredGame->id);

    NotificationQueue::create([
        'user_id' => $user->id,
        'game_id' => $ownedGame->id,
        'game_version_id' => $ownedVersion->id,
        'channel' => 'discord',
        'status' => 'failed',
        'error' => 'Missing permissions',
        'scheduled_at' => now(),
        'processed_at' => now(),
        'payload' => [],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $page = $response->viewData('page');
    $props = $page['props'];

    expect($page['component'])->toBe('dashboard/index')
        ->and($props['connectedProviders'])->toContain('itchio', 'discord')
        ->and($props['itchioData']['username'])->toBe('owner')
        ->and($props['myGames'][0]['id'])->toBe($ownedGame->id)
        ->and($props['myGames'][0]['has_additional_links'])->toBeTrue()
        ->and($props['notificationPreferences']['notification_digest'])->toBe('daily')
        ->and($props['discordInfo']['hasAccount'])->toBeTrue()
        ->and($props['discordInfo']['botInstallUrl'])->toContain('discord-client')
        ->and($props['discordInfo']['lastNotification']['status'])->toBe('failed')
        ->and($props['ignoredGames'][0]['id'])->toBe($ignoredGame->id)
        ->and($props['ignoredGamesCount'])->toBe(1);
});

it('formats all linked social account provider metadata for the dashboard', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'discord',
        'provider_id' => 'discord-1',
        'provider_data' => [
            'id' => 'discord-id',
            'global_name' => 'Discord Display',
            'avatar' => 'avatar-hash',
        ],
    ]);
    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'google',
        'provider_id' => 'google-1',
        'provider_data' => [
            'given_name' => 'Google Given',
            'picture' => 'https://google.example/avatar.png',
        ],
    ]);
    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'steam',
        'provider_id' => 'steam-1',
        'provider_data' => [
            'personaname' => 'Steam Persona',
            'avatarfull' => 'https://steam.example/avatar.png',
        ],
    ]);
    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'telegram',
        'provider_id' => 'telegram-1',
        'provider_data' => [
            'first_name' => 'Telegram',
            'last_name' => 'User',
            'photo_url' => 'https://telegram.example/avatar.png',
        ],
    ]);
    SocialAccount::factory()->for($user)->create([
        'provider_name' => 'itchio',
        'provider_id' => 'itchio-1',
        'provider_data' => [
            'username' => 'itch-user',
            'display_name' => 'Itch Display',
            'cover_url' => 'https://itch.example/cover.png',
        ],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['socialAccounts']['discord']['display_name'])->toBe('Discord Display')
        ->and($props['socialAccounts']['discord']['avatar'])->toBe('https://cdn.discordapp.com/avatars/discord-id/avatar-hash.png')
        ->and($props['socialAccounts']['google']['display_name'])->toBe('Google Given')
        ->and($props['socialAccounts']['google']['avatar'])->toBe('https://google.example/avatar.png')
        ->and($props['socialAccounts']['steam']['display_name'])->toBe('Steam Persona')
        ->and($props['socialAccounts']['steam']['avatar'])->toBe('https://steam.example/avatar.png')
        ->and($props['socialAccounts']['telegram']['display_name'])->toBe('Telegram User')
        ->and($props['socialAccounts']['telegram']['avatar'])->toBe('https://telegram.example/avatar.png')
        ->and($props['socialAccounts']['itchio']['display_name'])->toBe('Itch Display')
        ->and($props['socialAccounts']['itchio']['avatar'])->toBe('https://itch.example/cover.png');
});

it('creates default notification preferences and exposes discord install metadata', function () {
    config(['services.discord.client_id' => 'discord-client']);

    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->discord()->create();

    $this->actingAs($user)->getJson(route('browser-api.dashboard.notifications.get'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('preferences.browser_notifications_enabled', false)
        ->assertJsonPath('preferences.discord_notifications_enabled', false)
        ->assertJsonPath('preferences.notification_digest', 'asap')
        ->assertJsonPath('discordInfo.hasAccount', true)
        ->assertJsonPath('discordInfo.botInstallUrl', 'https://discord.com/oauth2/authorize?client_id=discord-client&integration_type=1&scope=applications.commands');

    expect($user->notificationPreferences()->exists())->toBeTrue();
});

it('updates notification preferences and rejects invalid digest values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notifications.update'), [
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'weekly',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('preferences.browser_notifications_enabled', true)
        ->assertJsonPath('preferences.discord_notifications_enabled', true)
        ->assertJsonPath('preferences.notification_digest', 'weekly');

    $this->actingAs($user)->postJson(route('browser-api.dashboard.notifications.update'), [
        'notification_digest' => 'hourly',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['notification_digest']);
});

it('submits addition requests and reports empty input as a validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('browser-api.dashboard.addition-requests.submit'), [
        'urls' => '',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.urls', 'Please enter at least one valid game URL.');

    $this->actingAs($user)->postJson(route('browser-api.dashboard.addition-requests.submit'), [
        'urls' => "https://example.itch.io/new-game\nhttps://example.itch.io/new-game",
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('result.success_count', 1)
        ->assertJsonPath('result.duplicate_count', 1);
});

it('returns user game stats from progress, reviews, owned games, monthly completions, and tags', function () {
    $user = User::factory()->create();
    $completedGame = Game::factory()->create([
        'itch_id' => 111,
        'name' => 'Completed Game',
        'platform' => 'itch_io',
        'is_visible' => true,
    ]);
    $readingGame = Game::factory()->create([
        'itch_id' => 222,
        'name' => 'Reading Game',
        'platform' => 'itch_io',
        'is_visible' => true,
        'additional_links' => [['label' => 'Patch', 'url' => 'https://example.com']],
    ]);
    $tag = Tag::create(['name' => 'Drama']);
    DB::table('game_tag')->insert([
        ['game_id' => $completedGame->id, 'tag_id' => $tag->id],
        ['game_id' => $readingGame->id, 'tag_id' => $tag->id],
    ]);

    SocialAccount::factory()->for($user)->itchio()->create([
        'provider_data' => [
            'username' => 'stats-owner',
            'url' => 'https://stats-owner.itch.io',
        ],
        'itchio_game_ids' => [111, 222],
    ]);

    UserGameProgress::factory()->for($user)->for($completedGame)->completed()->create([
        'completed_at' => now()->startOfMonth(),
    ]);
    UserGameProgress::factory()->for($user)->for($readingGame)->reading()->create();

    $rater = Rater::factory()->create();
    Rating::create([
        'event_id' => 123456,
        'game_id' => $completedGame->id,
        'rater_id' => $rater->id,
        'user_id' => $user->id,
        'rating' => 5,
        'review' => 'Great.',
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]);

    $this->actingAs($user)->getJson(route('browser-api.dashboard.game-stats'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('stats.itchioUsername', 'stats-owner')
        ->assertJsonPath('stats.ownedGamesCount', 2)
        ->assertJsonPath('stats.gamesWithLinksCount', 1)
        ->assertJsonPath('stats.progress.total', 2)
        ->assertJsonPath('stats.progress.completed', 1)
        ->assertJsonPath('stats.progress.reading', 1)
        ->assertJsonPath('stats.progress.total_hours', 0)
        ->assertJsonPath('stats.reviewsCount', 1)
        ->assertJsonPath('stats.topTags.Drama', 2);
});

it('requires authentication for dashboard JSON endpoints', function () {
    $this->getJson(route('browser-api.dashboard.notifications.get'))
        ->assertUnauthorized();

    $this->postJson(route('browser-api.dashboard.notifications.update'), [])
        ->assertUnauthorized();

    $this->postJson(route('browser-api.dashboard.addition-requests.submit'), [])
        ->assertUnauthorized();

    $this->getJson(route('browser-api.dashboard.game-stats'))
        ->assertUnauthorized();
});

it('lists and cancels the current users addition requests', function () {
    $user = User::factory()->create();
    $reviewer = User::factory()->create(['name' => 'Queue Reviewer']);
    $game = Game::factory()->create(['name' => 'Approved Game', 'slug' => 'approved-game']);
    $pending = AdditionRequest::factory()->create([
        'game_url' => 'https://pending.itch.io/game',
        'status' => AdditionRequest::STATUS_PENDING,
    ]);
    $approved = AdditionRequest::factory()->approved()->create([
        'game_id' => $game->id,
        'reviewed_by' => $reviewer->id,
    ]);
    $other = AdditionRequest::factory()->create();

    $pending->users()->attach($user->id);
    $approved->users()->attach($user->id);
    $other->users()->attach(User::factory()->create()->id);

    $this->actingAs($user)->getJson(route('browser-api.dashboard.addition-requests.index', ['status' => 'completed']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'requests')
        ->assertJsonPath('requests.0.id', $approved->id)
        ->assertJsonPath('requests.0.game.slug', $game->slug)
        ->assertJsonPath('requests.0.reviewer.name', 'Queue Reviewer');

    $this->actingAs($user)->postJson(route('browser-api.dashboard.addition-requests.cancel', $pending))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($pending->users()->whereKey($user->id)->exists())->toBeFalse();
});

it('rejects cancelling addition requests the user cannot cancel', function () {
    $user = User::factory()->create();
    $request = AdditionRequest::factory()->approved()->create();
    $request->users()->attach($user->id);

    $this->actingAs($user)->postJson(route('browser-api.dashboard.addition-requests.cancel', $request))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('redirects merge requests to the selected provider flow', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('user.merge', 'telegram'))
        ->assertRedirect(route('auth.telegram'));

    expect(session('merging_user_id'))->toBe($user->id);

    $this->actingAs($user)->post(route('user.merge', 'itchio'))
        ->assertRedirect(route('auth.redirect', ['provider' => 'itchio']));
});

it('disconnects a linked provider only when another login provider remains', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->itchio()->create();

    $this->actingAs($user)->deleteJson(route('user.disconnect', 'itchio'))
        ->assertRedirect(route('dashboard'));

    expect($user->socialAccounts()->where('provider_name', 'itchio')->exists())->toBeTrue();

    SocialAccount::factory()->for($user)->discord()->create();

    $this->actingAs($user)->deleteJson(route('user.disconnect', 'itchio'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('provider', 'itchio');

    expect($user->socialAccounts()->where('provider_name', 'itchio')->exists())->toBeFalse()
        ->and($user->socialAccounts()->where('provider_name', 'discord')->exists())->toBeTrue();
});

it('deletes the authenticated account and anonymizes retained data', function () {
    User::query()->firstOrCreate(
        ['id' => 1],
        [
            'name' => 'System User',
            'email' => 'system@example.test',
            'password' => bcrypt('password'),
        ],
    );
    $user = User::factory()->create();
    $reviewerRequest = AdditionRequest::factory()->approved()->create([
        'reviewed_by' => $user->id,
    ]);
    $game = Game::factory()->create([
        'custom_page_updated_by' => $user->id,
        'has_custom_page' => true,
        'custom_name' => 'Deleted User Custom Name',
        'custom_description' => '<p>Deleted user custom page</p>',
        'custom_screenshots' => [['url' => 'https://custom.example/screen.jpg']],
        'custom_assets' => ['asset' => true],
        'custom_css' => '.custom {}',
        'custom_tags' => null,
        'custom_page_updated_at' => now(),
    ]);
    $version = GameVersion::factory()->for($game)->create();
    $list = VnList::factory()->for($user)->create();
    VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
    ]);
    SocialAccount::factory()->for($user)->discord()->create();
    $user->notificationPreferences()->create([
        'browser_notifications_enabled' => true,
        'discord_notifications_enabled' => true,
        'notification_digest' => 'weekly',
    ]);
    $user->ignoredGames()->attach($game->id);
    UserGameProgress::factory()->for($user)->for($game)->create([
        'game_version_id' => $version->id,
    ]);
    NotificationHistory::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ]);
    ChangeLog::create([
        'timestamp' => now(),
        'event_type' => ChangeLog::EVENT_UPDATED,
        'entity_type' => User::class,
        'entity_id' => $user->id,
        'user_id' => $user->id,
        'changes' => ['name'],
        'old_values' => ['name' => 'Old'],
        'new_values' => ['name' => 'New'],
        'context' => ['ip_address' => '203.0.113.88'],
        'source' => ChangeLog::SOURCE_WEB,
    ]);
    ClickStat::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'delete-account-session',
        'ip_address' => '203.0.113.99',
        'user_agent' => 'Browser',
        'clicked_at' => now(),
    ]);

    $this->be($user, 'web');

    $this->deleteJson(route('user.account.delete'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($reviewerRequest->refresh()->reviewed_by)->toBe(SystemAuditUser::id())
        ->and($game->refresh()->has_custom_page)->toBeFalse()
        ->and($game->custom_name)->toBeNull()
        ->and($game->custom_description)->toBeNull()
        ->and($game->custom_screenshots)->toBeNull()
        ->and($game->custom_page_updated_by)->toBeNull()
        ->and(SocialAccount::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(UserGameProgress::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(NotificationHistory::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('user_ignored_games')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(ClickStat::first()->user_id)->toBeNull()
        ->and(ClickStat::first()->ip_address)->not->toBe('203.0.113.99');
});

it('renders digest notifications for dates with and without user notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $date = now()->subDay()->toDateString();

    $otherNotification = NotificationHistory::create([
        'user_id' => $other->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ]);
    $otherNotification->forceFill(['created_at' => "{$date} 12:00:00"])->save();

    $emptyResponse = $this->actingAs($user)->get(route('user.notifications.digest', $date));
    $emptyResponse->assertOk();
    $emptyPage = $emptyResponse->viewData('page');
    expect($emptyPage['component'])->toBe('dashboard/digest-notifications')
        ->and($emptyPage['props']['hasNotifications'])->toBeFalse()
        ->and($emptyPage['props']['hasAnyNotifications'])->toBeTrue();

    $userNotification = NotificationHistory::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ]);
    $userNotification->forceFill(['created_at' => "{$date} 13:00:00"])->save();

    $filledResponse = $this->actingAs($user)->get(route('user.notifications.digest', $date));
    $filledResponse->assertOk();
    $filledPage = $filledResponse->viewData('page');
    expect($filledPage['props']['hasNotifications'])->toBeTrue()
        ->and($filledPage['props']['notifications'])->toHaveCount(1);
});

it('guards dashboard version comparisons by user access to the game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $fromVersion = GameVersion::factory()->for($game)->create(['version' => '1.0']);
    $toVersion = GameVersion::factory()->for($game)->create(['version' => '1.1']);

    $payload = [
        'gameId' => $game->id,
        'fromVersionId' => $fromVersion->id,
        'toVersionId' => $toVersion->id,
    ];

    $this->actingAs($user)->postJson(route('users.dashboard.version-comparison'), $payload)
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $list = VnList::factory()->for($user)->create();
    VnListEntry::factory()->create([
        'vn_list_id' => $list->id,
        'game_id' => $game->id,
    ]);

    $this->actingAs($user)->postJson(route('users.dashboard.version-comparison'), $payload)
        ->assertOk();
});
