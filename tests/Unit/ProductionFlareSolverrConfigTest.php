<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

test('flaresolverr has no runtime disable switch', function () {
    expect(config('services.flaresolverr'))->not->toHaveKey('enabled');
});

test('production flaresolverr is isolated from the application data network', function () {
    $compose = file_get_contents(base_path('docker/production/docker-compose.yml'));
    $exampleEnv = file_get_contents(base_path('.env.example'));
    $appService = dockerComposeServiceBlock($compose, 'app');
    $flareSolverrService = dockerComposeServiceBlock($compose, 'flaresolverr');
    $services = Yaml::parse($compose)['services'];

    expect($compose)->not->toBeFalse()
        ->and($exampleEnv)->not->toBeFalse()
        ->and($exampleEnv)->not->toContain('FLARESOLVERR_ENABLED')
        ->and($compose)->not->toContain('FLARESOLVERR_ENABLED')
        ->and($compose)->toContain('- flaresolverr')
        ->and($compose)->toContain('${COMPOSE_PROJECT_NAME}_flaresolverr');

    expect(dockerComposeNetworkNames($services['app']['networks']))->toBe(['fvnli', 'flaresolverr', 'web'])
        ->and(dockerComposeNetworkNames($services['flaresolverr']['networks']))->toBe(['flaresolverr'])
        ->and($appService)->toContain("    networks:\n      fvnli:")
        ->and($flareSolverrService)->toContain("    networks:\n      - flaresolverr")
        ->and($flareSolverrService)->not->toContain('      - fvnli')
        ->and($flareSolverrService)->not->toContain('      - web');
});

test('production redis save command passes interval and change count separately', function () {
    $compose = file_get_contents(base_path('docker/production/docker-compose.yml'));
    $redisService = dockerComposeServiceBlock($compose, 'redis');

    expect($compose)->not->toBeFalse()
        ->and($redisService)->toContain("    command:\n      - \"redis-server\"\n      - \"--save\"\n      - \"60\"\n      - \"1\"")
        ->and($redisService)->not->toContain('- "60 1"');
});

function dockerComposeServiceBlock(string $compose, string $service): string
{
    $matched = preg_match(
        '/^  '.preg_quote($service, '/').":\n(?:(?!^  [A-Za-z0-9_-]+:).*\n?)*/m",
        $compose,
        $matches
    );

    expect($matched)->toBe(1);

    return $matches[0];
}

function dockerComposeNetworkNames(array $networks): array
{
    if (array_is_list($networks)) {
        return $networks;
    }

    return array_keys($networks);
}
