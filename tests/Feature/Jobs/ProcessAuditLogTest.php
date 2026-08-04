<?php

declare(strict_types=1);

use App\Jobs\ProcessAuditLog;
use App\Models\ChangeLog;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

it('does not log full audit payloads for recoverable stale user fallback', function () {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }

    $staleUser = User::factory()->create();
    $staleUserId = $staleUser->id;
    $staleUser->delete();
    ChangeLog::query()->delete();

    try {
        Log::shouldReceive('warning')
            ->withArgs(function (string $message, array $context): bool {
                return str_starts_with($message, 'Audit log creation failed on attempt ')
                    && isset($context['audit'])
                    && ! isset($context['audit_data'])
                    && ! array_key_exists('old_values', $context['audit'])
                    && ! array_key_exists('new_values', $context['audit'])
                    && ! array_key_exists('context', $context['audit']);
            })
            ->once();

        Log::shouldReceive('warning')
            ->with('Audit log inserted with system user due to FK violation', Mockery::type('array'))
            ->once();

        $job = new ProcessAuditLog(auditPayload([
            'user_id' => $staleUserId,
            'old_values' => ['status' => 'playing', 'rating' => 10],
            'new_values' => ['status' => 'completed', 'rating' => 8],
            'context' => [
                'url' => 'https://fvn.li/dashboard/lists?token=SECRET_URL_TOKEN',
                'session_id' => 'SESSIONID_SHOULD_NOT_LEAK',
            ],
        ]));

        $job->handle();

        $log = ChangeLog::query()
            ->where('entity_type', Game::class)
            ->where('entity_id', 123)
            ->firstOrFail();

        expect($log->user_id)->not->toBe($staleUserId)
            ->and($log->old_values)->toMatchArray(['status' => 'playing', 'rating' => 10])
            ->and($log->context['session_id'])->toBe('SESSIONID_SHOULD_NOT_LEAK');
    } finally {
        DB::table('change_logs')->delete();
        DB::table('users')->where('id', $staleUserId)->delete();
        DB::beginTransaction();
    }
});

it('logs permanent audit job failures without throwing', function () {
    $job = new ProcessAuditLog(auditPayload());
    Log::shouldReceive('error')
        ->once()
        ->with('Audit log job failed permanently', Mockery::on(
            fn (array $context): bool => $context['error'] === 'permanent failure'
                && $context['exception'] instanceof RuntimeException
        ));

    $job->failed(new RuntimeException('permanent failure'));
});
