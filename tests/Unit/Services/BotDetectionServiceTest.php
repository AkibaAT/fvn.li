<?php

declare(strict_types=1);

use App\Services\BotDetectionService;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    BotDetectionService::flushCache();
});

afterEach(function () {
    BotDetectionService::flushCache();
});

it('leaves ordinary browser traffic unflagged', function (string $userAgent) {
    expect(BotDetectionService::detect($userAgent, '203.0.113.0'))->toBeNull();
})->with([
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 OPR/133.0.0.0',
]);

it('does not mistake device names containing bot for crawlers', function () {
    $cubot = 'Mozilla/5.0 (Linux; Android 10; CUBOT NOTE 20) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Mobile Safari/537.36';

    expect(BotDetectionService::detect($cubot, '203.0.113.0'))->toBeNull();
});

it('flags self-declared crawlers', function (string $userAgent) {
    expect(BotDetectionService::detect($userAgent, '203.0.113.0'))
        ->toBe(BotDetectionService::REASON_CRAWLER_UA);
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; DataForSeoBot/1.0; +https://dataforseo.com/dataforseo-bot)',
    'Mozilla/5.0 (compatible; IbouBot/1.0; +bot@ibou.io; +https://ibou.io/iboubot.html)',
    'Mozilla/5.0 (compatible; jscrawler/0.1; +https://github.com/)',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 (compatible; meta-webindexer/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler))',
    'curl/8.5.0',
    'python-requests/2.32.3',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/142.0.0.0 Safari/537.36',
]);

it('flags hits with no user agent', function (?string $userAgent) {
    expect(BotDetectionService::detect($userAgent, '203.0.113.0'))
        ->toBe(BotDetectionService::REASON_NO_USER_AGENT);
})->with([null, '', '   ']);

it('flags blocked networks whatever the user agent claims', function (string $ipAddress) {
    $browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, $ipAddress))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK);
})->with([
    '40.223.170.0',
    '40.223.1.44',
    '172.121.117.0',
    '172.252.78.0',
    '172.252.255.255',
]);

it('leaves addresses adjacent to blocked networks alone', function (string $ipAddress) {
    $browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, $ipAddress))->toBeNull();
})->with([
    '40.222.255.255',
    '40.224.0.0',
    '172.120.255.255',
    '172.122.0.0',
    '172.251.255.255',
    '172.253.0.0',
]);

it('matches subnet-anonymised and raw forms of the same address alike', function () {
    $browser = 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, '40.223.170.219'))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK)
        ->and(BotDetectionService::detect($browser, '40.223.170.0'))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK);
});

it('reports the network reason ahead of the user agent reason', function () {
    expect(BotDetectionService::detect('curl/8.5.0', '40.223.170.0'))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK);
});

it('tolerates addresses that are not parseable', function () {
    $browser = 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, 'hash_ab12cd34ef56'))->toBeNull()
        ->and(BotDetectionService::detect($browser, '***'))->toBeNull()
        ->and(BotDetectionService::detect($browser, null))->toBeNull();
});

it('honours networks added through configuration', function () {
    Config::set('analytics.bot_detection.blocked_networks', ['198.51.100.0/24']);
    BotDetectionService::flushCache();

    $browser = 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, '198.51.100.7'))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK)
        ->and(BotDetectionService::detect($browser, '198.51.101.7'))->toBeNull()
        ->and(BotDetectionService::detect($browser, '40.223.170.0'))->toBeNull();
});

it('supports IPv6 prefixes', function () {
    Config::set('analytics.bot_detection.blocked_networks', ['2001:db8::/32']);
    BotDetectionService::flushCache();

    $browser = 'Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36';

    expect(BotDetectionService::detect($browser, '2001:db8:1234::'))
        ->toBe(BotDetectionService::REASON_BLOCKED_NETWORK)
        ->and(BotDetectionService::detect($browser, '2001:db9::1'))->toBeNull();
});

it('never convicts a row belonging to an erased account', function (?string $userAgent, ?string $ipAddress) {
    expect(BotDetectionService::detect($userAgent, $ipAddress, 'anonymized_a1b2c3d4'))->toBeNull();
})->with([
    // Anonymisation strips the identifying fields, and their absence must not
    // read as evidence of automation.
    [null, null],
    ['', null],
    ['Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36', null],
    ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', '203.0.113.0'],
    ['Mozilla/5.0 (Macintosh) Chrome/142.0.0.0 Safari/537.36', '40.223.170.0'],
]);

it('still judges rows whose session id merely resembles the marker', function () {
    expect(BotDetectionService::detect(null, null, 'anonymizedsomething'))
        ->toBe(BotDetectionService::REASON_NO_USER_AGENT)
        ->and(BotDetectionService::detect(null, null, 'session-anonymized_x'))
        ->toBe(BotDetectionService::REASON_NO_USER_AGENT);
});
