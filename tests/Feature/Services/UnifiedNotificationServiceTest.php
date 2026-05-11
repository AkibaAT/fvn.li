<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationQueue;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\UserNotificationPreferences;
use App\Services\UnifiedNotificationService;

function unifiedNotificationVersion(Game $game, array $attributes = []): GameVersion
{
    $version = GameVersion::factory()->create(array_merge([
        'game_id' => $game->id,
        'version' => '1.0',
        'published_at' => now(),
    ], $attributes));
    $version->forceFill(['is_latest' => true])->save();

    return $version;
}

function unifiedNotificationUser(Game $game, bool $browser, bool $discord, bool $hasDiscordAccount = false, bool $receiveUpdates = true): User
{
    $user = User::factory()->create();
    UserGameProgress::factory()->create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $game->latestVersion?->id,
        'receive_updates' => $receiveUpdates,
    ]);
    UserNotificationPreferences::create([
        'user_id' => $user->id,
        'browser_notifications_enabled' => $browser,
        'discord_notifications_enabled' => $discord,
        'notification_digest' => 'asap',
    ]);

    if ($hasDiscordAccount) {
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider_name' => 'discord',
            'provider_id' => 'discord-'.$user->id,
        ]);
    }

    return $user;
}

it('queues browser and discord notifications only for opted-in recipients', function () {
    $service = app(UnifiedNotificationService::class);
    $game = Game::factory()->create([
        'name' => 'Notification Game',
        'is_paid' => false,
    ]);
    $version = unifiedNotificationVersion($game, [
        'version' => '2.0',
        'devlog' => 'Patch notes',
    ]);
    $game->setRelation('latestVersion', $version);

    $browserUser = unifiedNotificationUser($game, browser: true, discord: false);
    $discordUser = unifiedNotificationUser($game, browser: false, discord: true, hasDiscordAccount: true);
    unifiedNotificationUser($game, browser: true, discord: true, hasDiscordAccount: true, receiveUpdates: false);
    unifiedNotificationUser($game, browser: false, discord: true, hasDiscordAccount: false);

    $service->queueGameUpdate($game, $version);

    $notifications = NotificationQueue::query()->orderBy('channel')->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('channel')->all())->toBe(['browser', 'discord'])
        ->and($notifications->firstWhere('channel', 'browser')->user_id)->toBe($browserUser->id)
        ->and($notifications->firstWhere('channel', 'browser')->payload['title'])->toBe('Notification Game - New Update Available')
        ->and($notifications->firstWhere('channel', 'discord')->user_id)->toBe($discordUser->id)
        ->and($notifications->firstWhere('channel', 'discord')->payload['devlog'])->toBe('Patch notes');
});

it('does not queue notifications for paid games with stale subscriptions', function () {
    $service = app(UnifiedNotificationService::class);
    $game = Game::factory()->create([
        'name' => 'Paid Notification Game',
        'is_paid' => true,
    ]);
    $version = unifiedNotificationVersion($game, [
        'version' => '2.0',
    ]);
    $game->setRelation('latestVersion', $version);
    unifiedNotificationUser($game, browser: true, discord: true, hasDiscordAccount: true, receiveUpdates: true);

    $service->queueGameUpdate($game, $version);

    expect(NotificationQueue::query()->count())->toBe(0);
});

it('records manual discord updates and marks pending notifications as processed', function () {
    $service = app(UnifiedNotificationService::class);
    $game = Game::factory()->create([
        'name' => 'Manual Update Game',
        'is_paid' => false,
    ]);
    unifiedNotificationVersion($game, ['version' => '1.0']);
    $game->refresh();
    $discordUser = unifiedNotificationUser($game, browser: false, discord: true, hasDiscordAccount: true);

    $manualVersion = $service->recordManualDiscordUpdate(
        $game,
        'https://discord.example/update',
        'Manual Patch',
        'Manual notes'
    );

    expect($manualVersion->version)->toBe('Manual Patch')
        ->and($manualVersion->is_latest)->toBeTrue();

    $pending = $service->getPendingNotifications('discord', 10);
    expect($pending)->toHaveCount(1)
        ->and($pending->first()->user_id)->toBe($discordUser->id)
        ->and($pending->first()->payload['manual_update'])->toBeTrue()
        ->and($pending->first()->payload['update_url'])->toBe('https://discord.example/update');

    $service->markNotificationSent($pending->first(), success: false);

    $notification = $pending->first()->fresh();
    expect($notification->status)->toBe('failed')
        ->and($notification->processed_at)->not->toBeNull();
});
