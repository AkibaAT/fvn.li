<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

ob_start();
require __DIR__ . '/make-preferences-review-fixture.php';
$fixture = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
$tags = [];
for ($i = 1; $i <= 12; $i++) {
    $tags[] = Tag::create(['name' => "Fixture tag {$i} {$suffix}"])->id;
}
$game->tags()->sync($tags);

// Public UI hides tags used by fewer than Tag::minPublicGameCount() games.
$extraGameCount = max(0, Tag::minPublicGameCount() - 1);
if ($extraGameCount > 0) {
    $extraGames = Game::factory()->count($extraGameCount)->create(['is_visible' => true]);
    foreach ($extraGames as $extraGame) {
        $extraGame->tags()->sync($tags);
    }
}
foreach (['eng', 'deu', 'fra', 'spa', 'ita', 'jpn', 'kor', 'por', 'pol'] as $lang) {
    DB::table('iso_639_3_languages')->insertOrIgnore(['id' => $lang, 'scope' => 'I', 'type' => 'L', 'ref_name' => $lang, 'flag_code' => 'gb']);
    $version->addSupportedLanguage($lang);
}
$server->update(['available_channels' => array_merge($server->available_channels ?? [], array_map(fn ($id) => ['id' => $id, 'name' => $id, 'type' => 0], ['111', '222', '333']))]);
$override = $server->gameOverrides()->first();
$override->update(['is_ignored' => false, 'channel_id' => '111']);
echo json_encode([...$fixture, 'overrideId' => $override->id]);
