<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use App\Services\BotDetectionService;
use App\Services\SessionChurnDetector;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

const CHURN_UA = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36';
const REAL_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36';

beforeEach(function () {
    Config::set('analytics.bot_detection.session_churn.min_rows', 20);
});

/**
 * Traffic that never carries a session forward: one session and one subnet per hit.
 */
function churnTraffic(Game $game, string $userAgent, int $hits, ?CarbonInterface $day = null, ?int $userId = null): void
{
    $day ??= now();

    foreach (range(1, $hits) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'user_id' => $index === 1 ? $userId : null,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'churn-' . $day->format('Ymd') . '-' . $index,
            'ip_address' => '198.51.' . intdiv($index, 250) . '.' . ($index % 250),
            'user_agent' => $userAgent,
            'clicked_at' => $day,
        ]);
    }
}

/**
 * A real population: each visitor views several games in one session.
 */
function browsingTraffic(Game $game, string $userAgent, int $visitors, int $hitsEach = 3): void
{
    foreach (range(1, $visitors) as $visitor) {
        foreach (range(1, $hitsEach) as $ignored) {
            ClickStat::create([
                'game_id' => $game->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'visitor-' . $visitor,
                'ip_address' => '203.0.113.' . $visitor,
                'user_agent' => $userAgent,
                'clicked_at' => now(),
            ]);
        }
    }
}

it('convicts a user agent that opens a fresh session for every hit', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);

    $result = SessionChurnDetector::apply();

    expect($result['user_agent_days'])->toBe(1)
        ->and($result['convicted'])->toBe(25)
        ->and(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25);
});

it('spares a user agent whose visitors carry sessions forward', function () {
    $game = Game::factory()->create();

    browsingTraffic($game, REAL_UA, 12);

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(36);
});

it('spares a user agent below the daily row threshold', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 19);

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);
});

it('spares a user agent that any signed-in visitor used', function () {
    $game = Game::factory()->create();
    $user = User::factory()->create();

    churnTraffic($game, CHURN_UA, 25, userId: $user->id);

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);
});

it('spares churning traffic that stays on one subnet', function () {
    $game = Game::factory()->create();

    // A single visitor with cookies turned off is not a rotating pool.
    foreach (range(1, 25) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'no-cookies-' . $index,
            'ip_address' => '203.0.113.77',
            'user_agent' => CHURN_UA,
            'clicked_at' => now(),
        ]);
    }

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);
});

it('judges each day on its own behaviour', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25, now()->subDays(3));
    browsingTraffic($game, CHURN_UA, 12);

    SessionChurnDetector::apply();

    expect(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(36);
});

it('ignores rows already convicted by a per-row rule when judging', function () {
    $game = Game::factory()->create();

    browsingTraffic($game, REAL_UA, 12);

    // Crawler rows sharing the user agent would otherwise drag the day over
    // the thresholds.
    foreach (range(1, 60) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'blocked-' . $index,
            'ip_address' => '40.223.170.' . $index,
            'user_agent' => REAL_UA,
            'bot_reason' => BotDetectionService::REASON_BLOCKED_NETWORK,
            'clicked_at' => now(),
        ]);
    }

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);
});

it('acquits a day that no longer meets the thresholds', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);
    SessionChurnDetector::apply();
    expect(ClickStat::whereNull('bot_reason')->count())->toBe(0);

    Config::set('analytics.bot_detection.session_churn.min_rows', 500);

    $result = SessionChurnDetector::apply();

    expect($result['acquitted'])->toBe(25)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(25);
});

it('reaches the same verdict on a second run', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);
    SessionChurnDetector::apply();

    $result = SessionChurnDetector::apply();

    expect($result['convicted'])->toBe(0)
        ->and($result['acquitted'])->toBe(0)
        ->and(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25);
});

it('leaves days outside the window untouched', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25, now()->subDays(30));
    churnTraffic($game, CHURN_UA, 25, now());

    SessionChurnDetector::apply(now()->subDays(7));

    expect(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(25);
});

it('writes nothing on a dry run', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);

    $result = SessionChurnDetector::apply(dryRun: true);

    expect($result['convicted'])->toBe(25)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(25);
});

it('respects a loosened reuse ratio', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);

    // One shared session is more reuse than a zero allowance tolerates.
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'churn-' . now()->format('Ymd') . '-1',
        'ip_address' => '198.51.0.1',
        'user_agent' => CHURN_UA,
        'clicked_at' => now(),
    ]);

    Config::set('analytics.bot_detection.session_churn.max_session_reuse_ratio', 0.0);
    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);

    Config::set('analytics.bot_detection.session_churn.max_session_reuse_ratio', 0.5);
    expect(SessionChurnDetector::apply()['convicted'])->toBe(26);
});

it('neither judges nor convicts rows from an erased account', function () {
    $game = Game::factory()->create();

    churnTraffic($game, CHURN_UA, 25);

    // Same user agent and day as the convicted traffic, but belonging to a
    // visitor whose account was erased.
    foreach (range(1, 10) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'anonymized_' . $index,
            'ip_address' => null,
            'user_agent' => CHURN_UA,
            'clicked_at' => now(),
        ]);
    }

    SessionChurnDetector::apply();

    expect(ClickStat::where('bot_reason', BotDetectionService::REASON_SESSION_CHURN)->count())->toBe(25)
        ->and(ClickStat::whereNull('bot_reason')->count())->toBe(10);
});

it('ignores rows with no user agent', function () {
    $game = Game::factory()->create();

    foreach (range(1, 25) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'null-ua-' . $index,
            'ip_address' => '198.51.100.' . $index,
            'user_agent' => null,
            'bot_reason' => BotDetectionService::REASON_NO_USER_AGENT,
            'clicked_at' => now(),
        ]);
    }

    expect(SessionChurnDetector::apply()['convicted'])->toBe(0);
});
