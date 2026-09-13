<?php

declare(strict_types=1);

use App\Models\Character;
use App\Models\DiscordServer;
use App\Models\Rater;
use App\Models\Rating;
use App\Models\VersionCharacterStats;
use App\Models\VersionFileCategory;
use App\Models\VnList;
use Illuminate\Support\Facades\DB;

ob_start();
require __DIR__ . '/make-history-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

$ratings = Rating::where('rater_id', $fixture['raterId'])->orderByDesc('published_at')->limit(2)->get();
foreach ($ratings as $rating) {
    $old = $rating->replicate();
    $old->is_visible = false;
    $old->published_at = now()->subYear();
    $old->saveQuietly();
}

$server = DiscordServer::factory()->configured()->create(['owner_user_id' => $fixture['userId']]);
$server->gameOverrides()->create(['game_id' => $game->id, 'is_ignored' => true]);
$server->notificationHistory()->create([
    'game_id' => $game->id,
    'game_version_id' => $version->id,
    'notification_type' => 'update',
    'channel_id' => '111',
    'delivery_status' => 'sent',
    'sent_at' => now(),
]);

DB::table('iso_639_3_languages')->insertOrIgnore([
    'id' => 'eng', 'scope' => 'I', 'type' => 'L', 'ref_name' => 'English', 'part1' => 'en', 'flag_code' => 'gb',
]);
$game->forceFill(['source_language_id' => 'eng'])->saveQuietly();
$character = Character::factory()->create(['game_id' => $game->id, 'character_id' => 'alex', 'display_names' => ['eng' => 'Alex']]);
foreach ([$version->id, $older->id] as $versionId) {
    VersionCharacterStats::factory()->english()->create(['game_version_id' => $versionId, 'character_id' => $character->id]);
    VersionFileCategory::create(['game_version_id' => $versionId, 'category' => 'images', 'total_count' => 1, 'total_size' => 1024]);
}

$lists = [];
foreach (['Deleted list', 'Other list', 'Public list', 'Last public list'] as $index => $name) {
    $list = VnList::create([
        'user_id' => $fixture['userId'],
        'name' => $name,
        'type' => 'custom',
        'is_default' => false,
        'is_public' => $index >= 2,
    ]);
    $list->forceFill(['created_at' => now()->subMinutes($index)])->saveQuietly();
    $lists[] = $list->id;
}
foreach ([1, 5] as $stars) {
    Rating::withoutEvents(fn () => Rating::create([
        'game_id' => $game->id,
        'rater_id' => Rater::factory()->create()->id,
        'rating' => $stars,
        'review' => "Consistency {$stars} star review",
        'is_visible' => true,
        'is_reviewed' => true,
        'source_platform' => 'itch_io',
        'published_at' => now(),
    ]));
}
$user->socialAccounts()->create(['provider_name' => 'google', 'provider_id' => "consistency-{$user->id}"]);

echo json_encode([...$fixture, 'discordServerId' => $server->id, 'listIds' => $lists], JSON_THROW_ON_ERROR);
