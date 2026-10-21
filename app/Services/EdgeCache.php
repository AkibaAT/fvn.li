<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\PurgeEdgeCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EdgeCache
{
    public const string GAMES = 'games';

    public const string LISTS = 'lists';

    public const string RATINGS = 'ratings';

    public const int MAX_TAGS_PER_REQUEST = 100;

    private const string PENDING_KEY = 'edge-cache:pending-tags';

    private const int PENDING_TTL_SECONDS = 3600;

    public static function game(int $gameId): string
    {
        return "game-{$gameId}";
    }

    public static function isEnabled(): bool
    {
        return filled(config('services.cloudflare.zone_id')) && filled(config('services.cloudflare.api_token'));
    }

    /**
     * @param  list<string>  $tags
     */
    public static function purge(array $tags): void
    {
        if (! self::isEnabled() || $tags === []) {
            return;
        }

        Cache::lock(self::PENDING_KEY . ':lock', 5)->block(5, function () use ($tags): void {
            $pending = Cache::get(self::PENDING_KEY, []);
            Cache::put(self::PENDING_KEY, array_values(array_unique([...$pending, ...$tags])), self::PENDING_TTL_SECONDS);
        });

        PurgeEdgeCache::dispatch()->afterCommit();
    }

    /**
     * @return list<string>
     */
    public static function takePending(): array
    {
        return Cache::lock(self::PENDING_KEY . ':lock', 5)->block(5, fn (): array => Cache::pull(self::PENDING_KEY, []));
    }

    /**
     * @param  list<string>  $tags
     */
    public static function send(array $tags): void
    {
        $payload = count($tags) > self::MAX_TAGS_PER_REQUEST ? ['purge_everything' => true] : ['tags' => $tags];

        self::request($payload);
    }

    public static function purgeEverything(): void
    {
        if (self::isEnabled()) {
            self::request(['purge_everything' => true]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function request(array $payload): void
    {
        Http::withToken((string) config('services.cloudflare.api_token'))
            ->timeout(15)
            ->post('https://api.cloudflare.com/client/v4/zones/' . config('services.cloudflare.zone_id') . '/purge_cache', $payload)
            ->throw();
    }
}
