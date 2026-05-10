<?php

declare(strict_types=1);

use App\Services\ItchUrlSafetyValidator;

it('recognizes only the official itch api host as an api request', function () {
    $validator = new ItchUrlSafetyValidator;

    expect($validator->isApiRequest('https://api.itch.io/games'))->toBeTrue()
        ->and($validator->isApiRequest('https://api.itch.io.evil.test/games'))->toBeFalse()
        ->and($validator->isApiRequest('https://creator.itch.io/game'))->toBeFalse();
});

it('rejects non itch hosts before they can be sent to flaresolverr', function (string $url, string $message) {
    $validator = new ItchUrlSafetyValidator;

    expect(fn () => $validator->validate($url))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'loopback url' => ['http://127.0.0.1:7777/internal-admin', 'Itch.io URL must use HTTPS'],
    'docker service url' => ['http://redis:6379/', 'Itch.io URL must use HTTPS'],
    'metadata ip' => ['https://169.254.169.254/latest/meta-data', 'Untrusted itch.io host'],
    'host suffix confusion' => ['https://itch.io.evil.test/game', 'Untrusted itch.io host'],
    'missing host' => ['https:///game', 'Invalid itch.io URL'],
]);
