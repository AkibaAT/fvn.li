<?php

declare(strict_types=1);

test('production Postgres initializes the DenKit Stash role and database on a fresh volume', function () {
    $compose = file_get_contents(base_path('docker/production/docker-compose.yml'));
    $initializer = file_get_contents(base_path('scripts/postgres-init-denkit-stash.sh'));

    expect($compose)
        ->not->toContain('denkit-stash-db-init:')
        ->toContain('DENKIT_STASH_POSTGRES_DATABASE=${DENKIT_STASH_POSTGRES_DATABASE:-butler}')
        ->toContain('DENKIT_STASH_POSTGRES_USERNAME=${DENKIT_STASH_POSTGRES_USERNAME:-denkit_stash}')
        ->toContain('DENKIT_STASH_POSTGRES_PASSWORD=${DENKIT_STASH_POSTGRES_PASSWORD}')
        ->toContain('${PWD}/scripts/postgres-init-denkit-stash.sh:/docker-entrypoint-initdb.d/10-denkit-stash.sh:ro')
        ->and($initializer)
        ->not->toContain('--set=denkit_password=')
        ->toContain('\getenv denkit_password DENKIT_STASH_POSTGRES_PASSWORD')
        ->toContain('CREATE ROLE %I LOGIN PASSWORD %L')
        ->toContain('CREATE DATABASE %I OWNER %I');
});
