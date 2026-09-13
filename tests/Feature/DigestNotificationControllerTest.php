<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\NotificationHistory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('digest entries show the game version and delivery status for the current user', function () {
    $user = User::factory()->createQuietly();
    $game = Game::factory()->createQuietly([
        'name' => 'Original VN', 'slug' => 'digest-vn', 'custom_name' => 'Renamed VN',
        'has_custom_page' => true, 'view_mode' => 'custom', 'is_visible' => true,
    ]);
    $version = GameVersion::factory()->createQuietly(['game_id' => $game->id, 'version' => '2.0']);
    foreach (['browser' => true, 'discord' => false] as $type => $success) {
        NotificationHistory::record([
            'user_id' => $user->id, 'game_id' => $game->id, 'game_version_id' => $version->id,
            'type' => $type, 'success' => $success, 'created_at' => '2026-05-03 00:30:00',
            'meta_data' => ['error' => 'Internal transport detail'],
        ]);
    }
    NotificationHistory::record([
        'user_id' => User::factory()->createQuietly()->id, 'game_id' => $game->id, 'game_version_id' => $version->id,
        'type' => 'email', 'success' => true, 'created_at' => '2026-05-03 00:30:00',
    ]);

    $this->actingAs($user)->get(route('user.notifications.digest', '2026-05-03'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/digest-notifications')
            ->has('notifications', 2)
            ->where('notifications', function ($entries) use ($game) {
                expect(collect($entries)->pluck('message')->sort()->values()->all())->toBe([
                    'Version 2.0 · Delivery failed via Discord', 'Version 2.0 · Sent via browser notification',
                ]);
                foreach ($entries as $entry) {
                    expect($entry['title'])->toBe('Renamed VN')
                        ->and($entry['url'])->toBe(route('games.show', $game->slug))
                        ->and($entry['created_at'])->toBe('2026-05-03T00:30:00.000000Z')
                        ->and($entry)->not->toHaveKey('meta_data');
                }

                return true;
            }));
});
