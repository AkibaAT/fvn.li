<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Services\BotDetectionService;

const CRAWLER_UA = 'Mozilla/5.0 (compatible; DataForSeoBot/1.0; +https://dataforseo.com/dataforseo-bot)';
const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

beforeEach(function () {
    BotDetectionService::flushCache();
});

it('flags automated hits as they are recorded', function () {
    $game = Game::factory()->create();

    $humanRecorded = ClickStat::recordClick(
        gameId: $game->id,
        type: ClickStat::TYPE_PAGE_VIEW,
        sessionId: 'session-human',
        ipAddress: '203.0.113.44',
        userAgent: BROWSER_UA,
    );

    $botRecorded = ClickStat::recordClick(
        gameId: $game->id,
        type: ClickStat::TYPE_PAGE_VIEW,
        sessionId: 'session-bot',
        ipAddress: '203.0.113.44',
        userAgent: CRAWLER_UA,
    );

    expect($humanRecorded)->toBeTrue()
        ->and($botRecorded)->toBeFalse()
        ->and(ClickStat::where('session_id', 'session-human')->value('bot_reason'))->toBeNull()
        ->and(ClickStat::where('session_id', 'session-bot')->value('bot_reason'))
        ->toBe(BotDetectionService::REASON_CRAWLER_UA);
});

it('flags hits from blocked networks before the address is anonymised', function () {
    $game = Game::factory()->create();

    ClickStat::recordClick(
        gameId: $game->id,
        type: ClickStat::TYPE_EXTERNAL_PROJECT,
        sessionId: 'session-proxied',
        ipAddress: '40.223.170.219',
        userAgent: BROWSER_UA,
    );

    $click = ClickStat::where('session_id', 'session-proxied')->firstOrFail();

    expect($click->bot_reason)->toBe(BotDetectionService::REASON_BLOCKED_NETWORK)
        ->and($click->ip_address)->toBe('40.223.170.0');
});

it('flags crawler page views served through the tracking middleware', function () {
    $game = Game::factory()->create(['is_visible' => true]);

    $this->withHeader('User-Agent', CRAWLER_UA)
        ->get(route('games.show', $game->slug))
        ->assertSuccessful();

    expect(ClickStat::where('game_id', $game->id)->where('type', ClickStat::TYPE_PAGE_VIEW)->count())->toBe(1)
        ->and(ClickStat::where('game_id', $game->id)->value('bot_reason'))
        ->toBe(BotDetectionService::REASON_CRAWLER_UA);
});

it('keeps automated hits out of the reported game stats', function () {
    $game = Game::factory()->create();

    foreach (range(1, 40) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'bot-' . $index,
            'ip_address' => '40.223.170.' . $index,
            'user_agent' => BROWSER_UA,
            'bot_reason' => BotDetectionService::REASON_BLOCKED_NETWORK,
            'clicked_at' => now(),
        ]);
    }

    foreach (range(1, 3) as $index) {
        ClickStat::create([
            'game_id' => $game->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'human-' . $index,
            'ip_address' => '203.0.113.' . $index,
            'user_agent' => BROWSER_UA,
            'clicked_at' => now(),
        ]);
    }

    $stats = ClickStat::getGameStats($game->id, now()->subDays(30));

    expect($stats['page_views_total'])->toBe(3)
        ->and($stats['page_views_unique'])->toBe(3);

    $today = collect(ClickStat::getDailyStats($game->id, 30))
        ->firstWhere('date', now()->format('Y-m-d'));

    expect($today['page_views_total'])->toBe(3)
        ->and($today['page_views_unique'])->toBe(3);
});
