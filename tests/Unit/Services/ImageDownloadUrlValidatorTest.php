<?php

declare(strict_types=1);

use App\Services\ImageDownloadUrlValidator;

beforeEach(function () {
    $this->validator = new ImageDownloadUrlValidator;
});

test('it accepts configured trusted image hosts over https', function (string $url) {
    expect($this->validator->validate($url))->toBe($url);
})->with([
    'itch cdn' => ['https://img.itch.zone/aW1hZ2Uvfixture.webp'],
    'itch legacy cdn' => ['https://img.itch.io/fixture.png'],
    'steam akamai cdn' => ['https://shared.akamai.steamstatic.com/store_item_assets/header.jpg'],
    'steam cloudflare cdn' => ['https://shared.cloudflare.steamstatic.com/store_item_assets/header.jpg'],
    'booth pixiv cdn' => ['https://booth.pximg.net/c/620x620/12345678-i000000000000-1.png'],
]);

test('it rejects untrusted or unsafe image urls', function (string $url, string $message) {
    expect(fn () => $this->validator->validate($url))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'plain http' => ['http://img.itch.zone/file.png', 'HTTPS'],
    'localhost' => ['https://localhost/file.png', 'Untrusted image host'],
    'loopback ip' => ['https://127.0.0.1/file.png', 'Untrusted image host'],
    'metadata ip' => ['https://169.254.169.254/latest/meta-data.png', 'Untrusted image host'],
    'host suffix confusion' => ['https://img.itch.zone.evil.test/file.png', 'Untrusted image host'],
    'booth host suffix confusion' => ['https://booth.pximg.net.evil.test/file.png', 'Untrusted image host'],
    'credentials' => ['https://user:pass@img.itch.zone/file.png', 'credentials'],
    'missing host' => ['https:///file.png', 'Invalid image URL'],
]);
