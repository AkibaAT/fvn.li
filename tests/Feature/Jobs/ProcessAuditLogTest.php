<?php

declare(strict_types=1);

use App\Jobs\ProcessAuditLog;
use App\Models\ChangeLog;
use App\Models\Game;

function auditPayload(array $overrides = []): array
{
    return array_merge([
        'timestamp' => now(),
        'event_type' => ChangeLog::EVENT_CREATED,
        'entity_type' => Game::class,
        'entity_id' => 123,
        'user_id' => null,
        'changes' => [],
        'old_values' => [],
        'new_values' => ['name' => 'Game'],
        'context' => ['source' => 'test'],
        'source' => ChangeLog::SOURCE_SYSTEM,
    ], $overrides);
}

it('creates audit logs synchronously from queued payloads', function () {
    $job = new ProcessAuditLog(auditPayload());

    $job->handle();

    $log = ChangeLog::firstOrFail();

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60)
        ->and($log->entity_type)->toBe(Game::class)
        ->and($log->new_values)->toBe(['name' => 'Game'])
        ->and($job->retryUntil()->greaterThan(now()->addMinutes(9)))->toBeTrue();
});

it('logs permanent audit job failures without throwing', function () {
    $job = new ProcessAuditLog(auditPayload());

    $job->failed(new RuntimeException('permanent failure'));

    expect(true)->toBeTrue();
});
