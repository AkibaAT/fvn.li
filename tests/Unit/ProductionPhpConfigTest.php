<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

function productionDockerFile(string $path): string
{
    return base_path($path);
}

function productionIniValue(string $contents, string $key): ?string
{
    if (! preg_match('/^'.preg_quote($key, '/').'\s*=\s*(.+)$/m', $contents, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

test('production global php config keeps finite cli resource limits', function () {
    $phpIni = file_get_contents(productionDockerFile('docker/app/php.ini'));

    expect($phpIni)->not->toBeFalse()
        ->and(productionIniValue($phpIni, 'memory_limit'))->toBe('1G')
        ->and(productionIniValue($phpIni, 'max_execution_time'))->toBe('120')
        ->and(productionIniValue($phpIni, 'max_input_time'))->toBe('120');
});

test('production docker image applies global php config to cli entrypoints', function () {
    $dockerfile = file_get_contents(productionDockerFile('docker/app/Dockerfile'));
    $supervisorConfigs = glob(productionDockerFile('docker/app/supervisor/*.conf'));

    expect($dockerfile)->not->toBeFalse()
        ->and($dockerfile)->toContain('COPY docker/app/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini')
        ->and($supervisorConfigs)->not->toBeEmpty();

    foreach ($supervisorConfigs as $configPath) {
        $contents = file_get_contents($configPath);

        expect($contents)->not->toBeFalse()
            ->and($contents)->not->toContain('memory_limit=-1')
            ->and($contents)->not->toContain('max_execution_time=0');
    }
});

test('production docker base image is pinned to an immutable php patch image digest', function () {
    $dockerfile = file_get_contents(productionDockerFile('docker/app/Dockerfile'));

    expect($dockerfile)->not->toBeFalse();

    preg_match('/^FROM\s+([^\s]+)$/m', $dockerfile, $matches);
    $baseImage = $matches[1] ?? '';
    preg_match('/frankenphp:([\d.]+)-php/', $baseImage, $version);
    expect(version_compare($version[1] ?? '0', '1.13.2', '>='))->toBeTrue();

    expect($baseImage)
        ->toMatch('/^dunglas\/frankenphp:\d+\.\d+\.\d+-php8\.5\.\d+@sha256:[a-f0-9]{64}$/')
        ->toContain('@sha256:')
        ->not->toMatch('/^dunglas\/frankenphp:php8\.5(@|$)/');
});

test('production php includes the October 2026 critical security fixes', function () {
    $dockerfile = file_get_contents(productionDockerFile('docker/app/Dockerfile'));
    preg_match('/-php(8\.5\.\d+)@/', $dockerfile, $matches);

    // CVE-2026-109618 and CVE-2026-109619 require the 2026-10-21 security release.
    expect(version_compare($matches[1] ?? '0', '8.5.12', '>='))->toBeTrue();
});

test('dependabot scans all maintained container definitions', function () {
    $dependabot = Yaml::parseFile(base_path('.github/dependabot.yml'));
    $expectedContainerUpdates = [
        'docker:/docker/app',
        'docker:/docker/social-images',
        'docker-compose:/docker/production',
        'docker-compose:/.ddev',
    ];
    $containerUpdates = collect($dependabot['updates'] ?? [])
        ->map(fn (array $update): string => ($update['package-ecosystem'] ?? '').':'.($update['directory'] ?? ''));

    expect($containerUpdates)->toContain(...$expectedContainerUpdates);

    collect($dependabot['updates'] ?? [])
        ->filter(fn (array $update): bool => in_array(
            ($update['package-ecosystem'] ?? '').':'.($update['directory'] ?? ''),
            $expectedContainerUpdates,
            true,
        ))
        ->each(fn (array $update) => expect($update['schedule']['interval'] ?? null)->toBe('weekly'));
});

test('frankenphp web override remains bounded separately from global cli config', function () {
    $caddyfile = file_get_contents(productionDockerFile('docker/app/Caddyfile'));

    expect($caddyfile)->not->toBeFalse()
        ->and($caddyfile)->toContain('php_ini')
        ->and($caddyfile)->toContain('memory_limit 1G')
        ->and($caddyfile)->toContain('max_execution_time 60')
        ->and($caddyfile)->toContain('max_input_time 60');
});

test('browser images match the Playwright client and explicitly distrust the recalled roots', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $socialPackage = json_decode(file_get_contents(base_path('docker/social-images/package.json')), true, flags: JSON_THROW_ON_ERROR);
    $version = $package['devDependencies']['@playwright/test'];
    $dockerfile = file_get_contents(base_path('docker/social-images/Dockerfile'));

    expect($version)->toBe('1.64.1')
        ->and($socialPackage['dependencies']['playwright-core'])->toBe($version)
        ->and($dockerfile)->toContain('playwright:v'.$version.'-noble@sha256:', "'-t', 'p,p,p'", "readFileSync('/tmp/recalled-ca.pem'")
        ->and(file_get_contents(base_path('.ddev/docker-compose.playwright.yaml')))->toContain('playwright@'.$version.' run-server');

    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', file_get_contents(base_path('docker/social-images/recalled-ca.pem')), $matches);
    $fingerprints = array_map(fn (string $certificate) => openssl_x509_fingerprint($certificate, 'sha256'), $matches[0]);
    expect($fingerprints)->toHaveCount(2)->toContain(
        '86a1ecba089c4a8d3bbe2734c612ba341d813e043cf9e8a862cd5c57a36bbe6b',
        'd7a7a0fb5d7e2731d771e9484ebcdef71d5f0c3e0a2948782bc83ee0ea699ef4',
    );
});
