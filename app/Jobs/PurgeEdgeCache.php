<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\EdgeCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class PurgeEdgeCache implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const int WINDOW_SECONDS = 30;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    public int $uniqueFor = self::WINDOW_SECONDS;

    public function __construct()
    {
        $this->onQueue('default');
        $this->delay(self::WINDOW_SECONDS);
    }

    public function handle(): void
    {
        $tags = EdgeCache::takePending();

        if ($tags === []) {
            return;
        }

        try {
            EdgeCache::send($tags);
        } catch (Throwable $exception) {
            EdgeCache::purge($tags);

            throw $exception;
        }
    }
}
