<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

test('production Redis health check rejects loading and failed replies', function (string $reply, int $exitCode, bool $ready) {
    $healthcheck = Yaml::parseFile(base_path('docker/production/docker-compose.yml'))['services']['redis']['healthcheck']['test'];
    $command = $healthcheck[0] === 'CMD' ? array_slice($healthcheck, 1) : ['sh', '-c', $healthcheck[1]];
    $fakeBinPath = sys_get_temp_dir() . '/fvn-redis-health-' . bin2hex(random_bytes(8));
    mkdir($fakeBinPath);
    file_put_contents($fakeBinPath . '/redis-cli', <<<'SH'
#!/bin/sh
printf '%s\n' "$FVN_REDIS_REPLY"
exit "$FVN_REDIS_EXIT"
SH);
    chmod($fakeBinPath . '/redis-cli', 0755);

    try {
        $process = new Process($command, env: [
            'PATH' => $fakeBinPath . ':' . getenv('PATH'),
            'FVN_REDIS_REPLY' => $reply,
            'FVN_REDIS_EXIT' => (string) $exitCode,
        ], timeout: 5);
        $process->run();

        expect($process->isSuccessful())->toBe($ready);
    } finally {
        File::deleteDirectory($fakeBinPath);
    }
})->with([
    'ready' => ['PONG', 0, true],
    'loading dataset with successful CLI exit' => ['LOADING Redis is loading the dataset in memory', 0, false],
    'authentication failure with successful CLI exit' => ['NOAUTH Authentication required', 0, false],
    'connection failure' => ['', 1, false],
]);

test('production deploy fails before docker mutations when DenKit configuration is incomplete', function () {
    $deploymentPath = sys_get_temp_dir() . '/fvn-deploy-test-' . bin2hex(random_bytes(8));
    $fakeBinPath = $deploymentPath . '/bin';
    mkdir($fakeBinPath, 0777, true);

    copy(base_path('scripts/deploy.sh'), $deploymentPath . '/deploy.sh');
    file_put_contents($deploymentPath . '/.env', implode("\n", [
        'DB_USERNAME=fvn',
        'DB_PASSWORD=database-secret',
        'DENKIT_STASH_IMAGE=registry.example/denkit-stash:latest',
        'DENKIT_STASH_S3_ACCESS_KEY=rustfs-access',
        'DENKIT_STASH_S3_SECRET_KEY=rustfs-secret',
        'DENKIT_API_KEY_HASH_SECRET=hash-secret',
        '',
    ]));
    file_put_contents($deploymentPath . '/.env.deploy', "DOCKER_IMAGE=registry.example/fvn:latest\n");
    file_put_contents($fakeBinPath . '/docker', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "${DOCKER_LOG}"
SH);
    chmod($fakeBinPath . '/docker', 0755);

    $dockerLog = $deploymentPath . '/docker.log';
    try {
        $process = new Process(
            ['bash', './deploy.sh'],
            $deploymentPath,
            [
                'PATH' => $fakeBinPath . ':' . getenv('PATH'),
                'DOCKER_LOG' => $dockerLog,
                'DENKIT_STASH_POSTGRES_PASSWORD' => '',
            ],
        );
        $process->run();

        expect($process->isSuccessful())->toBeFalse()
            ->and($process->getOutput())->toContain('Deployment configuration is incomplete; refusing to modify the running stack.')
            ->and($process->getOutput())->toContain('Missing required value: DENKIT_STASH_POSTGRES_PASSWORD')
            ->and(file_exists($dockerLog))->toBeFalse();
    } finally {
        File::deleteDirectory($deploymentPath);
    }
});
