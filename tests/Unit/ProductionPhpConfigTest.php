<?php

declare(strict_types=1);

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

test('frankenphp web override remains bounded separately from global cli config', function () {
    $caddyfile = file_get_contents(productionDockerFile('docker/app/Caddyfile'));

    expect($caddyfile)->not->toBeFalse()
        ->and($caddyfile)->toContain('php_ini')
        ->and($caddyfile)->toContain('memory_limit 1G')
        ->and($caddyfile)->toContain('max_execution_time 60')
        ->and($caddyfile)->toContain('max_input_time 60');
});
