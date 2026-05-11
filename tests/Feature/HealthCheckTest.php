<?php

declare(strict_types=1);

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
