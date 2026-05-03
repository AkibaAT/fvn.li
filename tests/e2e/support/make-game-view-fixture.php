<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\User;
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

$originalName = "Original itch.io Name {$suffix}";
$customName = "Custom Draft Name {$suffix}";
$originalDescription = "<p>Original itch.io body {$suffix}</p>";
$customDescription = "<p>Custom draft body {$suffix}</p>";

$game = Game::factory()->create([
    'name' => $originalName,
    'description' => "Original summary {$suffix}",
    'full_description' => $originalDescription,
    'is_visible' => true,
    'status' => 'Published',
    'has_custom_page' => true,
    'view_mode' => 'original',
    'custom_name' => $customName,
    'custom_description' => $customDescription,
    'custom_page_updated_by' => $user->id,
    'screenshots' => [
        [
            'url' => 'https://example.test/original-screenshot.jpg',
            'thumbnail_url' => 'https://example.test/original-screenshot-thumb.jpg',
        ],
    ],
    'custom_screenshots' => [
        [
            'url' => 'https://example.test/custom-screenshot.jpg',
            'thumbnail_url' => 'https://example.test/custom-screenshot-thumb.jpg',
        ],
    ],
]);

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
    'originalName' => $originalName,
    'customName' => $customName,
    'originalDescription' => "Original itch.io body {$suffix}",
    'customDescription' => "Custom draft body {$suffix}",
], JSON_THROW_ON_ERROR);
