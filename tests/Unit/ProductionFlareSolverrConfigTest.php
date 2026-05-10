<?php

declare(strict_types=1);

test('flaresolverr is opt in by default', function () {
    expect(config('services.flaresolverr.enabled'))->toBeFalse();
});

test('production flaresolverr is isolated from the application data network', function () {
    $compose = file_get_contents(base_path('docker/production/docker-compose.yml'));
    $exampleEnv = file_get_contents(base_path('.env.example'));
    $appService = dockerComposeServiceBlock($compose, 'app');
    $flareSolverrService = dockerComposeServiceBlock($compose, 'flaresolverr');

    expect($compose)->not->toBeFalse()
        ->and($exampleEnv)->not->toBeFalse()
        ->and($exampleEnv)->toContain('FLARESOLVERR_ENABLED=false')
        ->and($compose)->toContain('- flaresolverr')
        ->and($compose)->toContain('${COMPOSE_PROJECT_NAME}_flaresolverr');

    expect($appService)->toContain("    networks:\n      - fvnli\n      - flaresolverr\n      - web")
        ->and($flareSolverrService)->toContain("    networks:\n      - flaresolverr")
        ->and($flareSolverrService)->not->toContain('      - fvnli')
        ->and($flareSolverrService)->not->toContain('      - web');
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
