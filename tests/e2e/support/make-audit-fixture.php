<?php

use App\Models\BugReport;
use App\Models\Tag;
use App\Models\UserGameProgress;
use Illuminate\Support\Str;

ob_start();
require __DIR__ . '/make-account-list-fixture.php';
$f = json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
if (! $user->vnLists()->where('is_default', true)->exists()) {
    $user->initializeDefaultLists();
}
$reading = $user->vnLists()->where('type', 'reading')->firstOrFail();
$reading->addGame($game->id);
UserGameProgress::updateOrCreate(['user_id' => $user->id, 'game_id' => $game->id], ['receive_updates' => true]);
$bugReport->updateQuietly(['description' => "Audit report A {$suffix}"]);
$second = BugReport::withoutEvents(fn () => BugReport::create(['user_id' => $user->id, 'page_url' => 'http://web:8088/dashboard', 'description' => "Audit report B {$suffix}", 'status' => 'open', 'is_closed' => false]));
$shots = array_map(fn ($n) => ['url' => asset("storage/e2e/audit-shot-{$n}.webp"), 'optimized' => ['default' => ['path' => "e2e/audit-shot-{$n}.webp"], 'large' => ['path' => "e2e/audit-shot-{$n}.webp"]]], ['a', 'b', 'c']);
$game->updateQuietly(['has_custom_page' => true, 'view_mode' => 'custom', 'custom_screenshots' => $shots]);
if (in_array('--without-thumbnail', $argv, true)) {
    $game->updateQuietly(['thumb_url' => null, 'optimized_thumbnails' => null]);
}
$server->config->updateQuietly(['routing_rules' => [['id' => 'audit-rule', 'name' => 'Audit tags rule', 'enabled' => true, 'priority' => 1, 'conditions' => [['field' => 'tags', 'operator' => 'contains_any', 'value' => ['audit alpha']], ['field' => 'tags', 'operator' => 'contains_any', 'value' => ['audit beta']]], 'action' => ['type' => 'ignore']]]]);
foreach (['Audit Alpha', 'Audit Beta'] as $name) {
    Tag::withoutEvents(fn () => Tag::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]));
}
echo json_encode([...$f, 'userName' => $user->name, 'secondReportId' => $second->id, 'readingListId' => $reading->id, 'reportA' => $bugReport->description, 'reportB' => $second->description]);
