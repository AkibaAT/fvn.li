<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Services\BotDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    BotDetectionService::flushCache();
});

function storeClick(Game $game, ?string $ipAddress, ?string $userAgent, ?string $botReason = null): ClickStat
{
    return ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => fake()->uuid(),
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'bot_reason' => $botReason,
        'clicked_at' => now(),
    ]);
}

test('it flags stored rows that match the current rules', function () {
    $game = Game::factory()->create();

    $human = storeClick($game, '203.0.113.0', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');
    $proxied = storeClick($game, '40.223.170.0', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');
    $crawler = storeClick($game, '203.0.113.0', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    $headless = storeClick($game, '203.0.113.0', null);

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();

    expect($human->refresh()->bot_reason)->toBeNull()
        ->and($proxied->refresh()->bot_reason)->toBe(BotDetectionService::REASON_BLOCKED_NETWORK)
        ->and($crawler->refresh()->bot_reason)->toBe(BotDetectionService::REASON_CRAWLER_UA)
        ->and($headless->refresh()->bot_reason)->toBe(BotDetectionService::REASON_NO_USER_AGENT);
});

test('it writes nothing on a dry run', function () {
    $game = Game::factory()->create();

    $proxied = storeClick($game, '40.223.170.0', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');

    $this->artisan('analytics:backfill-bot-flags', ['--dry-run' => true])
        ->expectsOutputToContain('Would re-classify 1 of 1 rows.')
        ->assertSuccessful();

    expect($proxied->refresh()->bot_reason)->toBeNull();
});

test('it clears flags on rows that no longer match', function () {
    $game = Game::factory()->create();

    $stale = storeClick(
        $game,
        '203.0.113.0',
        'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36',
        BotDetectionService::REASON_BLOCKED_NETWORK
    );

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();

    expect($stale->refresh()->bot_reason)->toBeNull();
});

test('it re-classifies after the blocked networks change', function () {
    $game = Game::factory()->create();

    $click = storeClick($game, '198.51.100.7', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();
    expect($click->refresh()->bot_reason)->toBeNull();

    Config::set('analytics.bot_detection.blocked_networks', ['198.51.100.0/24']);

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();
    expect($click->refresh()->bot_reason)->toBe(BotDetectionService::REASON_BLOCKED_NETWORK);
});

test('it is idempotent', function () {
    $game = Game::factory()->create();

    storeClick($game, '40.223.170.0', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');
    storeClick($game, '203.0.113.0', 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();

    $this->artisan('analytics:backfill-bot-flags')
        ->expectsOutputToContain('Re-classified 0 of 2 rows.')
        ->assertSuccessful();
});

test('it applies the session churn verdict after the per-row pass', function () {
    Config::set('analytics.bot_detection.session_churn.min_rows', 20);

    $game = Game::factory()->create();
    $churnUa = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36';

    foreach (range(1, 25) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'churn-' . $index,
            'ip_address' => '198.51.100.' . $index,
            'user_agent' => $churnUa,
            'clicked_at' => now(),
        ]);
    }

    $this->artisan('analytics:backfill-bot-flags')
        ->expectsOutputToContain('1 user agent days meet the session churn thresholds')
        ->assertSuccessful();

    expect(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25);
});

test('it holds the session churn verdict steady across runs', function () {
    Config::set('analytics.bot_detection.session_churn.min_rows', 20);

    $game = Game::factory()->create();
    $churnUa = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36';

    foreach (range(1, 25) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'churn-' . $index,
            'ip_address' => '198.51.100.' . $index,
            'user_agent' => $churnUa,
            'clicked_at' => now(),
        ]);
    }

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();

    $this->artisan('analytics:backfill-bot-flags')
        ->expectsOutputToContain('Re-classified 0 of 25 rows.')
        ->assertSuccessful();

    expect(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25);
});

test('it acquits a user agent once the thresholds no longer hold', function () {
    Config::set('analytics.bot_detection.session_churn.min_rows', 20);

    $game = Game::factory()->create();
    $churnUa = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36';

    foreach (range(1, 25) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'churn-' . $index,
            'ip_address' => '198.51.100.' . $index,
            'user_agent' => $churnUa,
            'clicked_at' => now(),
        ]);
    }

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();
    expect(ClickStat::whereNull('bot_reason')->count())->toBe(0);

    Config::set('analytics.bot_detection.session_churn.min_rows', 500);

    $this->artisan('analytics:backfill-bot-flags')->assertSuccessful();
    expect(ClickStat::whereNull('bot_reason')->count())->toBe(25);
});

test('it spans multiple chunks', function () {
    $game = Game::factory()->create();

    foreach (range(1, 25) as $index) {
        storeClick($game, '40.223.170.' . $index, 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36');
    }

    $this->artisan('analytics:backfill-bot-flags', ['--chunk' => 100])
        ->expectsOutputToContain('Re-classified 25 of 25 rows.')
        ->assertSuccessful();

    expect(ClickStat::whereNull('bot_reason')->count())->toBe(0);
});
