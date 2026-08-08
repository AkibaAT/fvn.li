<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('returns a generic unhealthy response without backend exception details', function () {
    Log::spy();

    app()->instance('cache', new class
    {
        public function store(string $name): never
        {
            throw new RuntimeException('Connection refused [tcp://redis.internal.fvnli:6379], user=fvn_cache');
        }
    });

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'error'])
        ->assertJsonMissingPath('message');

    Log::shouldHaveReceived('warning')
        ->with('Health check failed', Mockery::on(
            fn (array $context) => str_contains($context['error'], 'redis.internal.fvnli')
                && $context['exception_class'] === RuntimeException::class
        ))
        ->once();
});

it('reports cached scheduler and notification pipeline health without changing ok semantics', function () {
    $now = now()->startOfSecond();
    $this->travelTo($now);
    Cache::forget('health.pipeline');
    Cache::put('scheduler.heartbeat', $now->copy()->subSeconds(90)->toISOString(), 600);
    $user = User::factory()->create();

    DB::table('notification_queue')->insert([
        [
            'user_id' => $user->id,
            'game_id' => null,
            'game_version_id' => null,
            'channel' => 'browser',
            'payload' => '{}',
            'status' => 'pending',
            'scheduled_at' => $now->copy()->subMinutes(10),
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'user_id' => $user->id,
            'game_id' => null,
            'game_version_id' => null,
            'channel' => 'browser',
            'payload' => '{}',
            'status' => 'processing',
            'scheduled_at' => $now->copy()->subHour(),
            'created_at' => $now->copy()->subHour(),
            'updated_at' => $now->copy()->subMinutes(20),
        ],
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => fake()->uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'test',
        'failed_at' => $now,
    ]);

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('scheduler.age_seconds', 90)
        ->assertJsonPath('scheduler.stale', false)
        ->assertJsonPath('notification_queue.pending_due_count', 1)
        ->assertJsonPath('notification_queue.oldest_due_age_seconds', 600)
        ->assertJsonPath('notification_queue.stale_processing_count', 1)
        ->assertJsonPath('failed_jobs_count', 1);
});
