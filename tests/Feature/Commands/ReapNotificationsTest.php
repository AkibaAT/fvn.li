<?php

declare(strict_types=1);

use App\Models\DiscordChannelAnnouncement;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires ancient pending work and hidden-game announcements while preserving deliverable rows', function () {
    $user = User::factory()->create();
    $expired = NotificationQueue::create([
        'user_id' => $user->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subDays(15),
        'payload' => [],
    ]);
    $fresh = NotificationQueue::create([
        'user_id' => $user->id,
        'channel' => 'browser',
        'status' => 'pending',
        'scheduled_at' => now()->subDay(),
        'payload' => [],
    ]);

    $hiddenGame = Game::factory()->create(['is_visible' => false]);
    $hiddenVersion = GameVersion::factory()->for($hiddenGame)->create();
    $hidden = DiscordChannelAnnouncement::create([
        'game_id' => $hiddenGame->id,
        'game_version_id' => $hiddenVersion->id,
        'status' => 'pending',
    ]);
    $hidden->timestamps = false;
    $hidden->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();

    $visibleGame = Game::factory()->create(['is_visible' => true]);
    $visibleVersion = GameVersion::factory()->for($visibleGame)->create();
    $visible = DiscordChannelAnnouncement::create([
        'game_id' => $visibleGame->id,
        'game_version_id' => $visibleVersion->id,
        'status' => 'pending',
    ]);
    $visible->timestamps = false;
    $visible->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();

    $this->artisan('notifications:reap')
        ->expectsOutput('Reaped 1 expired notifications and 1 hidden-game announcements.')
        ->assertSuccessful();

    expect($expired->fresh()->status)->toBe('failed')
        ->and($expired->fresh()->error)->toBe('notification_expired')
        ->and($fresh->fresh()->status)->toBe('pending')
        ->and($hidden->fresh()->status)->toBe('failed')
        ->and($hidden->fresh()->error)->toBe('game_not_visible')
        ->and($visible->fresh()->status)->toBe('pending');
});
