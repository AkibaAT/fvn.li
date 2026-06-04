<?php

declare(strict_types=1);

use App\Models\AdditionRequest;
use App\Models\BugReport;
use App\Models\BugReportComment;
use App\Models\ClickStat;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\SocialAccount;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';

putenv('APP_ENV=testing');
putenv('DB_DATABASE=db_test');
putenv('SCOUT_DRIVER=collection');
putenv('MEILISEARCH_HOST=http://localhost:9999');
putenv('SESSION_DRIVER=database');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = 'db_test';
$_ENV['SCOUT_DRIVER'] = 'collection';
$_ENV['MEILISEARCH_HOST'] = 'http://localhost:9999';
$_ENV['SESSION_DRIVER'] = 'database';
$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_DATABASE'] = 'db_test';
$_SERVER['SCOUT_DRIVER'] = 'collection';
$_SERVER['MEILISEARCH_HOST'] = 'http://localhost:9999';
$_SERVER['SESSION_DRIVER'] = 'database';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

$defaultConnection = Config::get('database.default');
Config::set("database.connections.{$defaultConnection}.database", 'db_test');
Config::set('scout.driver', 'collection');
Config::set('scout.queue', false);
Config::set('session.driver', 'database');
DB::purge($defaultConnection);

$suffix = Str::lower(Str::random(8));

$user = User::factory()->create([
    'name' => "E2E Admin {$suffix}",
    'email' => "e2e-admin-{$suffix}@example.test",
    'is_admin' => true,
]);

$staffUser = User::factory()->create([
    'name' => "E2E Staff {$suffix}",
    'email' => "e2e-staff-{$suffix}@example.test",
    'is_admin' => true,
]);

$originalName = "Original itch.io Name {$suffix}";
$customName = "Custom Draft Name {$suffix}";
$originalDescription = "<p>Original itch.io body {$suffix}</p>";
$customDescription = "<p>Custom draft body {$suffix}</p>";

$game = Game::factory()->create([
    'itch_id' => random_int(100000, 999999),
    'name' => $originalName,
    'description' => "Original summary {$suffix}",
    'full_description' => $originalDescription,
    'is_visible' => true,
    'status' => 'Published',
    'platform' => 'itch_io',
    'url' => [
        'itch_io' => "https://e2e-owner-{$suffix}.itch.io/main-game-{$suffix}",
    ],
    'has_custom_page' => true,
    'view_mode' => 'original',
    'custom_name' => $customName,
    'custom_description' => $customDescription,
    'custom_page_updated_by' => $user->id,
    'additional_links' => [
        [
            'id' => 'walkthrough',
            'name' => 'Walkthrough',
            'url' => 'https://example.test/walkthrough',
            'released' => true,
            'sort_order' => 1,
        ],
    ],
    'optimized_thumbnails' => [
        'default' => [
            'path' => "e2e/{$suffix}/cover.webp",
            'width' => 315,
            'height' => 250,
        ],
    ],
    'screenshots' => [
        [
            'url' => 'https://example.test/original-screenshot.jpg',
            'thumbnail_url' => 'https://example.test/original-screenshot-thumb.jpg',
            'optimized' => [
                'default' => [
                    'path' => "e2e/{$suffix}/original-screenshot-default.webp",
                    'width' => 315,
                    'height' => 250,
                ],
                'large' => [
                    'path' => "e2e/{$suffix}/original-screenshot-large.webp",
                    'width' => 1280,
                    'height' => 720,
                ],
            ],
        ],
    ],
    'custom_screenshots' => [
        [
            'url' => 'https://example.test/custom-screenshot.jpg',
            'thumbnail_url' => 'https://example.test/custom-screenshot-thumb.jpg',
            'optimized' => [
                'default' => [
                    'path' => "e2e/{$suffix}/custom-screenshot-default.webp",
                    'width' => 315,
                    'height' => 250,
                ],
                'large' => [
                    'path' => "e2e/{$suffix}/custom-screenshot-large.webp",
                    'width' => 1280,
                    'height' => 720,
                ],
            ],
        ],
    ],
]);

GameVersion::factory()->latest()->create([
    'game_id' => $game->id,
    'version' => '1.0.0',
    'published_at' => now()->subDays(2),
    'is_windows' => true,
    'is_linux' => true,
    'is_mac' => true,
]);

SocialAccount::factory()->for($user)->itchio()->create([
    'provider_data' => [
        'username' => "e2e-owner-{$suffix}",
        'url' => "https://e2e-owner-{$suffix}.itch.io",
        'display_name' => "E2E Owner {$suffix}",
    ],
    'itchio_game_ids' => [$game->itch_id],
]);
SocialAccount::factory()->for($user)->discord()->create();

$ignoredGame = Game::factory()->create([
    'name' => "Ignored Game {$suffix}",
    'is_visible' => true,
]);
$user->ignoredGames()->attach($ignoredGame->id);

$excludedTag = Tag::create(['name' => "E2E Excluded {$suffix}"]);
UserPreference::create([
    'user_id' => $user->id,
    'preferred_languages' => ['eng'],
    'excluded_tags' => [$excludedTag->id],
]);

$pendingRequest = AdditionRequest::factory()->create([
    'game_url' => "https://pending-{$suffix}.itch.io/new-vn",
    'normalized_url' => "pending-{$suffix}.itch.io/new-vn",
]);
$pendingRequest->addUser($user);

$approvedRequest = AdditionRequest::factory()->approved()->create([
    'game_url' => "https://approved-{$suffix}.itch.io/approved-vn",
    'normalized_url' => "approved-{$suffix}.itch.io/approved-vn",
    'game_id' => $game->id,
    'reviewed_by' => $staffUser->id,
]);
$approvedRequest->addUser($user);

$rejectedRequest = AdditionRequest::factory()->rejected()->create([
    'game_url' => "https://rejected-{$suffix}.itch.io/rejected-vn",
    'normalized_url' => "rejected-{$suffix}.itch.io/rejected-vn",
    'reviewed_by' => $staffUser->id,
]);
$rejectedRequest->addUser($user);

$bugReport = BugReport::create([
    'user_id' => $user->id,
    'page_url' => "http://web:8088/games/{$game->slug}",
    'page_title' => "Fixture bug report {$suffix}",
    'description' => "Fixture dashboard bug report {$suffix}",
    'request_parameters' => ['fixture' => $suffix],
    'user_agent' => 'Playwright fixture',
    'status' => BugReport::STATUS_IN_PROGRESS,
    'is_closed' => false,
]);
BugReportComment::create([
    'bug_report_id' => $bugReport->id,
    'user_id' => $staffUser->id,
    'message' => "Staff reply for accessibility fixture {$suffix}",
    'is_from_admin' => true,
    'is_read' => false,
]);

foreach ([ClickStat::TYPE_PAGE_VIEW, ClickStat::TYPE_PAGE_VIEW, ClickStat::TYPE_EXTERNAL_PROJECT, ClickStat::TYPE_CUSTOM_LINK] as $index => $type) {
    ClickStat::create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'type' => $type,
        'link_id' => $type === ClickStat::TYPE_CUSTOM_LINK ? 'walkthrough' : null,
        'session_id' => "e2e-session-{$suffix}-{$index}",
        'ip_address' => "127.0.0.{$index}",
        'user_agent' => 'Playwright fixture',
        'referrer' => null,
        'clicked_at' => now()->subDays($index),
    ]);
}

$session = app('session')->driver();
$session->setId(Str::random(40));
$session->start();
$session->put('_token', Str::random(40));
Auth::guard('web')->login($user);
$session->save();

$sessionCookieName = Config::get('session.cookie');
$encryptedSessionId = app('encrypter')->encrypt(
    CookieValuePrefix::create($sessionCookieName, app('encrypter')->getKey()).$session->getId(),
    false,
);

echo json_encode([
    'userId' => $user->id,
    'authCookie' => [
        'name' => $sessionCookieName,
        'value' => $encryptedSessionId,
    ],
    'slug' => $game->slug,
    'bugReportId' => $bugReport->id,
    'additionRequestUrl' => $pendingRequest->game_url,
    'originalName' => $originalName,
    'customName' => $customName,
    'originalDescription' => "Original itch.io body {$suffix}",
    'customDescription' => "Custom draft body {$suffix}",
], JSON_THROW_ON_ERROR);
