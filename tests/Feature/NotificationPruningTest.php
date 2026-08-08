<?php

declare(strict_types=1);

use App\Models\DiscordChannelAnnouncement;
use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\NotificationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ageModel($model, int $days): void
{
    $model->timestamps = false;
    $model->forceFill(['created_at' => now()->subDays($days), 'updated_at' => now()->subDays($days)])->save();
}

it('prunes only terminal notification rows beyond each retention cutoff', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create();
    $version = GameVersion::factory()->for($game)->create();
    $server = DiscordServer::factory()->create();

    $oldQueue = NotificationQueue::create([
        'user_id' => $user->id, 'channel' => 'browser', 'status' => 'sent', 'scheduled_at' => now(),
    ]);
    $oldPendingQueue = NotificationQueue::create([
        'user_id' => $user->id, 'channel' => 'browser', 'status' => 'pending', 'scheduled_at' => now(),
    ]);
    ageModel($oldQueue, 31);
    ageModel($oldPendingQueue, 31);

    $oldHistory = NotificationHistory::create([
        'user_id' => $user->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'type' => 'browser',
        'success' => true,
    ]);
    ageModel($oldHistory, 91);

    $oldDiscord = DiscordNotificationHistory::create([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'notification_type' => 'update',
        'channel_id' => 'channel',
        'delivery_status' => 'failed',
    ]);
    $oldPendingDiscord = DiscordNotificationHistory::create([
        'discord_server_id' => $server->id,
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'notification_type' => 'update',
        'channel_id' => 'channel',
        'delivery_status' => 'pending',
    ]);
    ageModel($oldDiscord, 91);
    ageModel($oldPendingDiscord, 91);

    $oldAnnouncement = DiscordChannelAnnouncement::create([
        'game_id' => $game->id,
        'game_version_id' => $version->id,
        'status' => 'sent',
    ]);
    ageModel($oldAnnouncement, 91);

    expect((new NotificationQueue)->prunable()->pluck('id')->all())->toBe([$oldQueue->id])
        ->and((new NotificationHistory)->prunable()->pluck('id')->all())->toBe([$oldHistory->id])
        ->and((new DiscordNotificationHistory)->prunable()->pluck('id')->all())->toBe([$oldDiscord->id])
        ->and((new DiscordChannelAnnouncement)->prunable()->pluck('id')->all())->toBe([$oldAnnouncement->id])
        ->and($oldPendingQueue->fresh())->not->toBeNull()
        ->and($oldPendingDiscord->fresh())->not->toBeNull();
});
